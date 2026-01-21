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
class reattempt_upload extends external_api {
    /**
     * Get parameter definition for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'Context id'),
                'key' => new external_value(PARAM_TEXT, 'Key'),
                'partnumber' => new external_value(PARAM_INT, 'Part number'),
                'uploadid' => new external_value(PARAM_TEXT, 'Upload id'),
            ]
        );
    }

    /**
     * Create new part url
     *
     * @param int $contextid Context id
     * @param int $partnumber Part number
     * @param string $key Key
     * @param string $uploadid Upload id
     * @return array Upload information
     */
    public static function execute($contextid, $key, $partnumber, $uploadid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'key' => $key,
            'partnumber' => $partnumber,
            'uploadid' => $uploadid,
        ]);

        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);

        require_login();
        require_capability('mediatimesrc/ignite:upload', $context);

        $api = new api();

        $result = $api->request(
            "/videos/upload/s3/multipart/{$params['uploadid']}/{$params['partnumber']}?key={$params['key']}"
        );

        return (array)$result;
    }

    /**
     * Get return definition for execute
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'url' => new external_value(PARAM_TEXT, 'Upload URL'),
        ]);
    }
}
