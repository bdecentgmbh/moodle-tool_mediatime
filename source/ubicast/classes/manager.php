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
use mod_videotime\hook\dndupload_handle;
use mod_videotime\hook\dndupload_register;
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
                'title' => $this->content->title ?? '',
                'description' => $this->content->description ?? '',
            ]);
        }

        if ($this->form->is_cancelled()) {
            $redirect = new moodle_url('/admin/tool/mediatime/index.php', [
                'contextid' => optional_param('contextid', $this->context->id, PARAM_INT),
            ]);
            redirect($redirect);
        } else if (
            optional_param('delete', null, PARAM_INT)
            && ($data = $this->form->get_data())
        ) {
            require_sesskey();
            $this->delete_resource(($data->action ?? 1) == 2);

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
                    $oid = $data->file;
                } else {
                    require_capability('mediatimesrc/ubicast:upload', $this->context);
                    $filename = $this->form->get_new_filename('videofile');
                    $fullpath = make_request_directory() . '/' . api::upload_filename($filename);
                    $this->form->save_file('videofile', $fullpath);
                    $oid = $api->add_media($fullpath, $filename, [
                        'title' => $data->title ?: $data->name,
                        'description' => text_to_html($data->description ?? '', false, false, true),
                        'validated' => 'yes',
                    ])->oid;
                }
                $data->content = json_encode(media_resource::fetch_content($api, $oid, $data->name, (object)[
                    'uploaded' => !empty($data->newfile),
                ]));
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
                    $api = new api();
                    if (media_resource::can_change_remote($this->content, $this->context, 'can_edit')) {
                        // Only send changed fields, so Nudgis formatting is kept unless the description was edited.
                        $fields = [];
                        if ($data->title !== ($this->content->title ?? '')) {
                            $fields['title'] = $data->title;
                        }
                        if (trim($data->description) !== ($this->content->description ?? '')) {
                            $fields['description'] = text_to_html($data->description, false, false, true);
                        }
                        if ($fields) {
                            $api->edit_media($this->content->oid, $fields);
                        }
                    }
                    $media = media_resource::fetch_content($api, $this->content->oid, $data->name, $this->content);
                    $data->content = json_encode($media);
                } catch (moodle_exception $e) {
                    \core\notification::error($e->getMessage());
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
     * @param bool $removeremote Whether to delete the video on Nudgis too, if this Moodle uploaded it
     */
    public function delete_resource(bool $removeremote = false) {
        global $DB;

        if ($removeremote && media_resource::can_change_remote($this->content, $this->context, 'can_delete')) {
            (new api())->delete_media($this->content->oid);
        }

        self::delete_cached_files($this->record->contextid, $this->record->id);

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

        self::delete_cached_files($hook->get_context()->id, $record->id);
    }

    /**
     * Delete the thumbnail and subtitles kept for a resource
     *
     * @param int $contextid Context id the files are stored in
     * @param int $id Resource id
     */
    public static function delete_cached_files(int $contextid, int $id): void {
        $fs = get_file_storage();
        foreach (['texttrack', 'thumbnail'] as $filearea) {
            $fs->delete_area_files($contextid, 'mediatimesrc_ubicast', $filearea, $id);
        }
    }

    /**
     * Register drag and drop file upload handler
     *
     * @param dndupload_register $hook Hook
     */
    public static function dndupload_register(dndupload_register $hook): void {
        if (
            !get_config('mediatimesrc_ubicast', 'enabled')
            || !get_config('mediatimesrc_ubicast', 'enabledraganddrop')
            || !get_config('mediatimesrc_ubicast', 'channel')
        ) {
            return;
        }

        foreach (['avi', 'mkv', 'mov', 'mp4', 'ogv', 'webm'] as $extension) {
            $hook->register_handler($extension);
        }
    }

    /**
     * Upload a dropped video to Nudgis and create a Video Time activity for it
     *
     * @param dndupload_handle $hook Hook
     */
    public static function dndupload_handle(dndupload_handle $hook): void {
        global $CFG, $DB, $USER;

        $context = $hook->get_context()->get_course_context();
        if (
            $hook->get_instanceid()
            || !get_config('mediatimesrc_ubicast', 'enabled')
            || !get_config('mediatimesrc_ubicast', 'enabledraganddrop')
            || !has_capability('mediatimesrc/ubicast:upload', $context)
        ) {
            return;
        }

        $api = new api();
        $channel = get_config('mediatimesrc_ubicast', 'channel');
        if (!$channel || !$api->is_public($channel)) {
            throw new moodle_exception('channelnotpublic', 'mediatimesrc_ubicast');
        }

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $hook->get_draftitemid(), 'id', false);
        if (!$file = reset($files)) {
            return;
        }
        $fullpath = make_request_directory() . '/' . api::upload_filename($file->get_filename());
        $file->copy_content_to($fullpath);
        $oid = $api->add_media($fullpath, $file->get_filename(), [
            'title' => $hook->get_displayname(),
            'validated' => 'yes',
        ])->oid;

        $clock = \core\di::get(\core\clock::class);
        $data = (object)[
            'source' => 'ubicast',
            'name' => $hook->get_displayname(),
            'contextid' => $context->id,
            'groupid' => 0,
            'usermodified' => $USER->id,
            'timecreated' => $clock->time(),
            'timemodified' => $clock->time(),
        ];
        $data->content = json_encode(media_resource::fetch_content($api, $oid, $data->name, (object)['uploaded' => true]));
        $data->id = $DB->insert_record('tool_mediatime', $data);
        event\resource_created::create_from_record($data)->trigger();

        // Create a default videotime object to pass to videotime_add_instance()!
        $videotime = get_config('videotime');
        $videotime->intro = '';
        $videotime->introformat = FORMAT_HTML;
        $videotime->course = $hook->get_course()->id;
        $videotime->coursemodule = $hook->get_coursemodule();
        $videotime->grade = $CFG->gradepointdefault;
        $videotime->video_description = '';
        $videotime->video_description_format = FORMAT_HTML;
        $videotime->cmidnumber = '';
        $videotime->name = $hook->get_displayname();
        $videotime->reference = $hook->get_displayname();
        $videotime->mediatimeid = $data->id;

        $hook->set_instanceid(videotime_add_instance($videotime, null));
    }
}
