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
 * @package    mediatimesrc_vimeo
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_vimeo\form;

use context_system;
use moodleform;
use mediatimesrc_vimeo\api;
use mediatimesrc_vimeo\output\media_resource;
use mediatimesrc_vimeo\output\video;

require_once("$CFG->dirroot/mod/videotime/lib.php");

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

        $mform->addElement('hidden', 'create');
        $mform->setType('create', PARAM_TEXT);

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', SYSCONTEXTID);

        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);
        $mform->setDefault('source', 'vimeo');

        $mform->addElement('text', 'name', get_string('resourcename', 'tool_mediatime'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'resourcename', 'tool_mediatime');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'title', get_string('title', 'tool_mediatime'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addHelpButton('title', 'title', 'tool_mediatime');

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $filesource = [
            $mform->createElement('radio', 'newfile', '', get_string('uploadnewfile', 'mediatimesrc_vimeo'), 1, []),
            $mform->createElement('radio', 'newfile', '', get_string('selectexistingfile', 'mediatimesrc_vimeo'), 0, []),
            $mform->createElement('radio', 'newfile', '', get_string('selecturl', 'mediatimesrc_vimeo'), 2, []),
        ];
        $mform->addGroup($filesource, 'filesource', get_string('videofile', 'mediatimesrc_vimeo'), [' '], false);
        $mform->setType('newfile', PARAM_INT);
        $mform->addHelpButton('filesource', 'videofile', 'mediatimesrc_vimeo');
        $mform->disabledIf('title', 'newfile', 'neq', 1);
        $mform->disabledIf('description', 'newfile', 'neq', 1);

        $mform->addElement('url', 'vimeo_url', get_string('vimeo_url', 'mod_videotime'));
        $mform->hideIf('vimeo_url', 'newfile', 'neq', 2);
        $mform->setType('vimeo_url', PARAM_URL);

        $mform->addElement('textarea', 'description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);

        $this->tag_elements();

        $this->add_action_buttons();
    }

    /**
     * Display resource or add file fields
     */
    public function definition_after_data() {
        global $DB, $OUTPUT, $USER;

        $mform =& $this->_form;

        $id = $mform->getElementValue('edit');
        $record = $DB->get_record('tool_mediatime', ['id' => $id]);
        $context = context_system::instance();
        if ($record) {
            $resource = new media_resource($record);
            $content = json_decode($record->content);
            $video = new video($content);
            $mform->insertElementBefore(
                $mform->createElement('html', $OUTPUT->render($video)),
                'name'
            );
            $mform->removeElement('vimeo_url');
            $mform->insertElementBefore(
                $mform->createElement('static', 'vimeo_url', get_string('vimeolink', 'mediatimesrc_vimeo'), $content->link),
                'filesource'
            );
            $mform->removeElement('filesource');
        } else {
            if (has_capability('mediatimesrc/vimeo:viewall', $context)) {
                $options = [null => ''];
                $api = new api();
                $videos = $api->request('/me/videos')['body']['data'];
                foreach ($videos as $video) {
                    $options[$video['uri']] = $video['name'];
                }

                $mform->insertElementBefore(
                    $mform->createElement('autocomplete', 'file', '', $options, [
                    ]),
                    'description'
                );
                $mform->hideIf('file', 'newfile', 'neq', 0);
                $mform->setDefault('newfile', 1);
                $mform->setDefault('file', []);

                if (has_capability('mediatimesrc/vimeo:upload', context_system::instance())) {
                    $maxbytes = 200000000;
                    $mform->insertElementBefore(
                        $mform->createElement(
                            'filemanager',
                            'videofile',
                            get_string('videofile', 'mediatimesrc_videotime'),
                            null,
                            [
                                'subdirs' => 0,
                                'maxbytes' => $maxbytes,
                                'areamaxbytes' => $maxbytes,
                                'maxfiles' => 1,
                                'accepted_types' => ['video'],
                                'return_types' => FILE_INTERNAL,
                            ]
                        ),
                        'description'
                    );
                    $mform->addHelpButton('videofile', 'videofile', 'mediatimesrc_videotime');
                    $mform->hideIf('videofile', 'newfile', 'neq', 1);
                }
            } else {
                $mform->removeElement('filesource');
                $mform->insertElementBefore(
                    $mform->createElement('hidden', 'newfile', 1),
                    'description'
                );
            }
        }
    }
}
