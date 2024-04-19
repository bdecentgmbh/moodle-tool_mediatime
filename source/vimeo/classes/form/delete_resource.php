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

/**
 * Media Time media edit form
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_resource extends \tool_mediatime\form\delete_resource {
    /**
     * Definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);
        $mform->setDefault('source', 'vimeo');

        $mform->addElement('static', 'name', get_string('resourcename', 'tool_mediatime'), $this->_customdata['name']);
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('hidden', 'delete');
        $mform->setType('delete', PARAM_INT);

        $action = [
            $mform->createElement('radio', 'action', '', get_string('keepvimeofiles', 'mediatimesrc_vimeo'), 1, []),
            $mform->createElement('radio', 'action', '', get_string('removevimeofiles', 'mediatimesrc_vimeo'), 2, []),
        ];
        $mform->addGroup($action, 'vimeofileaction', get_string('vimeofileaction', 'mediatimesrc_vimeo'), [' '], false);
        $mform->setType('action', PARAM_INT);
        $mform->setDefault('action', 2);
        $mform->addHelpButton('vimeofileaction', 'vimeofileaction', 'mediatimesrc_vimeo');

        $this->add_action_buttons(true, get_string('delete'));
    }
}
