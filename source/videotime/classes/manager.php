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
 * Manage Video Time resource files
 *
 * @package    mediatimesrc_videotime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_videotime;

use context;
use core_tag_tag;
use moodle_exception;
use moodle_url;
use moodleform;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Manage Video Time resource files
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager implements renderable, templatable {
    /** @var $content Cached content object */
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
            $this->content = json_decode($record->content);
            $this->context = \context::instance_by_id($record->contextid);
        } else {
            $this->context = \context::instance_by_id(optional_param('contextid', SYSCONTEXTID, PARAM_INT));
        }

        if ($delete = optional_param('delete', null, PARAM_INT)) {
            $this->form = new form\delete_resource((new moodle_url('/admin/tool/mediatime', [
                'contextid' => optional_param('contextid', $this->record->id, PARAM_INT),
            ]))->out(), (array)$this->record, 'GET');
            $this->form->set_data(['delete' => $delete]);
        } else {
            $this->form = new form\edit_resource();
            $this->form->set_data(['contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT)]);
        }

        $maxbytes = get_config('mediatimesrc_videotime', 'maxbytes');
        if ($edit = optional_param('edit', null, PARAM_INT)) {
            $draftitemid = file_get_submitted_draft_itemid('videofile');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mediatimesrc_videotime',
                'videofile',
                $edit,
                [
                    'subdirs' => 0,
                    'maxbytes' => $maxbytes,
                    'maxfiles' => 1,
                ]
            );
            $this->content->videofile = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('posterimage');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mediatimesrc_videotime',
                'posterimage',
                $edit,
                [
                    'subdirs' => 0,
                    'maxbytes' => $maxbytes,
                    'maxfiles' => 1,
                ]
            );
            $this->content->posterimage = $draftitemid;

            $this->content->tags = core_tag_tag::get_item_tags_array('tool_mediatime', 'tool_mediatime', $edit);

            $this->form->set_data([
                'edit' => $edit,
            ] + (array)$this->content);
        }
        if ($this->form->is_cancelled()) {
            $redirect = new moodle_url('/admin/tool/mediatime/index.php', [
                'contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT),
            ]);
            redirect($redirect);
        } else if (
            optional_param('delete', null, PARAM_INT)
            && ($data = $this->form->get_data())
        ) {
            require_sesskey();
            $this->delete_resource();

            $redirect = new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $this->record->contextid]);
            redirect($redirect);
        } else if ($data = $this->form->get_data()) {
            require_sesskey();
            $data->timemodified = time();
            $data->usermodified = $USER->id;

            if (empty($data->edit)) {
                $data->timecreated = $data->timemodified;
                $data->content = json_encode($data);
                $data->edit = $DB->insert_record('tool_mediatime', $data);
                $event = \mediatimesrc_videotime\event\resource_created::create([
                    'contextid' => SYSCONTEXTID,
                    'objectid' => $data->edit,
                ]);
                $event->trigger();
            } else {
                $data->id = $data->edit;
                $data->content = json_encode($data);
                $DB->update_record('tool_mediatime', $data);

                $event = event\resource_updated::create([
                    'contextid' => SYSCONTEXTID,
                    'objectid' => $this->record->id,
                ]);
                $event->trigger();
            }

            file_save_draft_area_files(
                $data->videofile,
                $this->context->id,
                'mediatimesrc_videotime',
                'videofile',
                $data->edit,
                [
                    'subdirs' => 0,
                    'maxbytes' => $maxbytes,
                    'maxfiles' => 1,
                ]
            );

            file_save_draft_area_files(
                $data->posterimage,
                $this->context->id,
                'mediatimesrc_videotime',
                'posterimage',
                $data->edit,
                [
                    'subdirs' => 0,
                    'maxbytes' => $maxbytes,
                    'maxfiles' => 1,
                ]
            );

            core_tag_tag::set_item_tags(
                'tool_mediatime',
                'tool_mediatime',
                $data->edit,
                $this->context,
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
        global $DB;

        if (optional_param('id', null, PARAM_INT)) {
            $resource = new output\media_resource($this->record);
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
     */
    public function delete_resource() {
        global $DB;

        $DB->delete_records('tool_mediatime', [
            'id' => $this->record->id,
        ]);

        $event = \mediatimesrc_videotime\event\resource_deleted::create([
            'contextid' => SYSCONTEXTID,
            'objectid' => $this->record->id,
        ]);
        $event->trigger();
    }
}
