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

defined('MOODLE_INTERNAL') || die();

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

        $mform->addElement('hidden', 'parent_folder_uri');
        $mform->setType('parent_folder_uri', PARAM_RAW);
        $mform->setDefault('parent_folder_uri', '');

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
        $mform->hideIf('videofile', 'newfile', 'neq', 1);
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
        if ($record = $DB->get_record('tool_mediatime', ['id' => $id])) {
            $this->context = \context::instance_by_id($record->contextid);
        } else {
            $this->context = \context::instance_by_id($mform->getElementValue('contextid'));
        }
        $this->selected_group();
        $systemcontext = context_system::instance();
        if ($record) {
            $resource = new media_resource($record);
            $content = json_decode($record->content);
            $video = new video($content);
            $mform->insertElementBefore(
                $mform->createElement('html', $OUTPUT->render_from_template(
                    'mediatimesrc_vimeo/video',
                    $video->export_for_template($OUTPUT) + ['classes' => 'col-md-9 col-lg-8 col-xl-6']
                )),
                'name'
            );
            $mform->removeElement('vimeo_url');
            $mform->insertElementBefore(
                $mform->createElement('static', 'vimeo_url', get_string('vimeolink', 'mediatimesrc_vimeo'), $content->link ?? ''),
                'filesource'
            );
            $mform->removeElement('filesource');
            $mform->setDefault('groupid', $record->groupid);
        } else {
            if (has_capability('mediatimesrc/vimeo:viewall', $systemcontext)) {
                try {
                    $options = [null => ''];
                    $api = new api();
                    $videos = $api->request('/me/videos')['body']['data'];
                    foreach ($videos as $video) {
                        $options[$video['uri']] = $video['name'];
                    }

                    $mform->insertElementBefore(
                        $mform->createElement('autocomplete', 'file', '', $options, [
                            'ajax' => 'mediatimesrc_vimeo/video_datasource',
                            'tags' => true,
                        ]),
                        'description'
                    );
                    $mform->hideIf('file', 'newfile', 'neq', 0);
                    $mform->setDefault('newfile', 1);
                    $mform->setDefault('file', []);
                    $mform->setType('file', PARAM_URL);
                } catch (\moodle_exception $e) {
                    $api = null;
                    $mform->insertElementBefore(
                        $mform->createElement('hidden', 'newfile', 2),
                        'description'
                    );
                    $mform->removeElement('filesource');
                    return;
                }
            }

            if (
                has_capability('mediatimesrc/vimeo:upload', context_system::instance())
                || (($contextid = $mform->getElementValue('contextid')) && $DB->get_records('tool_mediatime', [
                    'source' => 'folder',
                    'contextid' => $contextid,
                ]) && has_capability('mediatimesrc/folder:use', \context::instance_by_id($contextid)))
            ) {
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
                if (!has_capability('mediatimesrc/vimeo:upload', context_system::instance())) {
                    if (
                        empty(optional_param('parent_folder_uri', '', PARAM_RAW))
                        && class_exists('\\mediatimesrc_folder\\manager')
                        && ($contextid = $mform->getElementValue('contextid'))
                        && ($context = \context::instance_by_id($contextid))
                        && $default = \mediatimesrc_folder\manager::default_folder($context, true)
                    ) {
                        $mform->setDefault(
                            'parent_folder_uri',
                            (new \mediatimesrc_folder\manager($default))->get_uri()
                        );
                    }
                    $mform->insertElementBefore(
                        $mform->createElement('hidden', 'newfile', 1),
                        'description'
                    );
                    $mform->removeElement('filesource');
                }
            }
        }
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

        if (
            empty($data['edit'])
            && empty($data['newfile'])
            && empty(preg_match('~^https://vimeo.com/\\d+$|^/videos/\\d+$~', $data['file']))
        ) {
            $errors['file'] = get_string('enteruri', 'mediatimesrc_vimeo');
        }

        return $errors;
    }
}
