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
 * @package    mediatimesrc_ignite
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_ignite\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/resourcelib.php");

use context_system;
use moodleform;
use mediatimesrc_ignite\api;
use mediatimesrc_ignite\output\media_resource;

/**
 * Media Time media edit form
 *
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_resource extends \tool_mediatime\form\edit_resource {
    /**
     * Definition
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;

        parent::definition();

        $filesource = [
            $mform->createElement('radio', 'newfile', '', get_string('uploadnewfile', 'mediatimesrc_ignite'), 1, []),
            $mform->createElement('radio', 'newfile', '', get_string('selectexistingfile', 'mediatimesrc_ignite'), 0, []),
        ];
        $mform->addGroup($filesource, 'filesource', get_string('videofile', 'mediatimesrc_ignite'), [' '], false);
        $mform->setType('newfile', PARAM_INT);
        $mform->addHelpButton('filesource', 'videofile', 'mediatimesrc_ignite');

        $mform->addElement('text', 'title', get_string('title', 'tool_mediatime'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addHelpButton('title', 'title', 'tool_mediatime');

        $mform->addElement('textarea', 'description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);
        $mform->disabledIf('description', 'newfile', 0);
        $mform->disabledIf('title', 'newfile', 0);

        $context = context_system::instance();
        if (
            $this->record
            && ($content = json_decode($this->record->content ?? '{}'))
            && !empty($content->id)
        ) {
            $igniteid = $content->id;
            $resource = new media_resource($this->record);

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
                    $OUTPUT->render_from_template('mediatimesrc_ignite/video', $content)
                ),
                'name'
            );
            $mform->insertElementBefore(
                $mform->createElement('static', 'vimeo_url', get_string('igniteid', 'mediatimesrc_ignite'), $igniteid),
                'filesource'
            );
            $mform->removeElement('filesource');
            $mform->setDefault('groupid', $this->record->groupid);
        } else {
            $options = [null => ''];

            $mform->insertElementBefore(
                $mform->createElement('autocomplete', 'file', '', $options, [
                    'ajax' => 'mediatimesrc_ignite/video_datasource',
                    'tags' => true,
                ]),
                'description'
            );
            $mform->hideIf('file', 'newfile', 'eq', 1);
            $mform->setDefault('newfile', 0);
            $mform->setDefault('file', []);

            if (
                has_capability('mediatimesrc/ignite:upload', context_system::instance())
            ) {
                $maxbytes = 200000000;
                $mform->insertElementBefore(
                    $mform->createElement(
                        'filepicker',
                        'videofile',
                        get_string('videofile', 'mediatimesrc_videotime'),
                        null,
                        [
                            'maxbytes' => $maxbytes,
                            'accepted_types' => ['video'],
                        ]
                    ),
                    'description'
                );
                $mform->addHelpButton('videofile', 'videofile', 'mediatimesrc_videotime');
                $mform->hideIf('videofile', 'newfile', 'neq', 1);
            } else {
                $mform->addRule('file', get_string('required'), 'required', null, 'client');
                $mform->insertElementBefore(
                    $mform->createElement('hidden', 'newfile', 1),
                    'description'
                );
                $mform->removeElement('filesource');
            }
        }
        $mform->setType('groupid', PARAM_INT);
        $this->tag_elements();

        $this->selected_group();

        $this->add_action_buttons();
    }
    /**
     * Validate data
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = [];

        if (empty($data['edit']) && empty($data['newfile']) && empty($data['file'])) {
            $errors['file'] = get_string('required');
        }

        return $errors;
    }
}
