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
 * Manage Vimeo source files
 *
 * @package    mediatimesrc_vimeo
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_vimeo;

use context;
use context_system;
use context_user;
use core_tag_tag;
use moodle_exception;
use moodle_url;
use moodleform;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Manage Vimeo source files
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager implements renderable, templatable {
    /** @var $api Vimeo API instance */
    protected ?api $api = null;

    /** @var $context Context */
    protected ?context $context = null;

    /** @var $content Cached Vimeo video record */
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

        if ($record) {
            $this->content = json_decode($record->content ?? '{}');
            $this->context = \context::instance_by_id($record->contextid);
        } else {
            $this->context = \context::instance_by_id(optional_param('contexid', SYSCONTEXTID, PARAM_INT));
        }

        if ($delete = optional_param('delete', null, PARAM_INT)) {
            $this->form = new form\delete_resource((new moodle_url('/admin/tool/mediatime', [
                'contextid' => optional_param('contextid', $this->record->contextid, PARAM_INT),
            ]))->out(), (array)$this->record, 'GET');
            $this->form->set_data(['delete' => $delete]);
        } else {
            $this->form = new form\edit_resource();
            $this->form->set_data(['contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT)]);
        }

        $data = [
            'create' => optional_param('create', '', PARAM_TEXT),
            'edit' => optional_param('edit', null, PARAM_INT),
        ];
        $maxbytes = get_config('mediatimesrc_videotime', 'maxbytes');
        if ($edit = optional_param('edit', null, PARAM_INT)) {
            $draftitemid = file_get_submitted_draft_itemid('videofile');
            file_prepare_draft_area(
                $draftitemid,
                $data->contextid,
                'mediatimesrc_vimeo',
                'videofile',
                $edit,
                [
                    'subdirs' => 0,
                    'maxbytes' => $maxbytes,
                    'maxfiles' => 1,
                ]
            );

            $this->content->tags = core_tag_tag::get_item_tags_array('tool_mediatime', 'tool_mediatime', $edit);

            // Check for updated values at Vimeo.
            if (!$this->form->is_submitted()) {
                $video = $this->api->request($this->content->uri ?? '')['body'];
                $data = [
                    'description' => $video['description'] ?? '',
                    'title' => $video['name'] ?? '',
                    'name' => $this->record->name,
                ] + $data;
            } else {
                $data += ['name' => $this->record->name, 'title' => $this->content->name ?? ''] + (array)$this->content;
            }
        }
        $this->form->set_data($data);
        if ($this->form->is_cancelled()) {
            $redirect = new moodle_url('/admin/tool/mediatime/index.php', [
                'contextid' => optional_param('contextid', SYSCONTEXTID, PARAM_INT),
            ]);
            redirect($redirect);
        } else if (
            optional_param('delete', null, PARAM_INT)
            && ($data = $this->form->get_data())
        ) {
            $this->delete_resource($data->action == 2);

            $redirect = new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $this->record->contextid]);
            redirect($redirect);
        } else if (($data = $this->form->get_data()) && (!empty($data->newfile) && $data->newfile == 1)) {
            require_sesskey();
            $data->timemodified = time();
            $data->usermodified = $USER->id;
            $data->timecreated = $data->timemodified;

            $fs = get_file_storage();
            foreach ($fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $data->videofile) as $file) {
                if (!$file->is_directory()) {
                    $data->id = $DB->insert_record('tool_mediatime', $data);
                    if (empty($data->title)) {
                        $data->title = $data->name;
                    }

                    file_save_draft_area_files(
                        $data->videofile,
                        $data->contextid,
                        'mediatimesrc_vimeo',
                        'videofile',
                        $data->id,
                        [
                            'subdirs' => 0,
                            'maxbytes' => $maxbytes,
                            'maxfiles' => 1,
                        ]
                    );
                    $url = moodle_url::make_pluginfile_url(
                        $data->contextid,
                        'mediatimesrc_vimeo',
                        'videofile',
                        $data->id,
                        '/' . $file->get_contenthash() . $file->get_filepath(),
                        $file->get_filename()
                    );

                    $video = $this->api->create_token([
                        'upload' => [
                            'approach' => 'pull',
                            'size' => $file->get_filesize(),
                            'link' => $url->out(),
                        ],
                    ]);

                    if (!empty($data->tags)) {
                        $context = \context::instance_by_id($data->contextid);
                        core_tag_tag::set_item_tags(
                            'tool_mediatime',
                            'tool_mediatime',
                            $data->id,
                            $context,
                            $data->tags
                        );
                    }

                    $video = $this->api->request($video['uri'], [
                        'name' => $data->title,
                        'description' => $data->description,
                    ], 'PATCH')['body'];
                    $data->content = json_encode($video);
                    $DB->update_record('tool_mediatime', $data);

                    $event = \mediatimesrc_vimeo\event\resource_created::create_from_record($data);
                    $event->trigger();
                    $redirect = new moodle_url('/admin/tool/mediatime/index.php', ['id' => $data->id]);
                    redirect($redirect);
                }
            }
        } else if (($data = $this->form->get_data()) && (empty($data->newfile) || $data->newfile != 1)) {
            require_sesskey();
            $data->timemodified = time();
            $data->usermodified = $USER->id;

            if (empty($data->edit)) {
                if (empty($data->newfile)) {
                    $video = $this->api->request("$data->file")['body'];
                } else {
                    $vimeoid = mod_videotime_get_vimeo_id_from_link($data->vimeo_url);
                    $video = $this->api->request("/videos/$vimeoid")['body'];
                }
                $data->content = json_encode($video);
                $data->timecreated = $data->timemodified;
                $data->id = $DB->insert_record('tool_mediatime', $data);

                $event = \mediatimesrc_vimeo\event\resource_created::create_from_record($data);
                $event->trigger();
                $data->edit = $data->id;
            } else {
                $data->id = $data->edit;
                $data->contextid = $this->record->contextid;
                $this->api->request($this->content->uri ?? '', array_intersect_key(['name' => $data->title] + (array)$data, [
                    'description' => true,
                    'name' => true,
                ]), 'PATCH');
                $video = $this->api->request($this->content->uri ?? '')['body'];
                $data->content = json_encode($video + [
                    'description' => $data->description,
                    'title' => $data->title,
                    'name' => $data->title,
                ]);
                $DB->update_record('tool_mediatime', $data);

                $event = \mediatimesrc_vimeo\event\resource_updated::create_from_record($this->record);
                $event->trigger();
            }

            $context = \context::instance_by_id($data->contextid);
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
        } else if (($data = $this->form->get_data()) && $this->form->is_submitted() && $data->newfile == 1) {
            require_sesskey();
            require_capability('mediatimesrc/vimeo:upload', context_system::instance());

            return [
                'form' => $output->render_from_template('mediatimesrc_vimeo/file_upload', [
                    'name' => json_encode($data->name),
                    'description' => json_encode($data->description),
                    'title' => json_encode($data->title),
                    'tags' => htmlspecialchars(json_encode($data->tags), ENT_COMPAT),
                ]),
            ];
        }
        return [
            'form' => $this->form->render(),
        ];
    }

    /**
     * Delete resource
     *
     * @param bool $removevimeofile Whether to delete server resource at Vimeo
     */
    public function delete_resource(bool $removevimeofile = false) {
        global $DB;

        if ($removevimeofile && !empty($this->content->uri)) {
            $uri = $this->content->uri;
            $this->api->request($uri, [], 'DELETE');
        }

        $DB->delete_records('tool_mediatime', [
            'id' => $this->record->id,
        ]);

        $event = \mediatimesrc_vimeo\event\resource_deleted::create_from_record($this->record);
        $event->trigger();
    }
}
