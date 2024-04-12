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
 * Manage Streamio source files
 *
 * @package    mediatimesrc_streamio
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_streamio;

use context_system;
use core_tag_tag;
use moodle_exception;
use moodle_url;
use moodleform;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Manage Streamio source files
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager implements renderable, templatable {
    /** @var $api Streamio API instance */
    protected ?api $api = null;

    /** @var $content Cached Streamio video record */
    protected ?stdClass $content = null;

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

        $this->api = new api();

        $this->record = $record;

        $upload = optional_param('upload', null, PARAM_INT);
        if (!empty($upload) && !optional_param('cancel', false, PARAM_BOOL)) {
            require_sesskey();
            require_capability('mediatimesrc/streamio:upload', context_system::instance());
            $video = $this->api->request("/videos", ['tags' => (string)$upload])[0];
            $this->api->request("/videos/$video->id", [
                'tags' => implode(array_diff($video->tags, ["$upload", "mediatimeupload"])),
            ], 'PUT');
            $video = $this->api->request("/videos/$video->id");
            $video->name = optional_param('name', '', PARAM_ALPHANUM);
            $id = $DB->insert_record('tool_mediatime', [
                'content' => json_encode($video),
                'source' => 'streamio',
                'usermodified' => $USER->id,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
            if ($tags = optional_param('tags', '', PARAM_TEXT)) {
                $context = context_system::instance();
                core_tag_tag::set_item_tags(
                    'tool_mediatime',
                    'tool_meidatime',
                    $id,
                    $context,
                    json_decode($tags)
                );
            }
            $event = \mediatimesrc_streamio\event\resource_created::create([
                'contextid' => SYSCONTEXTID,
                'objectid' => $id,
            ]);
            $event->trigger();
            $redirect = new moodle_url('/admin/tool/mediatime/index.php', ['id' => $id]);
            redirect($redirect);
        }

        if ($delete = optional_param('delete', null, PARAM_INT)) {
            $this->form = new form\delete_resource(null, (array)$this->record);
            $this->form->set_data(['delete' => $delete]);
        } else {
            $this->form = new form\edit_resource();
        }

        if ($record) {
            $this->content = json_decode($record->content);
        }

        if ($edit = optional_param('edit', null, PARAM_INT)) {
            $this->content->tags = core_tag_tag::get_item_tags_array('tool_mediatime', 'tool_mediatime', $edit);

            $this->form->set_data([
                'edit' => $edit,
            ] + (array)$this->content);
        }
        if ($this->form->is_cancelled()) {
            $redirect = new moodle_url('/admin/tool/mediatime/index.php');
            redirect($redirect);
        } else if (
            optional_param('delete', null, PARAM_INT)
            && ($data = $this->form->get_data())
        ) {
            $this->delete_resource($data->action == 2);

            $redirect = new moodle_url('/admin/tool/mediatime/index.php');
            redirect($redirect);
        } else if (($data = $this->form->get_data()) && empty($data->newfile)) {
            require_sesskey();
            $data->timemodified = time();
            $data->usermodified = $USER->id;

            if (empty($data->edit)) {
                $video = $this->api->request("/videos/$data->file");
                $video->name = $data->name;
                $data->content = json_encode($video);
                $data->timecreated = $data->timemodified;
                $data->edit = $DB->insert_record('tool_mediatime', $data);
                $event = \mediatimesrc_streamio\event\resource_created::create([
                    'contextid' => SYSCONTEXTID,
                    'objectid' => $data->edit,
                ]);
                $event->trigger();
            } else {
                $data->id = $data->edit;
                if (has_capability('mediatimesrc/streamio:upload', context_system::instance())) {
                    $this->api->request("/videos/" . $this->content->id, array_intersect_key((array)$data, [
                        'description' => true,
                        'title' => true,
                    ]), 'PUT');
                }
                $video = $this->api->request("/videos/" . $this->content->id);
                $video->name = $data->name;
                $data->content = json_encode($video);
                $DB->update_record('tool_mediatime', $data);

                $event = \mediatimesrc_streamio\event\resource_updated::create([
                    'contextid' => SYSCONTEXTID,
                    'objectid' => $this->record->id,
                ]);
                $event->trigger();
            }

            $this->save_file($data->edit, $video);

            $context = context_system::instance();
            core_tag_tag::set_item_tags(
                'tool_mediatime',
                'tool_mediatime',
                $data->edit,
                $context,
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
        } else if (($data = $this->form->get_data()) && !empty($data->newfile)) {
            require_sesskey();
            require_capability('mediatimesrc/streamio:upload', context_system::instance());

            $data->upload = file_get_unused_draft_itemid();
            $data->sesskey = sesskey();

            $data->token = $this->api->create_token([
                'tags' => "mediatimeupload,$data->upload",
            ] + array_intersect_key((array)$data, [
                'description' => true,
                'title' => true,
            ]))->token;
            $data->tags = json_encode($data->tags);

            return [
                'form' => $output->render_from_template('mediatimesrc_streamio/file_upload', $data),
            ];
        }
        return [
            'form' => $this->form->render(),
        ];
    }

    /**
     * Cache m3u8 file as moodle file
     *
     * @param int $id
     * @param stdClass $video
     */
    protected function save_file($id, $video) {
        // First delete stored files for content.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            SYSCONTEXTID,
            'tool_mediatime',
            'm3u8',
            $id,
            'id DESC', false
        );
        foreach ($files as $file) {
            $file->delete();
        }
        if ($video->transcodings) {

            $fileinfo = [
                'contextid' => SYSCONTEXTID,
                'component' => 'tool_mediatime',
                'itemid' => $id,
                'filearea' => 'm3u8',
                'filename' => 'video.m3u8',
                'filepath' => '/',
            ];

            $ch = curl_init("https://streamio.com/api/v1/videos/$video->id/public_show.m3u8");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $fs->create_file_from_string(
                $fileinfo,
                curl_exec($ch)
            );

            curl_close($ch);
        }
    }

    /**
     * Delete resource
     *
     * @param bool $removestreamiofile Whether to delete server resource at Streamio
     */
    public function delete_resource(bool $removestreamiofile = false) {
        global $DB;

        if ($removestreamiofile) {
            $id = $this->content->id;
            $this->api->request("/videos/$id", [], 'DELETE');
        }

        $DB->delete_records('tool_mediatime', [
            'id' => $this->record->id,
        ]);

        $event = \mediatimesrc_streamio\event\resource_deleted::create([
            'contextid' => SYSCONTEXTID,
            'objectid' => $this->record->id,
        ]);
        $event->trigger();
    }
}
