<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     tool_mediatime
 * @category    upgrade
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute tool_mediatime upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_mediatime_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024010805) {

        // Define field contextid to be added to tool_mediatime.
        $table = new xmldb_table('tool_mediatime');
        $field = new xmldb_field('contextid', XMLDB_TYPE_INTEGER, '10', null, null, null, '1', 'content');

        // Conditionally launch add field contextid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Mediatime savepoint reached.
        upgrade_plugin_savepoint(true, 2024010805, 'tool', 'mediatime');
    }

    return true;
}
