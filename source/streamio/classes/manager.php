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
    /** @var $content Cached Streamio video record */
    protected ?stdClass $content = null;

    /** @var $record Media time record for resource */
    protected ?stdClass $record = null;

    /** @var $form Editing form */
    protected ?moodleform $form = null;

    /** @var $username Streamio account password */
    protected ?string $password = null;

    /** @var $username Streamio account username */
    protected ?string $username = null;

    /**
     * Constructor
     *
     * @param ?stdClass $record Media time record for resource
     */
    public function __construct($record = null) {
        global $DB, $USER;

        if (!$username = get_config('mediatimesrc_streamio', 'username')) {
            throw new moodle_exception('credentialsnotconfigured');
        }
        if (!$password = get_config('mediatimesrc_streamio', 'password')) {
            throw new moodle_exception('credentialsnotconfigured');
        }
        $this->username = $username;
        $this->password = $password;

        $this->record = $record;

        $upload = optional_param('upload', null, PARAM_INT);
        if (!empty($upload)) {
            $video = $this->request("/videos", ['tags' => (string)$upload])[0];
            $id = $DB->insert_record('tool_mediatime', [
                'content' => json_encode($video),
                'source' => 'streamio',
                'usercreated' => $USER->id,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
            $this->request("/videos/$video->id", ['tags' => ''], 'PUT');
            $redirect = new moodle_url('/admin/tool/mediatime/index.php', ['id' => $id]);
            redirect($redirect);
        }

        $this->form = new form\edit_resource();
        if ($record) {
            $this->content = json_decode($record->content);
        }

        if ($edit = optional_param('edit', null, PARAM_INT)) {
            $this->form->set_data([
                'edit' => $edit,
            ] + (array)$this->content);
        }
        if (($data = $this->form->get_data()) && empty($data->newfile)) {
            $data->timemodified = time();
            $data->usermodified = $USER->id;

            if (empty($data->edit)) {
                $video = $this->request("/videos/$data->file");
                $data->content = json_encode($video);
                $data->timecreated = $data->timemodified;
                $data->edit = $DB->insert_record('tool_mediatime', $data);
            } else {
                $data->id = $data->edit;
                $this->request("/videos/" . $this->content->id, array_intersect_key((array)$data, [
                    'description' => true,
                    'title' => true,
                ]), 'PUT');
                $video = $this->request("/videos/" . $this->content->id);
                $data->content = json_encode($video);
                $DB->update_record('tool_mediatime', $data);
            }

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
            $resource = new output\resource($this->record);
            return [
                'resource' => $output->render($resource),
            ];
        } else if (($data = $this->form->get_data()) && !empty($data->newfile)) {
            $data->upload = file_get_unused_draft_itemid();

            $data->token = $this->create_token([
                'tags' => "$data->upload",
            ])->token;

            return [
                'form' => $output->render_from_template('mediatimesrc_streamio/file_upload', $data),
            ];
        }
        return [
            'form' => $this->form->render(),
        ];
    }

    /**
     * Submit request to Streamio
     *
     * @param string $endpoint
     * @param ?array $params Options for request
     * @param string $method HTTP method to use
     * @return mixed
     */
    public function request($endpoint, $params = [], $method = 'GET') {
        $opts = [
            'http' => [
                "header" => "Authorization: Basic " . base64_encode("$this->username:$this->password")
                . "\nAccept: application/json\n"
                . "Content-type: application/json\n",
                'method' => $method,
                "protocol_version" => 1.1,
                'content' => json_encode($params),
            ],
        ];

        $context = stream_context_create($opts);

        $response = file_get_contents('https://streamio.com/api/v1' . $endpoint, false, $context);

        if ($response === false) {
            throw new moodle_exception('streamiorequesterror');
        }

        return json_decode($response);
    }

    /**
     * Create upload token for Streamio
     *
     * @param ?array $params Fields to set for video
     * @return string
     */
    public function create_token(?array $params = []) {
        return $this->request('/videos/create_token', $params, 'POST');
    }
}
