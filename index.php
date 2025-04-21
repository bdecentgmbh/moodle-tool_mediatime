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
 * Resource library page
 *
 * @package    tool_mediatime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_mediatime\media_manager;

$source = optional_param('source', '', PARAM_ALPHANUMEXT);
$id = optional_param('id', null, PARAM_INT);
$delete = optional_param('delete', null, PARAM_INT);
$edit = optional_param('edit', null, PARAM_INT);
$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);

if ($id) {
    $record = $DB->get_record('tool_mediatime', ['id' => $id]);
} else if ($delete) {
    $record = $DB->get_record('tool_mediatime', ['id' => $delete]);
} else if ($edit) {
    $record = $DB->get_record('tool_mediatime', ['id' => $edit]);
} else {
    $record = null;
}
if (!empty($record)) {
    $contextid = $record->contextid;
}

if ($contextid == SYSCONTEXTID) {
    require_login();
    admin_externalpage_setup('mediatimelibrary');
} else {
    $context = context::instance_by_id($contextid);
    $PAGE->set_context($context);
    if ($context->contextlevel == CONTEXT_COURSECAT) {
        $coursecat = core_course_category::get($context->instanceid);
        $PAGE->set_category_by_id($coursecat->id);
        $PAGE->set_heading($coursecat->name);
    } else {
        $course = get_course($context->instanceid);
        $PAGE->set_heading($course->fullname);
        $PAGE->set_course($course);
    }
    $PAGE->set_title(get_string('pluginname', 'tool_mediatime'));
    $PAGE->set_url('/admin/tool/mediatime/index.php', ['contextid' => $contextid]);
}

$manager = new media_manager($source, $record);

echo $OUTPUT->header();
if (empty($source)) {
    echo $OUTPUT->heading(get_string('pluginname', 'tool_mediatime'));
} else {
    echo $OUTPUT->heading(get_string('pluginname', "mediatimesrc_$source"));
}

echo $OUTPUT->render($manager);

echo $OUTPUT->footer();
