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

use context_system;
use moodleform;
use mediatimesrc_streamio\api;
use mediatimesrc_streamio\output\media_resource;

/**
 * Media Time media edit form
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_resource extends \tool_mediatime\form\edit_resource {

    /**
     * Definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);
        $mform->setDefault('source', 'streamio');

        $mform->addElement('text', 'name', get_string('resourcename', 'tool_mediatime'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'resourcename', 'tool_mediatime');

        $mform->addElement('text', 'title', get_string('title', 'tool_mediatime'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addHelpButton('title', 'title', 'tool_mediatime');

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $filesource = [
            $mform->createElement('radio', 'newfile', '', get_string('uploadnewfile', 'mediatimesrc_streamio'), 1, []),
            $mform->createElement('radio', 'newfile', '', get_string('selectexistingfile', 'mediatimesrc_streamio'), 0, []),
        ];
        $mform->addGroup($filesource, 'filesource', get_string('videofile', 'mediatimesrc_streamio'), [' '], false);
        $mform->setType('newfile', PARAM_INT);
        $mform->addHelpButton('filesource', 'videofile', 'mediatimesrc_streamio');

        $mform->addElement('textarea', 'description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);

        $this->tag_elements();

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
        $context = context_system::instance();
        if ($record) {
            $resource = new media_resource($record);
            $mform->insertElementBefore(
                $mform->createElement('html', format_text(
                    $OUTPUT->render_from_template('mediatimesrc_streamio/video', json_decode($record->content)),
                    FORMAT_HTML,
                    ['context' => $context]
                )),
                'name'
            );
            $mform->removeElement('filesource');
        } else {
            if (has_capability('mediatimesrc/streamio:viewall', $context)) {
                $options = [];
                $api = new api();
                $videos = $api->request('/videos');
                foreach ($videos as $video) {
                    $options[$video->id] = $video->title;
                }

                $mform->insertElementBefore(
                    $mform->createElement('select', 'file', '', $options),
                    'description'
                );
                $mform->disabledIf('file', 'newfile', 'eq', 1);
                $mform->setDefault('newfile', 1);
            } else if (has_capability('mediatimesrc/streamio:upload', context_system::instance())) {
                $mform->removeElement('filesource');
                $mform->insertElementBefore(
                    $mform->createElement('hidden', 'newfile', 1),
                    'description'
                );
            }
        }
    }
}
