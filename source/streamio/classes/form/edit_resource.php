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
 * Media Time media edit form
 *
 * @package    mediatimesrc_streamio
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_streamio\form;

use moodleform;
use mediatimesrc_streamio\output\media_resource;

/**
 * Media Time media edit form
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_resource extends moodleform {

    /**
     * Definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);
        $mform->setDefault('source', 'streamio');

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('text', 'title', get_string('title', 'mediatimesrc_streamio'));
        $mform->setType('title', PARAM_TEXT);

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('text', 'description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);

        $this->add_action_buttons();
    }

    /**
     * Display resource or add file fields
     */
    public function definition_after_data() {
        global $DB, $OUTPUT;

        $mform =& $this->_form;

        $id = $mform->getElementValue('edit');
        $record = $DB->get_record('tool_mediatime', ['id' => $id]);
        if ($record) {
            $resource = new media_resource($record);
            $mform->insertElementBefore(
                $mform->createElement('html', format_text($OUTPUT->render($resource), FORMAT_HTML, [
                    'context' => \context_system::instance(),
                ])),
                'name'
            );
        } else {
            $options = [];
            $api = new \mediatimesrc_streamio\manager(null);
            $videos = $api->request('/videos');
            foreach ($videos as $video) {
                $options[$video->id] = $video->title;
            }

            $mform->insertElementBefore(
                $mform->createElement('select', 'file', get_string('file'), $options),
                'name'
            );
            $mform->insertElementBefore(
                $mform->createElement('advcheckbox', 'newfile', get_string('file')),
                'name'
            );
        }
    }
}
