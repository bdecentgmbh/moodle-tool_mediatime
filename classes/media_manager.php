<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Manage Media Time source files
 *
 * @package    tool_mediatime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mediatime;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use core_course\hook\before_course_deleted;
use block_contents;
use context_system;
use core_tag_tag;
use core_tag_area;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use single_button;
use single_select;
use templatable;
use stdClass;
use recordset;

/**
 * Manage Media Time source files
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_manager implements renderable, templatable {
    /** @var \moodleform $form Move resource form */
    protected $form = null;

    /** @var array $media List of media resources to display */
    protected $media = null;

    /** @var int $page Paging offset */
    protected int $page = 0;

    /** @var $source Source manager */
    protected $source = null;

    /** @var $page Search form */
    protected $search = null;

    /** @var ?stdClass $record Media Time resource record */
    protected $record;

    /** @var ?\context $context Context */
    protected $context;

    /**
     * Constructor
     *
     * @param string $source Source plugin type to add
     * @param stdClass|null $record Media Time resource record
     * @param int $page Paging offset
     */
    public function __construct(string $source, ?stdClass $record = null, int $page = 0) {
        global $DB;

        $this->page = $page;

        $this->record = $record;

        if ($record) {
            $source = $record->source;
            $this->context = \context::instance_by_id($record->contextid);
        } else {
            $this->context = \context::instance_by_id(optional_param('contextid', SYSCONTEXTID, PARAM_INT));
            if (
                ($source = optional_param('source', null, PARAM_ALPHA))
                && !component_callback("mediatimesrc_$source", 'can_manage', [$this->context], false)
            ) {
                require_capability('tool/mediatime:manage', $this->context);
            }
        }
        if (empty($this->record)) {
            require_capability('tool/mediatime:view', $this->context);
        }

        $plugins = plugininfo\mediatimesrc::get_enabled_plugins();
        if (!empty($source) && !in_array($source, $plugins)) {
            throw new moodle_exception('invalidsource');
        }

        if (($action = optional_param('action', null, PARAM_ALPHA)) && $action == 'move') {
            $formclass = "\\tool_mediatime\\form\\{$action}_resource";
            $this->form = new $formclass((new moodle_url('/admin/tool/mediatime', [
                'id' => $this->record->id,
                'action' => 'move',
            ]))->out(false), [
                'context' => $this->context,
                'record' => $this->record,
            ], 'GET');
            if ($this->form->is_cancelled()) {
                $url = new moodle_url('/admin/tool/mediatime', ['contextid' => $this->context->id]);
                redirect($url);
            } else if ($this->form->is_submitted()) {
                $data = $this->form->get_data();
                if ($data->contextlevel == CONTEXT_SYSTEM) {
                    $this->record->contextid = SYSCONTEXTID;
                    $this->record->groupid = 0;
                } else if ($data->contextlevel == CONTEXT_COURSECAT && !empty($data->categoryid)) {
                    $context = \context_coursecat::instance($data->categoryid);
                    $this->record->contextid = $context->id;
                    $this->record->groupid = 0;
                } else if ($data->contextlevel == CONTEXT_COURSE && !empty($data->courseid)) {
                    $context = \context_course::instance($data->courseid);
                    $course = get_course($data->courseid);
                    if (groups_get_course_groupmode($course)) {
                        $this->record->groupid = groups_get_course_group($course);
                    } else {
                        $this->record->groupid = 0;
                    }
                    $this->record->contextid = $context->id;
                }
                $DB->update_record('tool_mediatime', $this->record);
                $eventclass = "\\mediatimesrc_{$this->record->source}\\event\\resource_created";
                $event = $eventclass::create_from_record($this->record);
                $event->trigger();

                $url = new moodle_url('/admin/tool/mediatime', ['contextid' => $this->record->contextid]);
                redirect($url);
            }
        }

        $this->media = [];
        if ($source) {
            $classname = "\\mediatimesrc_$source\\manager";
            if (class_exists($classname)) {
                $this->source = new $classname($this->record);
            }
        } else {
            $this->search = new form\search((new moodle_url('/admin/tool/mediatime', [
                'contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT),
            ]))->out(), [
                'contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT),
            ], 'GET', '', [
                'class' => 'form-inline',
            ]);

            if ($this->search->is_cancelled()) {
                $url = new moodle_url('/admin/tool/mediatime/index.php', [
                    'contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT),
                ]);
                redirect($url);
            }

            $rs = self::search([
                'contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT),
            ] + (array)$this->search->get_data());
            foreach ($rs as $media) {
                if (in_array($media->source, $plugins)) {
                    $media->content = $media->content ?? '{}';
                    $this->media[] = $media;
                }
            }
            $this->setup_page();

            $rs->close();
        }
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB, $USER;

        if (!empty($this->form)) {
            $context = [
                'libraryhome' => (new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $this->context->id]))->out(),
                'resource' => $this->form->render(),
            ];

            return $context;
        }
        if (!empty($this->source)) {
            $context = [
                'libraryhome' => (new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $this->context->id]))->out(),
                'resource' => $output->render($this->source),
            ];
            if (!empty($this->record) && empty(optional_param('edit', null, PARAM_INT))) {
                $resource = new output\media_resource($this->record);
                $context['tags'] = $resource->tags($output);
            }

            return $context;
        }

        $media = [];
        if ($this->context instanceof \context_course) {
            $course = get_course($this->context->instanceid);
            if ($groupmode = groups_get_course_groupmode($course)) {
                $group = groups_get_course_group($course);
            }
        }
        foreach ($this->media as $record) {
            $resource = new output\media_resource($record);
            $url = new moodle_url('/admin/tool/mediatime/index.php', ['id' => $record->id]);
            $editurl = new moodle_url('/admin/tool/mediatime/index.php', ['edit' => $record->id]);
            $removeurl = new moodle_url('/admin/tool/mediatime/index.php', ['delete' => $record->id]);
            $moveurl = new moodle_url('/admin/tool/mediatime/index.php', ['id' => $record->id, 'action' => 'move']);
            $media[] = [
                'canedit' => has_capability('tool/mediatime:manage', $this->context)
                    || component_callback("mediatimesrc_$record->source", 'can_manage', [$this->context], false)
                    || (!empty($record) && $USER->id == $record->usermodified),
                'group' => !empty($groupmode) && !empty($record->groupid)
                    && ($group = groups_get_group($record->groupid)) ? $group->name : '',
                'imageurl' => $resource->image_url($output),
                'tags' => $resource->tags($output),
                'url' => $url->out(),
                'name' => $record->name,
                'title' => $resource->get_title(),
                'description' => shorten_text(json_decode($record->content)->description ?? '', 80),
                'editurl' => $editurl->out(false),
                'moveurl' => $moveurl->out(false),
                'removeurl' => $removeurl->out(false),
            ];
        }

        $plugins = \core_plugin_manager::instance()->get_installed_plugins('mediatimesrc');

        $options = [];
        foreach (plugininfo\mediatimesrc::get_enabled_plugins() as $plugin) {
            $options[$plugin] = get_string("pluginname", "mediatimesrc_$plugin");
        }
        if (!has_capability('tool/mediatime:manage', $this->context)) {
            $action = '';
        } else if (count($options) == 1) {
            $button = new single_button(new moodle_url('/admin/tool/mediatime/index.php', [
                'source' => array_keys($options)[0],
                'contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT),
            ]), get_string('addnewcontent', 'tool_mediatime'));
            $action = $output->render($button);
        } else if (count($options)) {
            $select = new single_select(new moodle_url('/admin/tool/mediatime/index.php', [
                'contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT),
            ]), 'source', $options);
            $action = get_string('addnewcontent', 'tool_mediatime') . ' ' . $output->render($select);
        } else {
            $action = '';
        }

        return [
            'media' => array_values($media),
        ];
    }

    /**
     * Return search query
     *
     * @param array $filters Parameters for search
     * @return recordset
     */
    public static function search($filters = []) {
        global $DB;

        $params = [];

        // Require enabled source plugins.
        if (!$sources = plugininfo\mediatimesrc::get_enabled_plugins()) {
            return $result;
        }
        [$sql, $params] = $DB->get_in_or_equal($sources, SQL_PARAMS_NAMED);

        $sql = "source $sql";
        $order = 'timecreated DESC';

        // Filter for text query.
        if ($query = $filters['query'] ?? '') {
            $sql .= ' AND ' . $DB->sql_like('content', ':query', false);
            $params['query'] = "%$query%";

            $params['name'] = "%$query%";
            $order = 'CASE WHEN ' . $DB->sql_like('name', ':name', false) . ' THEN 1 ELSE 0 END DESC, timecreated DESC';
        }

        // Filter by context.
        if ($contextid = $filters['contextid'] ?? '') {
            $sql .= ' AND contextid = :contextid';
            $params['contextid'] = $contextid;

            $context = \context::instance_by_id($contextid);
            if (
                $context instanceof \context_course
                && ($course = get_course($context->instanceid))
                && ($groupmode = groups_get_course_groupmode($course))
                && $group = optional_param('group', groups_get_course_group($course), PARAM_INT)
            ) {
                $sql .= ' AND (groupid = :groupid OR groupid = 0)';
                $params['groupid'] = $group;
            }
        }

        // Filter by tags.
        if ($tags = $filters['tags'] ?? '') {
            $tagcollid = core_tag_area::get_collection('tool_mediatime', 'tool_mediatime');
            $tags = core_tag_tag::get_by_name_bulk($tagcollid, $tags);
            $tags = array_column($tags, 'id');
            if (!empty($tags)) {
                [$tagsql, $tagparams] = $DB->get_in_or_equal($tags, SQL_PARAMS_NAMED, 'tagparams');
                $sql .= " AND id IN (SELECT itemid FROM {tag_instance} WHERE tagid $tagsql)";
                $params += $tagparams;
            }
        }

        return $DB->get_recordset_select(
            'tool_mediatime',
            $sql,
            $params,
            $order
        );
    }

    /**
     * Add fake block for search and adding resources
     */
    protected function setup_page() {
        global $OUTPUT, $PAGE, $USER;

        $bc = new block_contents();
        $bc->title = get_string('sharedvideo', 'videotimeplugin_live');
        $bc->attributes['class'] = 'block block_book_toc';

        $options = [];
        foreach (plugininfo\mediatimesrc::get_enabled_plugins() as $plugin) {
            if (
                has_capability('tool/mediatime:manage', $this->context)
                || component_callback("mediatimesrc_$plugin", 'can_manage', [$this->context], false)
            ) {
                $options[$plugin] = get_string("pluginname", "mediatimesrc_$plugin");
            }
        }
        if (count($options) == 1) {
            $button = new single_button(new moodle_url('/admin/tool/mediatime/index.php', [
                'contextid' => $this->context->id,
                'source' => array_keys($options)[0],
            ]), get_string('addnewcontent', 'tool_mediatime'));
            $action = $OUTPUT->render($button);
        } else if (count($options)) {
            $select = new single_select(new moodle_url('/admin/tool/mediatime/index.php', [
                'contextid' => $this->context->id,
            ]), 'source', $options);
            $action = get_string('addnewcontent', 'tool_mediatime') . ' ' . $OUTPUT->render($select);
        } else {
            return;
        }
        $bc->content = $OUTPUT->render_from_template('tool_mediatime/block_content', [
            'action' => $action,
            'search' => $this->search->render(),
        ]);

        $defaultregion = $PAGE->blocks->get_default_region();
        $PAGE->blocks->add_fake_block($bc, $defaultregion);
    }

    /**
     * Observe hook to delete resource when a course is deleted
     *
     * @param course_content_deleted $hook
     */
    public static function before_course_deleted(course_content_deleted $hook) {
        global $DB;

        $context = \context_course::instance($hook->course->id);

        $records = $DB->get_records('tool_mediatime', ['contextid' => $context->id]);
        foreach ($records as $record) {
            $classname = "\\mediatimesrc_$source\\manager";
            $resource = new $classname($record);
            $resource->delete_resource();
        }
    }
}
