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

use mediatimesrc_ignite\api;
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
 * @package    mediatimesrc_ignite
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
                'mimetype' => new external_value(PARAM_TEXT, 'Mime type'),
                'name' => new external_value(PARAM_TEXT, 'Resource name'),
                'parts' => new external_value(PARAM_INT, 'Number of parts'),
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
     * @param string $mimetype Mime type
     * @param string $name Name of resource
     * @param string $parts Number of parts to upload
     * @param string $tags Tags to add
     * @param string $title Name of resource
     * @return array Upload information
     */
    public static function execute($contextid, $description, $filesize, $groupid, $mimetype, $name, $parts, $tags, $title): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'description' => $description,
            'filesize' => $filesize,
            'groupid' => $groupid,
            'mimetype' => $mimetype,
            'name' => $name,
            'parts' => $parts,
            'tags' => $tags,
            'title' => $title,
        ]);

        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);

        require_login();
        require_capability('mediatimesrc/ignite:upload', $context);

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

        $params['title'] = $params['title'] ?: $params['name'];

        $api = new api();

        $video = $api->request(
            "/videos/upload",
            [
                'mimeType' => $params['mimetype'],
                'title' => $params['title'],
                'useMultipart' => true,
                'visibility' => 'public',
            ],
            'PUT'
        );
        $video->id = $video->videoId;
        $video->description = $params['description'];
        $video->name = $params['name'];
        $id = $DB->insert_record('tool_mediatime', [
            'name' => $params['name'],
            'source' => 'ignite',
            'content' => json_encode($video),
            'contextid' => $params['contextid'],
            'groupid' => $params['groupid'],
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => $USER->id,
        ]);

        if (!empty($params['tags'])) {
            core_tag_tag::set_item_tags(
                'tool_mediatime',
                'tool_mediatime',
                $id,
                $context,
                json_decode(htmlspecialchars_decode($params['tags'], ENT_COMPAT))
            );
        }

        $event = \mediatimesrc_ignite\event\resource_created::create([
            'contextid' => $contextid,
            'objectid' => $id,
        ]);
        $event->trigger();

        $url = $api->request(
            '/videos/upload/s3/multipart/' . urlencode($video->multipartUpload->uploadId) .
                '/prepare-parts?key=' . urlencode($video->multipartUpload->key),
            [
                'startPart' => 1,
                'endPart' => $parts,
            ],
            'POST'
        );

        return [
            'id' => $id,
            'key' => urlencode($video->multipartUpload->key),
            'parts' => $url->parts,
            'uploadid' => urlencode($video->multipartUpload->uploadId),
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
            'key' => new external_value(PARAM_TEXT, 'Ignite video key'),
            'parts' => new external_multiple_structure(new external_single_structure([
                'partNumber' => new external_value(PARAM_INT, 'Part number'),
                'url' => new external_value(PARAM_TEXT, 'Part URL'),
            ])),
            'uploadid' => new external_value(PARAM_TEXT, 'Upload id'),
        ]);
    }
}
