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
class delete_resource extends \tool_mediatime\form\delete_resource {
    /**
     * Definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);
        $mform->setDefault('source', 'streamio');

        $mform->addElement('static', 'name', get_string('resourcename', 'tool_mediatime'), $this->_customdata['name']);
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('hidden', 'delete');
        $mform->setType('delete', PARAM_INT);

        $action = [
            $mform->createElement('radio', 'action', '', get_string('keepstreamiofiles', 'mediatimesrc_streamio'), 1, []),
            $mform->createElement('radio', 'action', '', get_string('removestreamiofiles', 'mediatimesrc_streamio'), 2, []),
        ];
        $mform->addGroup($action, 'streamiofileaction', get_string('streamiofileaction', 'mediatimesrc_streamio'), [' '], false);
        $mform->setType('action', PARAM_INT);
        $mform->addHelpButton('streamiofileaction', 'streamiofileaction', 'mediatimesrc_streamio');
        $mform->setDefault('action', 1);

        $this->add_action_buttons(true, get_string('delete'));
    }
}
