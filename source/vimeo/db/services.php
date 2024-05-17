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
 * @package     mediatimesrc_vimeo
 * @category    external
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'mediatimesrc_vimeo_create_token' => [
        'classname' => '\\mediatimesrc_vimeo\\external\\create_token',
        'methodname' => 'execute',
        'description' => 'Create place holder to upload video',
        'type' => 'write',
        'ajax' => true,
    ],
];
