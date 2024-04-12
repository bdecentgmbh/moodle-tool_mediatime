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
 * Media Time delete resource form
 *
 * @package    mediatimesrc_videotime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_videotime\form;

use context_system;
use moodleform;
use mediatimesrc_videotime\api;
use mediatimesrc_videotime\output\media_resource;

/**
 * Media Time delete resource form
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
        $mform->setDefault('source', 'videotime');

        $mform->addElement('static', 'name', get_string('resourcename', 'tool_mediatime'), $this->_customdata['name']);
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('hidden', 'delete');
        $mform->setType('delete', PARAM_INT);

        $this->add_action_buttons(true, get_string('delete'));
    }
}
