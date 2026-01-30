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
 * External functions and service definitions.
 *
 * @package     mediatimesrc_ignite
 * @category    external
 * @copyright   2025 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'mediatimesrc_ignite_create_token' => [
        'classname' => '\\mediatimesrc_ignite\\external\\create_token',
        'methodname' => 'execute',
        'description' => 'Create place holder to upload video',
        'type' => 'write',
        'ajax' => true,
    ],
    'mediatimesrc_ignite_finish_upload' => [
        'classname' => '\\mediatimesrc_ignite\\external\\finish_upload',
        'methodname' => 'execute',
        'description' => 'Finish multipart upload',
        'type' => 'write',
        'ajax' => true,
    ],
    'mediatimesrc_ignite_reattempt_upload' => [
        'classname' => '\\mediatimesrc_ignite\\external\\reattempt_upload',
        'methodname' => 'execute',
        'description' => 'Get new upload url for failed part',
        'type' => 'write',
        'ajax' => true,
    ],
    'mediatimesrc_ignite_tag_search' => [
        'classname' => '\\mediatimesrc_ignite\\external\\tag_search',
        'methodname' => 'execute',
        'description' => 'Search user tags on Ignite.',
        'type' => 'read',
        'ajax' => true,
    ],
    'mediatimesrc_ignite_video_search' => [
        'classname' => '\\mediatimesrc_ignite\\external\\video_search',
        'methodname' => 'execute',
        'description' => 'Search user videos on Ignite.',
        'type' => 'read',
        'ajax' => true,
    ],
];
