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
 * Plugin administration pages are defined here.
 *
 * @package     mediatimesrc_streamio
 * @category    admin
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'mediatimesrc_streamio/username',
            new lang_string('username', 'mediatimesrc_streamio'),
            new lang_string('username_help', 'mediatimesrc_streamio'),
            '',
            PARAM_ALPHANUMEXT
        ));
        $settings->add(new admin_setting_configtext(
            'mediatimesrc_streamio/password',
            new lang_string('password', 'mediatimesrc_streamio'),
            new lang_string('password_help', 'mediatimesrc_streamio'),
            '',
            PARAM_ALPHANUM
        ));
    }
}
