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
 * @package    mediatimesrc_videotime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_videotime\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/resourcelib.php");

use moodleform;
use mediatimesrc_videotime\output\media_resource;

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
        $mform->setDefault('create', '');

        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);
        $mform->setDefault('source', 'videotime');

        $mform->addElement('text', 'name', get_string('resourcename', 'tool_mediatime'));
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'resourcename', 'tool_mediatime');

        $mform->addElement('text', 'title', get_string('title', 'tool_mediatime'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addHelpButton('title', 'title', 'tool_mediatime');

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('textarea', 'description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);

        $maxbytes = 200000000;
        $mform->addElement(
            'filemanager',
            'videofile',
            get_string('videofile', 'mediatimesrc_videotime'),
            null,
            [
                'accepted_types' => [
                    '.flac',
                    '.mp3',
                    '.mp4',
                    '.mov',
                    '.movie',
                    '.m3u',
                    '.m3u8',
                    '.m4a',
                    '.m4v',
                    '.mp4',
                    '.mpeg',
                    '.ogg',
                    '.oga',
                    '.ogv',
                    '.wav',
                    '.webm',
                    '.wma',
                    '.wmv',
                ],
                'subdirs' => 0,
                'maxbytes' => $maxbytes,
                'areamaxbytes' => $maxbytes,
                'maxfiles' => 1,
                'return_types' => FILE_INTERNAL,
            ]
        );
        $mform->addHelpButton('videofile', 'videofile', 'mediatimesrc_videotime');
        $mform->addRule('videofile', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'filemanager',
            'posterimage',
            get_string('posterimage', 'mediatimesrc_videotime'),
            null,
            [
                'subdirs' => 0,
                'maxbytes' => $maxbytes,
                'areamaxbytes' => $maxbytes,
                'maxfiles' => 1,
                'accepted_types' => ['image', 'jpg', 'jpeg'],
                'return_types' => FILE_INTERNAL,
            ]
        );
        $mform->addHelpButton('posterimage', 'posterimage', 'mediatimesrc_videotime');
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
        if ($record) {
            $resource = new media_resource($record);

            $videourl = $resource->video_url($OUTPUT);

            $content = [
                'poster' => $resource->image_url($OUTPUT),
                'elementid' => 'video-' . uniqid(),
                'instance' => json_encode([
                    'vimeo_url' => $videourl,
                    'controls' => true,
                    'responsive' => true,
                    'playsinline' => false,
                    'autoplay' => false,
                    'option_loop' => false,
                    'muted' => true,
                    'type' => resourcelib_guess_url_mimetype($videourl),
                ]),
            ];
            $mform->insertElementBefore(
                $mform->createElement(
                    'html',
                    $OUTPUT->render_from_template('mediatimesrc_videotime/video', $content)
                ),
                'name'
            );
        }
    }
}
