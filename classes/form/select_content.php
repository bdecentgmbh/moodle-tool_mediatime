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

namespace tool_mediatime\form;

use core_form\dynamic_form;
use context;
use tool_mediatime\media_manager;
use tool_mediatime\output\media_resource;
use moodle_url;
use stored_file;

/**
 * Select content for Video Time
 *
 * @package     tool_mediatime
 * @copyright   2026 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_content extends dynamic_form {
    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $COURSE, $PAGE, $DB;

        $rs = media_manager::search();
        $options = ['' => ''];
        foreach ($rs as $record) {
            $options[$record->id] = $record->name;
        }
        $rs->close();

        $mform = $this->_form;

        $mform->registerNoSubmitButton('updatecontent');
        $mform->addElement('autocomplete', 'id', get_string('resourcename', 'tool_mediatime'), $options, [
            'ajax' => 'tool_mediatime/resource_datasource',
        ]);
        $mform->addElement('submit', 'updatecontent', 'updatecontent', ['style' => 'display: none;']);
    }

    /**
     * Returns context where this form is used
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return \context_system::instance();
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     */
    protected function check_access_for_dynamic_submission(): void {
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        global $DB;

        $data = $this->get_data();

        if (!empty($data->texttracks)) {
            $record = $DB->get_record('tool_mediatime', ['id' => $data->id]);
            $resource = new \tool_mediatime\output\media_resource($record);
            $data->texttracks = count($resource->texttracks());
        }

        return json_encode($data);
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB, $OUTPUT;

        $data = (object)$this->_ajaxformdata;
        $mform = $this->_form;

        $mform->setDefault('id', $data->id);

        if (empty($data->id)) {
            return;
        }

        $record = $DB->get_record(
            'tool_mediatime',
            [
                'id' => $data->id,
            ]
        );
        $resource = new media_resource($record);

        $mform->addElement('static', 'name', get_string('title', 'tool_mediatime'), $resource->get_title());
        $group = [
            $mform->createElement('checkbox', 'name', ' ', get_string('title', 'tool_mediatime')),
            $mform->createElement('checkbox', 'description', ' ', get_string('description')),
            $mform->createElement('checkbox', 'tags', ' ', get_string('tags', 'tag')),
            $mform->createElement('checkbox', 'url', ' ', get_string('url')),
        ];
        if (count($resource->texttracks())) {
            $group[] = $mform->createElement('checkbox', 'texttracks', ' ', get_string('texttracks', 'mod_videotime'));
        }
        $mform->addGroup($group, 'options', get_string('content'), [' '], false);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/admin/tool/mediatime/', [
            'id' => $this->_ajaxformdata['id'],
        ]);
    }

    /**
     * Validate form
     *
     * @param array $data Form data
     * @param array $files Files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['nosubmit'])) {
            $errors['id'] = get_string('required');
        }

        return $errors;
    }
}
