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

require_login();

$source = optional_param('source', '', PARAM_ALPHANUMEXT);
$id = optional_param('id', null, PARAM_INT);
$delete = optional_param('delete', null, PARAM_INT);
$edit = optional_param('edit', null, PARAM_INT);

admin_externalpage_setup('mediatimelibrary');

if ($id) {
    $record = $DB->get_record('tool_mediatime', ['id' => $id]);
} else if ($delete) {
    $record = $DB->get_record('tool_mediatime', ['id' => $delete]);
} else if ($edit) {
    $record = $DB->get_record('tool_mediatime', ['id' => $edit]);
} else {
    $record = null;
}

$manager = new media_manager($source, $record);

$output = $PAGE->get_renderer('tool_mediatime');
echo $output->header();
echo $output->heading(get_string('pluginname', 'tool_mediatime'));

echo $output->render($manager);

echo $output->footer();
