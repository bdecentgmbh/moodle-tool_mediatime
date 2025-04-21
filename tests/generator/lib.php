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
 * Media Time resource data generator class
 *
 * @package    tool_mediatime
 * @category   test
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_mediatime_generator extends testing_module_generator {
    /**
     * Creates new mediatime resource
     *
     * @param array|stdClass $record data for resource being generated.
     * @return stdClass record from tool_mediatime table
     */
    public function create_resource($record = null): stdClass {
        global $DB, $USER;

        $record = (array)(object)$record + [
            'source' => 'videotime',
            'name' => 'Resource 1',
            'usermodified' => $USER->id,
            'contextid' => SYSCONTEXTID,
            'content' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $record['id'] = $DB->insert_record('tool_mediatime', $record);

        return (object)$record;
    }
}
