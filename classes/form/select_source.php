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
 * Media Time media source selector
 *
 * @package    tool_mediatime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mediatime\form;

use moodleform;
use tool_mediatime\plugininfo\mediatimesrc;

/**
 * Media Time media source selector
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_source extends moodleform {

    /**
     * Definition
     */
    public function definition() {
        $mform = $this->_form;

        $options = [];
        foreach (mediatimesrc::get_enabled_plugins() as $plugin) {
            $options[$plugin] = get_string("pluginname", "mediatimesrc_$plugin");
        }
        $mform->addElement('select', 'source', get_string('source', 'tool_mediatime'), $options);
        $this->add_action_buttons(false);
    }
}
