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
 * @package    tool_mediatime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mediatime\form;

use context_system;
use moodleform;

/**
 * Media Time media edit form
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_resource extends moodleform {
    /**
     * @var ?\context $context
     */
    protected ?\context $context = null;

    /**
     * Definition
     */
    public function definition() {
        $mform = $this->_form;
        require_capability('tool/mediatime:manage', context_system::instance());

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
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

    /**
     * Add group selector element
     */
    protected function selected_group() {
        $mform = $this->_form;

        if (!$this->context instanceof \context_course) {
            $mform->addElement('hidden', 'groupid', 0);
            return;
        }
        $course = get_course($this->context->instanceid);
        if (!$groupmode = groups_get_course_groupmode($course)) {
            $mform->addElement('hidden', 'groupid', 0);
            return;
        }
        $group = groups_get_course_group($course);
        $groups = groups_get_all_groups($course->id);
        if (!key_exists($group, $groups)) {
            require_capability('moodle/site:accessallgroups', $this->context);
        }
        $options = array_column($groups, 'name', 'id');
        if (has_capability('moodle/site:accessallgroups', $this->context)) {
            $options = ['0' => get_string('allgroups')] + $options;
        }
        $mform->insertElementBefore(
            $mform->createElement('select', 'groupid', 'Group', $options),
            'tags'
        );
        $mform->setDefault('groupid', $group);
    }
}
