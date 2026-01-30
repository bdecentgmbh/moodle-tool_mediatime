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
 * External function for Ignite video search
 *
 * @package    mediatimesrc_ignite
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
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $api = new api();
        if (has_capability('mediatimesrc/ignite:viewall', $context)) {
            $api = new api($USER->id);

            $videos = $api->request("/videos?sortBy=-createdAt&limit=25&where[title][like]=" . $params['query'], [ ]);

            return array_map(function ($video) {
                return ['name' => $video->title, 'uri' => $video->id];
            }, $videos->docs);
        }

        // We need filtered results.
        if (!$contextids = self::get_user_contextids()) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $resources = $DB->get_records_select(
            'tool_mediatime',
            "contextid $insql AND source = 'ignite'",
            $inparams,
            'timemodified DESC'
        );
        $videos = [];
        foreach ($resources as $resource) {
            $content = json_decode($resource->content);
            if (empty($query) || strpos($query, $content->title) !== false) {
                $videos[$content->id] = ['name' => $content->title, 'uri' => $content->id];
            }
        }

        return array_values($videos);
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

    /**
     * Get context ids where user has access
     *
     * @return array
     */
    private static function get_user_contextids() {
        global $USER;

        $categories = \core_course_category::get_all();
        $contextids = [];
        foreach ($categories as $category) {
            $context = \context_coursecat::instance($category->id);
            if (has_capability('tool/mediatime:manage', $context)) {
                $contextids[] = $context->id;
            }
        }

        $courses = \core_course_category::search_courses([], [], ['tool/mediatime:manage']);
        foreach ($courses as $course) {
            $contextids[] = \context_course::instance($course->id)->id;
        }

        return $contextids;
    }
}
