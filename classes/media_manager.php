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
    /** @var array $media List of media resources to display */
    protected $media;

    /** @var int $page Paging offset */
    protected int $page = 0;

    /** @var $page Search form */
    protected $search = null;

    /** @var ?stdClass $record Media Time resource record */
    protected $record;

    /**
     * Constructor
     *
     * @param string $source Source plugin type to add
     * @param stdClass|null $record Media Time resource record
     * @param int $page Paging offset
     */
    public function __construct(string $source, ?stdClass $record = null, int $page = 0) {
        $this->page = $page;

        $this->record = $record;

        require_capability('tool/mediatime:view', context_system::instance());
        if ($record) {
            $source = $record->source;
            $record->content = json_decode($record->content);
        }

        $plugins = plugininfo\mediatimesrc::get_enabled_plugins();
        if (!empty($source) && !in_array($source, $plugins)) {
            throw new moodle_exception('invalidsource');
        }

        $this->media = [];
        if ($source) {
            $classname = "\\mediatimesrc_$source\\manager";
            if (class_exists($classname)) {
                $this->source = new $classname($this->record);
            }
        } else {
            $this->search = new form\search(null, null, 'get', '', [
                'class' => 'form-inline',
            ]);

            if ($this->search->is_cancelled()) {
                $url = new moodle_url('/admin/tool/mediatime/index.php');
                redirect($url);
            }

            $rs = self::search((array)$this->search->get_data());
            foreach ($rs as $media) {
                if (in_array($media->source, $plugins)) {
                    $media->content = json_decode($media->content);
                    $this->media[] = $media;
                }
            }

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
        if (!empty($this->source)) {
            $context = [
                'libraryhome' => (new moodle_url('/admin/tool/mediatime/index.php'))->out(),
                'resource' => $output->render($this->source),
            ];
            if (!empty($this->record) && empty(optional_param('edit', null, PARAM_INT))) {
                $resource = new output\media_resource($this->record);
                $context['tags'] = $resource->tags($output);
            }

            return $context;
        }

        $media = [];
        foreach ($this->media as $record) {
            $resource = new output\media_resource($record);
            $url = new moodle_url('/admin/tool/mediatime/index.php', ['id' => $record->id]);
            $removeurl = new moodle_url('/admin/tool/mediatime/index.php', ['delete' => $record->id]);
            $media[] = [
                'imageurl' => $resource->image_url($output),
                'tags' => $resource->tags($output),
                'url' => $url->out(),
                'content' => $record->content,
                'removeurl' => $removeurl->out(),
            ];
        }

        $plugins = \core_plugin_manager::instance()->get_installed_plugins('mediatimesrc');

        $options = [];
        foreach (plugininfo\mediatimesrc::get_enabled_plugins() as $plugin) {
            $options[$plugin] = get_string("pluginname", "mediatimesrc_$plugin");
        }
        if (!has_capability('tool/mediatime:manage', context_system::instance())) {
            $action = '';
        } else if (count($options) == 1) {
            $button = new single_button(new moodle_url('/admin/tool/mediatime/index.php', [
                'source' => array_keys($options)[0],
            ]), get_string('addnewcontent', 'tool_mediatime'));
            $action = $output->render($button);
        } else if (count($options)) {
            $select = new single_select(new moodle_url('/admin/tool/mediatime/index.php'), 'source', $options);
            $action = get_string('addnewcontent', 'tool_mediatime') . ' ' . $output->render($select);
        } else {
            $action = '';
        }
        return [
            'action' => $action,
            'media' => array_values($media),
            'search' => $this->search->render(),
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
        list($sql, $params) = $DB->get_in_or_equal($sources, SQL_PARAMS_NAMED);

        $sql = "source $sql";
        $order = 'timecreated DESC';

        // Filter for text query.
        if ($query = $filters['query'] ?? '') {
            $sql .= ' AND ' . $DB->sql_like('content', ':query', false);
            $params['query'] = "%$query%";

            $params['name'] = "%$query%";
            $order = 'CASE WHEN ' . $DB->sql_like('name', ':name', false) . ' THEN 1 ELSE 0 END DESC, timecreated DESC';
        }

        // Filter by tags.
        if ($tags = $filters['tags'] ?? '') {
            $tagcollid = core_tag_area::get_collection('tool_mediatime', 'tool_mediatime');
            $tags = core_tag_tag::get_by_name_bulk($tagcollid, $tags);
            $tags = array_column($tags, 'id');
            if (!empty($tags)) {
                list($tagsql, $tagparams) = $DB->get_in_or_equal($tags, SQL_PARAMS_NAMED, 'tagparams');
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
}
