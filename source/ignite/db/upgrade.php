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
 * @package     mediatimesrc_ignite
 * @category    upgrade
 * @copyright   2025 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mediatimesrc_ignite upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_mediatimesrc_ignite_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    if ($oldversion < 2025111106) {
        // Define table mediatimesrc_ignite to be created.
        $table = new xmldb_table('mediatimesrc_ignite');

        // Adding fields to table mediatimesrc_ignite.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('resourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('igniteid', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table mediatimesrc_ignite.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('resourceid', XMLDB_KEY_FOREIGN_UNIQUE, ['resourceid'], 'tool_mediatime', ['id']);

        // Conditionally launch create table for mediatimesrc_ignite.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Ignite savepoint reached.
        upgrade_plugin_savepoint(true, 2025111106, 'mediatimesrc', 'ignite');
    }

    return true;
}
