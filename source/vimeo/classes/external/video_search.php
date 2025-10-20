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

namespace mediatimesrc_vimeo\external;

use context;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mediatimesrc_vimeo\api;

/**
 * External function for Vimeo video search
 *
 * @package    mediatimesrc_vimeo
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_search extends external_api {
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
     * Get video list
     *
     * @param int $query Query
     * @return array
     */
    public static function execute($query): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $api = new api();
        if (!has_capability('mediatimesrc/vimeo:viewall', $context)) {
            $api = new api($USER->id);
        }

        require_login();
        require_capability('mod/videotime:view', $context);

        $videos = $api->request('/me/videos', [
            'direction' => 'desc',
            'query' => $params['query'],
            'sort' => 'date',
        ])['body']['data'] ?? [];

        return array_map(function ($video) {
            return ['name' => $video['name'], 'uri' => $video['uri']];
        }, $videos);
    }

    /**
     * Get return definition for execute
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Video name'),
                'uri' => new external_value(PARAM_TEXT, 'Vimeo uri'),
            ])
        );
    }
}
