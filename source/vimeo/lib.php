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
 * @package     mediatimesrc_vimeo
 * @copyright   2022 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/resourcelib.php");

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
function mediatimesrc_vimeo_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if (
        in_array($filearea, [
        'posterimage',
        'videofile',
        ])
    ) {
        $itemid = array_shift($args);
        $contenthash = array_shift($args);

        $relativepath = implode('/', $args);

        $fullpath = "/$context->id/mediatimesrc_vimeo/$filearea/$itemid/$relativepath";

        $fs = get_file_storage();
        if (
            (!$file = $fs->get_file_by_hash(sha1($fullpath)))
            || $file->is_directory()
            || ($contenthash != $file->get_contenthash())
        ) {
            return false;
        }

        send_stored_file($file, null, 0, $forcedownload, $options);
    }
}

/**
 * Return file areas for backup
 *
 * @return array List of file areas
 */
function mediatimesrc_vimeo_config_file_areas() {
    return [
        'videofile',
    ];
}
