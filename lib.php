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
 * @package     tool_mediatime
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
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
function tool_mediatime_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if (!in_array($context->contextlevel, [
        CONTEXT_SYSTEM,
        CONTEXT_COURSECAT,
        CONTEXT_COURSE,
    ])) {
        return false;
    }

    require_login();
    require_capability('tool/mediatime:view');

    if (
        in_array($filearea, [
        'm3u8',
        ])
    ) {
        $itemid = array_shift($args);

        $relativepath = implode('/', $args);

        $fullpath = "/$context->id/tool_mediatime/$filearea/$itemid/$relativepath";

        $fs = get_file_storage();
        if ((!$file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
            return false;
        }

        send_stored_file($file, null, 0, $forcedownload, $options);
    }
}

/**
 * Return tagged resources
 *
 * @return array List of file areas
 */
function tool_mediatime_get_tagged_resources() {
    return [
    ];
}

/**
 * Return file areas for backup
 *
 * @return array List of file areas
 */
function tool_mediatime_config_file_areas() {
    return [
        'm3u8',
    ];
}

/**
 * Extends the navigation of the category admin menu with the Mediatime link
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param context $coursecategorycontext The context of the course category
 */
function tool_mediatime_extend_navigation_category_settings(
    navigation_node $navigation,
    context $coursecategorycontext
): void {
    if (has_capability('tool/mediatime:view', $coursecategorycontext)) {
        $title = get_string('pluginname', 'tool_mediatime');
        $path = new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $coursecategorycontext->id]);
        $settingsnode = navigation_node::create(
            $title,
            $path,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/course', '')
        );
        $navigation->add_node($settingsnode);
    }
}

/**
 * This function extends the navigation with Mediatime links
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass        $course     The course to object for the tool
 * @param context         $context    The context of the course
 */
function tool_mediatime_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('tool/mediatime:view', $context)) {
        $url = new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $context->id]);
        $settingsnode = navigation_node::create(
            get_string('pluginname', 'tool_mediatime'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/settings', '')
        );

        if (isset($settingsnode)) {
            $navigation->add_node($settingsnode);
        }
    }
}

/**
 * Callback to delete resources when category deleted
 *
 * @param stdClass $category
 */
function tool_mediatime_pre_course_category_delete(stdClass $category) {
    global $DB;

    $context = \context_coursecat::instance($category->id);
    $DB->delete_records('tool_mediatime', ['contextid' => $context->id]);
}
