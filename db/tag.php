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
 * Tag areas in component mod_wiki
 *
 * @package     tool_mediatime
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


$tagareas = [
    [
        'itemtype' => 'tool_mediatime',
        'component' => 'tool_mediatime',
        'callback' => 'tool_mediatime_get_tagged_resources',
        'callbackfile' => '/admin/tool/mediatime/lib.php',
    ],
];
