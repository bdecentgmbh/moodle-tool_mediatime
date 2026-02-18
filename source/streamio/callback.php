<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Callback from vimeo when upload is processed
 *
 * @package     mediatimesrc_streamio
 * @copyright   2026 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../../config.php');

$relativepath = get_file_argument();
$args = explode('/', trim($relativepath, '/'));
$record = $DB->get_record('tool_mediatime', ['id' => $args[0]]);
$content = json_decode($record->content);
$content->post = $_POST;
$videoid = json_decode(array_keys($_POST)[0])->video_id;
$api = new \mediatimesrc_streamio\api();
$video = $api->request("/videos/$videoid");
$record->content = json_encode($video);
$DB->update_record('tool_mediatime', $record);
require_login(null, true);
