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

namespace mediatimesrc_ubicast;

use context;
use core_tag_tag;
use moodle_exception;
use moodle_url;
use moodleform;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use mediatimesrc_ubicast\output\media_resource;
use tool_mediatime\hook\after_resource_moved;

/**
 * Manage UbiCast Nudgis source files
 *
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager implements renderable, templatable {
    /** @var $content Cached Nudgis media record */
    protected ?stdClass $content = null;

    /** @var $context Context */
    protected ?context $context = null;

    /** @var $record Media time record for resource */
    protected ?stdClass $record = null;

    /** @var $form Editing form */
    protected ?moodleform $form = null;

    /**
     * Constructor
     *
     * @param ?stdClass $record Media time record for resource
     */
    public function __construct($record = null) {
        global $DB, $USER;

        $this->record = $record;

        if ($record) {
            $this->content = json_decode($record->content ?? '{}');
            $this->context = \context::instance_by_id($record->contextid);
        } else {
            $this->context = \context::instance_by_id(optional_param('contextid', SYSCONTEXTID, PARAM_INT));
        }

        if ($delete = optional_param('delete', null, PARAM_INT)) {
            $this->form = new form\delete_resource((new moodle_url('/admin/tool/mediatime', [
                'contextid' => optional_param('contextid', $this->record->contextid, PARAM_INT),
            ]))->out(), (array)$this->record, 'GET');
            require_capability('tool/mediatime:manage', $this->context);
            $this->form->set_data([
                'contextid' => $this->context->id,
                'delete' => $delete,
            ]);
        } else {
            $this->form = new form\edit_resource(null, ['record' => $this->record]);
            if (optional_param('edit', null, PARAM_INT)) {
                require_capability('tool/mediatime:manage', $this->context);
            }
            $this->form->set_data([
                'contextid' => $this->context->id,
            ]);
        }

        if ($edit = optional_param('edit', null, PARAM_INT)) {
            $this->form->set_data([
                'edit' => $edit,
                'name' => $this->record->name,
                'groupid' => $this->record->groupid,
                'tags' => core_tag_tag::get_item_tags_array('tool_mediatime', 'tool_mediatime', $edit),
            ]);
        }

        if ($this->form->is_cancelled()) {
            $redirect = new moodle_url('/admin/tool/mediatime/index.php', [
                'contextid' => optional_param('contextid', $this->context->id, PARAM_INT),
            ]);
            redirect($redirect);
        } else if (
            optional_param('delete', null, PARAM_INT)
            && $this->form->get_data()
        ) {
            require_sesskey();
            $this->delete_resource();

            $redirect = new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $this->context->id]);
            redirect($redirect);
        } else if (($data = $this->form->get_data())) {
            require_sesskey();
            $clock = \core\di::get(\core\clock::class);
            $data->timemodified = $clock->time();
            $data->usermodified = $USER->id;

            if (empty($data->edit)) {
                $api = new api();
                if (empty($data->newfile)) {
                    $media = $api->get_media($data->file);
                } else {
                    require_capability('mediatimesrc/ubicast:upload', $this->context);
                    $filename = $this->form->get_new_filename('videofile');
                    $fullpath = make_request_directory() . '/' . api::upload_filename($filename);
                    $this->form->save_file('videofile', $fullpath);
                    $media = $api->add_media($fullpath, $filename, [
                        'title' => $data->title ?: $data->name,
                        'description' => text_to_html($data->description ?? '', false, false, true),
                        'validated' => 'yes',
                    ]);
                    if (!$api->is_public($media->oid)) {
                        \core\notification::warning(get_string('uploadnotpublic', 'mediatimesrc_ubicast'));
                    }
                }
                $data->content = json_encode(media_resource::content_from_media($media, $data->name));
                $data->timecreated = $data->timemodified;
                if (
                    $this->context instanceof \context_course
                    && ($course = get_course($this->context->instanceid))
                    && groups_get_course_groupmode($course)
                    && !groups_is_member($data->groupid)
                ) {
                    require_capability('moodle/site:accessallgroups', $this->context);
                }
                $data->groupid = $data->groupid ?? 0;
                $data->id = $DB->insert_record('tool_mediatime', $data);

                $event = event\resource_created::create_from_record($data);
                $event->trigger();
                $data->edit = $data->id;
            } else {
                $data->id = $data->edit;
                $data->contextid = $this->record->contextid;
                try {
                    $media = (new api())->get_media($this->content->oid);
                    $data->content = json_encode(media_resource::content_from_media($media, $data->name));
                } catch (moodle_exception $e) {
                    $this->content->name = $data->name;
                    $data->content = json_encode($this->content);
                }
                $DB->update_record('tool_mediatime', $data);

                $event = event\resource_updated::create_from_record($this->record);
                $event->trigger();
            }

            core_tag_tag::set_item_tags(
                'tool_mediatime',
                'tool_mediatime',
                $data->edit,
                \context::instance_by_id($data->contextid),
                $data->tags
            );

            $redirect = new moodle_url('/admin/tool/mediatime/index.php', ['id' => $data->edit]);
            redirect($redirect);
        }
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        if (optional_param('id', null, PARAM_INT)) {
            $resource = new media_resource($this->record);
            return [
                'resource' => $output->render($resource),
            ];
        }

        return [
            'form' => $this->form->render(),
        ];
    }

    /**
     * Delete resource
     *
     * The media is kept on Nudgis.
     */
    public function delete_resource() {
        global $DB;

        get_file_storage()->delete_area_files($this->record->contextid, 'mediatimesrc_ubicast', 'thumbnail', $this->record->id);

        $DB->delete_records('tool_mediatime', [
            'id' => $this->record->id,
        ]);

        $event = event\resource_deleted::create_from_record($this->record);
        $event->trigger();
    }

    /**
     * Remove the cached thumbnail from the old context when a resource is moved
     *
     * @param after_resource_moved $hook Hook
     */
    public static function after_resource_moved(after_resource_moved $hook): void {
        $record = $hook->get_record();
        if ($record->source != 'ubicast') {
            return;
        }

        get_file_storage()->delete_area_files($hook->get_context()->id, 'mediatimesrc_ubicast', 'thumbnail', $record->id);
    }
}
