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

namespace tool_mediatime\external;

use context;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_mediatime\media_manager;

/**
 * External function for Ignite video search
 *
 * @package    tool_mediatime
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_search extends external_api {
    /**
     * Get parameter definition for execute
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'query' => new external_value(PARAM_TEXT, 'Query string for video name'),
            ]
        );
    }

    /**
     * Get resource list
     *
     * @param int $query Query
     * @return array
     */
    public static function execute($query): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
        ]);

        $rs = media_manager::search(['query' => $query]);
        $options = [];
        foreach ($rs as $record) {
            $options[$record->id] = [
               'id' => $record->id,
               'name' => $record->name,
            ];
        }
        $rs->close();

        return array_values($options);
    }

    /**
     * Get return definition for execute
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Resource id'),
                'name' => new external_value(PARAM_TEXT, 'Resource name'),
            ])
        );
    }
}
