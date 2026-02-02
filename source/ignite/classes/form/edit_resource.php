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

require_once("$CFG->libdir/formslib.php");

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

        $mform->addElement('text', 'title', get_string('title', 'tool_mediatime'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addHelpButton('title', 'title', 'tool_mediatime');

        $filesource = [
            $mform->createElement('radio', 'newfile', '', get_string('uploadnewfile', 'mediatimesrc_ignite'), 1, []),
            $mform->createElement('radio', 'newfile', '', get_string('selectexistingfile', 'mediatimesrc_ignite'), 0, []),
        ];
        $mform->addGroup($filesource, 'filesource', get_string('filesource', 'mediatimesrc_ignite'), [' '], false);
        $mform->setType('newfile', PARAM_INT);
        $mform->addHelpButton('filesource', 'filesource', 'mediatimesrc_ignite');

        $mform->addElement('textarea', 'description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);
        $mform->disabledIf('description', 'newfile', 0);
        $mform->disabledIf('title', 'newfile', 0);
        $mform->disabledIf('subtitlelanguage', 'newfile', 0);

        $context = context_system::instance();
        if (
            $this->record
            && ($content = json_decode($this->record->content ?? '{}'))
            && !empty($content->id)
        ) {
            $igniteid = $content->id;
            $resource = new media_resource($this->record);
            $ignitetags = array_column($content->tags ?? [], 'title', 'id');

            $videourl = $resource->video_url($OUTPUT);
            $content = [
                'classes' => 'col-md-9 col-lg-8 col-xl-6',
                'elementid' => 'video-' . uniqid(),
                'poster' => $resource->image_url($OUTPUT),
                'texttracks' => $resource->texttracks(),
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
            $mform->setDefault('groupid', $this->record->groupid);
            $mform->removeElement('filesource');
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

            $mform->insertElementBefore(
                $mform->createElement('select', 'sync', get_string('synccontent', 'mediatimesrc_ignite'), [
                    get_string('localcontent', 'mediatimesrc_ignite'),
                    get_string('syncedcontent', 'mediatimesrc_ignite'),
                ]),
                'description'
            );
            $mform->addHelpButton('sync', 'synccontent', 'mediatimesrc_ignite');
            $mform->hideIf('sync', 'newfile', 'eq', 1);

            if (
                has_capability('mediatimesrc/ignite:upload', context_system::instance())
            ) {
                $maxbytes = 200000000;
                $mform->insertElementBefore(
                    $mform->createElement(
                        'filepicker',
                        'videofile',
                        get_string('videofile', 'mediatimesrc_ignite'),
                        null,
                        [
                            'maxbytes' => $maxbytes,
                            'accepted_types' => ['video'],
                        ]
                    ),
                    'description'
                );
                $mform->addHelpButton('videofile', 'videofile', 'mediatimesrc_ignite');
                $mform->hideIf('videofile', 'newfile', 'neq', 1);

                $languages = ['' => get_string('none')];
                foreach (\get_string_manager()->get_list_of_translations() as $key => $language) {
                    if (!empty(self::supported_code($key))) {
                        $languages[$key] = $language;
                    }
                }
                $mform->insertElementBefore(
                    $mform->createElement(
                        'select',
                        'subtitlelanguage',
                        get_string('subtitlelanguage', 'mediatimesrc_ignite'),
                        $languages
                    ),
                    'description'
                );
                $mform->addHelpButton('subtitlelanguage', 'subtitlelanguage', 'mediatimesrc_ignite');
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

        $mform->addElement('autocomplete', 'ignitetags', get_string('ignitetags', 'mediatimesrc_ignite'), $ignitetags ?? [], [
            'ajax' => 'mediatimesrc_ignite/tag_datasource',
            'multiple' => true,
        ]);

        if (!empty($this->record->sync) && !has_capability('mediatimesrc/ignite:upload', $this->context)) {
            $mform->hardFreeze('description');
            $mform->hardFreeze('title');
            $mform->hardFreeze('ignitetags');
        }

        $mform->setDefault('ignitetags', array_keys($ignitetags ?? []));
        $mform->hideIf('ignitetags', 'newfile', 0);
        $mform->hideIf('file', 'newfile', 'eq', 1);
        $mform->setDefault('newfile', 0);
        $mform->setDefault('file', []);
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

    /**
     * Formatted language code to supported Ignite form
     *
     * @param string $language Moodle language code
     * @return string Ignite code
     */
    public static function supported_code(string $language): string {
        $key = explode('_', $language)[0];

        return [
            'de' => 'de-DE',
            'en' => 'en-US',
            'fr' => 'fr-FR',
            'it' => 'it-IT',
            'pt' => 'pt-PT',
            'es' => 'es-ES',
        ][$key] ?? '';
    }
}
