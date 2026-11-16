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
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_ubicast\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

use mediatimesrc_ubicast\api;
use mediatimesrc_ubicast\output\media_resource;
use moodle_exception;

/**
 * Media Time media edit form
 *
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_resource extends \tool_mediatime\form\edit_resource {
    /**
     * Definition
     */
    public function definition() {
        global $CFG, $COURSE, $OUTPUT;

        $mform = $this->_form;

        parent::definition();

        $mform->setDefault('source', 'ubicast');

        if (
            $this->record
            && ($content = json_decode($this->record->content ?? '{}'))
            && !empty($content->oid)
        ) {
            $resource = new media_resource($this->record);
            $videourl = $resource->video_url($OUTPUT);
            $mform->insertElementBefore(
                $mform->createElement(
                    'html',
                    $OUTPUT->render_from_template('mediatimesrc_ubicast/video', [
                        'classes' => 'col-md-9 col-lg-8 col-xl-6',
                        'elementid' => 'video-' . uniqid(),
                        'poster' => $resource->image_url($OUTPUT),
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
                        'videourl' => $videourl,
                    ])
                ),
                'name'
            );
            $mform->addElement('static', 'mediaid', get_string('mediaid', 'mediatimesrc_ubicast'), $content->oid);
            $mform->addElement('static', 'mediatitle', get_string('title', 'tool_mediatime'), s($resource->get_title()));
        } else {
            $canupload = has_capability('mediatimesrc/ubicast:upload', $this->context);
            if ($canupload) {
                $filesource = [
                    $mform->createElement('radio', 'newfile', '', get_string('selectexistingfile', 'mediatimesrc_ubicast'), 0),
                    $mform->createElement('radio', 'newfile', '', get_string('uploadnewfile', 'mediatimesrc_ubicast'), 1),
                ];
                $mform->addGroup($filesource, 'filesource', get_string('filesource', 'mediatimesrc_ubicast'), [' '], false);
                $mform->addHelpButton('filesource', 'filesource', 'mediatimesrc_ubicast');
            } else {
                $mform->addElement('hidden', 'newfile');
            }
            $mform->setType('newfile', PARAM_INT);
            $mform->setDefault('newfile', 0);

            $mform->addElement('autocomplete', 'file', get_string('file', 'mediatimesrc_ubicast'), [], [
                'ajax' => 'mediatimesrc_ubicast/video_datasource',
            ]);
            $mform->setType('file', PARAM_ALPHANUM);
            $mform->addHelpButton('file', 'file', 'mediatimesrc_ubicast');
            $mform->hideIf('file', 'newfile', 'eq', 1);

            if ($canupload) {
                $mform->addElement('filepicker', 'videofile', get_string('videofile', 'mediatimesrc_ubicast'), null, [
                    'maxbytes' => get_max_upload_file_size($CFG->maxbytes, $COURSE->maxbytes),
                    'accepted_types' => ['video'],
                ]);
                $mform->addHelpButton('videofile', 'videofile', 'mediatimesrc_ubicast');
                $mform->hideIf('videofile', 'newfile', 'neq', 1);

                $mform->addElement('text', 'title', get_string('title', 'tool_mediatime'));
                $mform->setType('title', PARAM_TEXT);
                $mform->addHelpButton('title', 'title', 'tool_mediatime');
                $mform->hideIf('title', 'newfile', 'neq', 1);

                $mform->addElement('textarea', 'description', get_string('description', 'mediatimesrc_ubicast'));
                $mform->setType('description', PARAM_TEXT);
                $mform->addHelpButton('description', 'description', 'mediatimesrc_ubicast');
                $mform->hideIf('description', 'newfile', 'neq', 1);
            }
        }

        $this->tag_elements();

        $this->selected_group();
        $mform->setType('groupid', PARAM_INT);

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
        $errors = parent::validation($data, $files);

        if (!empty($data['edit'])) {
            return $errors;
        }

        if (!empty($data['newfile'])) {
            if (!has_capability('mediatimesrc/ubicast:upload', $this->context)) {
                $errors['filesource'] = get_string('nopermissions', 'error', get_string('ubicast:upload', 'mediatimesrc_ubicast'));
            } else if (empty($this->get_draft_files('videofile'))) {
                $errors['videofile'] = get_string('required');
            }
        } else if (empty($data['file'])) {
            $errors['file'] = get_string('required');
        } else {
            try {
                $api = new api();
                $media = $api->get_media($data['file']);
                if (empty($media->validated)) {
                    $errors['file'] = get_string('notpublished', 'mediatimesrc_ubicast');
                } else if (!$api->is_public($data['file'])) {
                    $errors['file'] = get_string('notpublic', 'mediatimesrc_ubicast');
                }
            } catch (moodle_exception $e) {
                $errors['file'] = $e->getMessage();
            }
        }

        return $errors;
    }
}
