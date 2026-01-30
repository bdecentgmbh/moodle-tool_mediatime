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
 * @package     mediatimesrc_ignite
 * @copyright   2026 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/resourcelib.php");

use mediatimesrc_vimeo\api;

/**
 * File serving callback
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
function mediatimesrc_ignite_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if (
        !in_array($context->contextlevel, [
            CONTEXT_SYSTEM,
            CONTEXT_COURSECAT,
            CONTEXT_COURSE,
        ])
    ) {
        return false;
    }

    if (
        in_array($filearea, [
            'chapters',
        ])
    ) {
        $itemid = array_shift($args);
        $filename = array_pop($args);
        $ext = resourcelib_get_extension($filename);
        if (
            $ext == 'vtt' && $record = $DB->get_record('tool_mediatime', [
            'id' => $itemid,
            'source' => 'ignite',
            ])
        ) {
            header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
            header("Content-Type: text/vtt\n");
            $resource = new \mediatimesrc_ignite\output\media_resource($record);
            echo $resource->chapters();
            die();
        }
    }
}
