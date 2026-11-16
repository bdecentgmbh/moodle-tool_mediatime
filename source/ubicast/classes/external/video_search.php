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

namespace mediatimesrc_ubicast\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mediatimesrc_ubicast\api;

/**
 * External function for UbiCast Nudgis video search
 *
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
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
                'query' => new external_value(PARAM_TEXT, 'Query string for video title'),
                'contextid' => new external_value(PARAM_INT, 'Context id of the Media Time library', VALUE_DEFAULT, SYSCONTEXTID),
            ]
        );
    }

    /**
     * Get video list
     *
     * Users allowed to view all videos search Nudgis, others only see videos
     * already linked in Media Time libraries they manage.
     *
     * @param string $query Query
     * @param int $contextid Context id of the Media Time library
     * @return array
     */
    public static function execute($query, $contextid = SYSCONTEXTID): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'contextid' => $contextid,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('tool/mediatime:manage', $context);

        if (has_capability('mediatimesrc/ubicast:viewall', $context)) {
            $api = new api();

            return array_map(function ($video) {
                return [
                    'name' => empty($video->parent_title) ? $video->title : "$video->title ($video->parent_title)",
                    'uri' => $video->oid,
                ];
            }, $api->search_videos($params['query']));
        }

        $resources = $DB->get_records('tool_mediatime', [
            'contextid' => $context->id,
            'source' => 'ubicast',
        ], 'timemodified DESC');
        $videos = [];
        foreach ($resources as $resource) {
            $content = json_decode($resource->content);
            if (
                !empty($content->oid)
                && (empty($params['query']) || stripos($content->title ?? '', $params['query']) !== false)
            ) {
                $videos[$content->oid] = ['name' => $content->title ?? $resource->name, 'uri' => $content->oid];
            }
        }

        return array_values($videos);
    }

    /**
     * Get return definition for execute
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Video title'),
                'uri' => new external_value(PARAM_ALPHANUM, 'Nudgis media object id'),
            ])
        );
    }
}
