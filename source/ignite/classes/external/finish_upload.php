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
 * External function for completing Ignite upload
 *
 * @package    mediatimesrc_ignite
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finish_upload extends external_api {
    /**
     * Get parameter definition for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Resource id'),
            'key' => new external_value(PARAM_TEXT, 'Ignite video key'),
            'parts' => new external_multiple_structure(new external_single_structure([
                'PartNumber' => new external_value(PARAM_INT, 'Part number'),
                'ETag' => new external_value(PARAM_TEXT, 'Etag'),
            ])),
            'uploadid' => new external_value(PARAM_TEXT, 'Upload id'),
        ]);
    }

    /**
     * Finish upload
     *
     * @param int $id Resource id
     * @param string $key Ignite video key
     * @param array $parts Parts array
     * @param string $uploadid uploadid
     * @return array Upload information
     */
    public static function execute($id, $key, $parts, $uploadid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'key' => $key,
            'parts' => $parts,
            'uploadid' => $uploadid,
        ]);

        $record = $DB->get_record('tool_mediatime', ['id' => $params['id']]);

        $context = \context::instance_by_id($record->contextid);
        self::validate_context($context);

        require_capability('mediatimesrc/ignite:upload', $context);

        $api = new api();

        $result = $api->request(
            "/videos/upload/s3/multipart/{$params['uploadid']}/complete?key={$params['key']}",
            [
                'parts' => $params['parts'],
            ],
            'POST'
        );

        return [
            'status' => true,
            'result' => json_encode($result),
        ];
    }

    /**
     * Get return definition for execute
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status'),
            'result' => new external_value(PARAM_TEXT, 'Status'),
        ]);
    }
}
