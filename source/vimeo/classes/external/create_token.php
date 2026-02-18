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

use mediatimesrc_vimeo\api;
use cache;
use context;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_tag_tag;
use stdClass;

/**
 * External function for creating resource place holder for Vimeo upload
 *
 * @package    mediatimesrc_vimeo
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_token extends external_api {
    /**
     * Get parameter definition for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'Context id'),
                'description' => new external_value(PARAM_TEXT, 'Resource description'),
                'filesize' => new external_value(PARAM_INT, 'Video file size'),
                'groupid' => new external_value(PARAM_INT, 'Group id'),
                'name' => new external_value(PARAM_TEXT, 'Resource name'),
                'parenturi' => new external_value(PARAM_RAW, 'Parent folder'),
                'tags' => new external_value(PARAM_RAW, 'Resource tags'),
                'title' => new external_value(PARAM_TEXT, 'Resource title'),
            ]
        );
    }

    /**
     * Create place holder
     *
     * @param int $contextid Context id
     * @param string $description Name of resource
     * @param int $filesize Video file size
     * @param int $groupid Group id
     * @param string $name Name of resource
     * @param string $parenturi Parent folder uri
     * @param string $tags Tags to add
     * @param string $title Name of resource
     * @return array Upload information
     */
    public static function execute($contextid, $description, $filesize, $groupid, $name, $parenturi, $tags, $title): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'description' => $description,
            'filesize' => $filesize,
            'groupid' => $groupid,
            'name' => $name,
            'parenturi' => $parenturi,
            'tags' => $tags,
            'title' => $title,
        ]);

        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);

        require_login();
        require_capability('mediatimesrc/vimeo:upload', $context);

        if ($context instanceof \context_course) {
            $course = get_course($context->instanceid);
            if ($groupmode = groups_get_course_groupmode($course)) {
                $groups = groups_get_all_groups($course->id);
                if (!key_exists($params['groupid'], $groups)) {
                    require_capability('moodle/site:accessallgroups', $context);
                }
            }
        } else {
            $params['groupid'] = 0;
        }
        $api = new api();

        $video = $api->create_token([
            'upload' => [
                'approach' => 'tus',
                'size' => $params['filesize'],
            ],
        ]);
        $updatedvideo = $api->request($video['uri'], [
            'name' => $params['title'] ?: $params['name'],
            'description' => $params['description'],
        ], 'PATCH')['body'];
        $clock = \core\di::get(\core\clock::class);
        $timenow = $clock->time();
        $id = $DB->insert_record('tool_mediatime', [
            'name' => $params['name'],
            'source' => 'vimeo',
            'content' => json_encode($updatedvideo),
            'contextid' => $params['contextid'],
            'groupid' => $params['groupid'],
            'timecreated' => $timenow,
            'timemodified' => $timenow,
            'usermodified' => $USER->id,
        ]);
        if (!empty($params['parenturi'])) {
            $api->request(
                json_decode($parenturi) . "/items",
                ['items' => [['uri' => $updatedvideo['uri']]]],
                'POST'
            );
        }

        if (!empty($params['tags'])) {
            core_tag_tag::set_item_tags(
                'tool_mediatime',
                'tool_mediatime',
                $id,
                $context,
                json_decode(htmlspecialchars_decode($params['tags'], ENT_COMPAT))
            );
        }

        $event = \mediatimesrc_vimeo\event\resource_created::create([
            'contextid' => $contextid,
            'objectid' => $id,
        ]);
        $event->trigger();

        return [
            'id' => $id,
            'uploadurl' => $video['upload']['upload_link'],
            'uri' => $video->uri,
        ];
    }

    /**
     * Get return definition for execute
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Resource id'),
            'uploadurl' => new external_value(PARAM_TEXT, 'Upload url'),
            'uri' => new external_value(PARAM_TEXT, 'Vimeo uri'),
        ]);
    }
}
