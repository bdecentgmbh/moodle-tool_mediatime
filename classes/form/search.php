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
 * Media Time media search form
 *
 * @package    tool_mediatime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mediatime\form;

defined('MOODLE_INTERNAL') || die();

use context_system;
use moodleform;

require_once($CFG->libdir . '/formslib.php');
/**
 * Media Time media search form
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search extends moodleform {
    /**
     * Definition
     */
    public function definition() {
        $mform = $this->_form;
        require_capability('tool/mediatime:manage', context_system::instance());

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', SYSCONTEXTID);

        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);

        $mform->addElement('text', 'query', get_string('keyword', 'tool_mediatime'));
        $mform->setType('query', PARAM_TEXT);

        $this->tag_elements();

        $this->add_action_buttons(false, get_string('search'));
        $mform->addElement('cancel', 'reset', get_string('reset'));
        $mform->disabledIf('reset', 'query', 'eq', '');
    }

    /**
     * Add tag elements
     */
    protected function tag_elements() {
        $mform = $this->_form;

        $mform->addElement(
            'tags',
            'tags',
            get_string('tags'),
            [
                'itemtype' => 'tool_mediatime',
                'component' => 'tool_mediatime',
            ]
        );
    }
}
