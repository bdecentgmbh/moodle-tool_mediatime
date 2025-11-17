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

use context_system;
use html_writer;
use moodleform;
use mediatimesrc_ignite\api;
use mediatimesrc_ignite\output\media_resource;

/**
 * Media Time media edit form
 *
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
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
        $mform->setDefault('source', 'ignite');

        $mform->addElement('html', html_writer::tag(
            'div',
            get_string('confirmdeleteresource', 'mediatimesrc_ignite'),
            ['class' => 'p-3']
        ));
        $mform->addElement('static', 'name', get_string('resourcename', 'tool_mediatime'), $this->_customdata['name']);
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('hidden', 'delete');
        $mform->setType('delete', PARAM_INT);

        if (has_capability('mediatimesrc/ignite:upload', context_system::instance())) {
            $action = [
                $mform->createElement('radio', 'action', '', get_string('keepignitefiles', 'mediatimesrc_ignite'), 1, []),
                $mform->createElement('radio', 'action', '', get_string('removeignitefiles', 'mediatimesrc_ignite'), 2, []),
            ];
            $mform->addGroup($action, 'ignitefileaction', get_string('ignitefileaction', 'mediatimesrc_ignite'), [' '], false);
            $mform->addHelpButton('ignitefileaction', 'ignitefileaction', 'mediatimesrc_ignite');
            $mform->setDefault('action', 1);
        } else {
            $mform->addElement('hidden', 'action', 1);
        }
        $mform->setType('action', PARAM_INT);

        $this->add_action_buttons(true, get_string('delete'));
    }
}
