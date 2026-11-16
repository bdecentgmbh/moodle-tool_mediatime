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
 * Library of interface functions and constants.
 *
 * @package     mediatimesrc_ubicast
 * @copyright   2026 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * File serving callback
 *
 * Serves the thumbnail of a Nudgis video. Nudgis thumbnail links work only
 * once, so the image is fetched with the API key and kept as a file.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file was not found, just send the file otherwise and do not return anything
 */
function mediatimesrc_ubicast_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if (
        $filearea !== 'thumbnail'
        || !in_array($context->contextlevel, [
            CONTEXT_SYSTEM,
            CONTEXT_COURSECAT,
            CONTEXT_COURSE,
        ])
    ) {
        return false;
    }

    require_login(null, true);

    $itemid = (int)array_shift($args);
    if (
        !$record = $DB->get_record('tool_mediatime', [
            'id' => $itemid,
            'contextid' => $context->id,
            'source' => 'ubicast',
        ])
    ) {
        return false;
    }

    $resource = new \mediatimesrc_ubicast\output\media_resource($record);
    if (!$file = $resource->thumbnail_file()) {
        return false;
    }
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}
