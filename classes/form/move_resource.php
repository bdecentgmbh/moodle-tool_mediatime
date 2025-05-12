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
class move_resource extends moodleform {
    /**
     * @var Context
     */
    protected ?\context $context;

    /**
     * Definition
     */
    public function definition() {
        $mform = $this->_form;
        $this->context = $this->_customdata['context'];
        $record = $this->_customdata['record'];
        require_capability('tool/mediatime:manage', context_system::instance());

        $mform->addElement('hidden', 'id', $record->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('static', 'name', get_string('name'), s($record->name));

        $levels = [];
        $levels[] = $mform->createElement('radio', 'contextlevel', ' ', get_string('site'), CONTEXT_SYSTEM);
        $levels[] = $mform->createElement('radio', 'contextlevel', ' ', get_string('category'), CONTEXT_COURSECAT);
        $levels[] = $mform->createElement('radio', 'contextlevel', ' ', get_string('course'), CONTEXT_COURSE);
        $mform->addGroup($levels, 'levels', get_string('moveto', 'tool_mediatime'), [' '], false);

        $categories = \core_course_category::get_all();
        $options = [];
        foreach ($categories as $category) {
            if (has_capability('tool/mediatime:manage', \context_coursecat::instance($category->id))) {
                $options[$category->id] = $category->name;
            }
        }
        $mform->addElement('select', 'categoryid', get_string('category'), $options);
        $mform->hideIf('categoryid', 'contextlevel', 'neq', CONTEXT_COURSECAT);
        $mform->hideIf('courseid', 'contextlevel', 'neq', CONTEXT_COURSE);
        $mform->setDefault('contextlevel', $this->context->contextlevel);

        $courses = \core_course_category::search_courses([], [], ['tool/mediatime:manage']);
        $options = [];
        foreach ($courses as $course) {
            $options[$course->id] = $course->fullname;
        }
        $mform->addElement('select', 'courseid', get_string('course'), $options);

        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);
        $mform->setDefault('source', 'vimeo');
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);
        $mform->setDefault('action', 'move');

        $this->add_action_buttons(true, get_string('move'));
    }
}
