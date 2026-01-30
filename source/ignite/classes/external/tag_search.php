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

namespace mediatimesrc_ignite\external;

use context;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mediatimesrc_ignite\api;

/**
 * External function for Ignite tag search
 *
 * @package    mediatimesrc_ignite
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_search extends external_api {
    /**
     * Get parameter definition for execute
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'query' => new external_value(PARAM_TEXT, 'Query string for tag name'),
            ]
        );
    }

    /**
     * Get tag list
     *
     * @param int $query Query
     * @return array
     */
    public static function execute($query): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $api = new api();

        $tags = $api->request("/tags?sortBy=title&limit=25&where[title][like]=" . $params['query'], [ ]);

        return array_map(function ($tag) {
            return [
                'id' => $tag->id,
                'slug' => $tag->slug,
                'title' => $tag->title,
            ];
        }, $tags->docs);
    }

    /**
     * Get return definition for execute
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Tag id'),
                'slug' => new external_value(PARAM_TEXT, 'Tag slug'),
                'title' => new external_value(PARAM_TEXT, 'Tag title'),
            ])
        );
    }
}
