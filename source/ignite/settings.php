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
 * @package     mediatimesrc_ignite
 * @category    admin
 * @copyright   2025 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'mediatimesrc_ignite/apikey',
            new lang_string('apikey', 'mediatimesrc_ignite'),
            new lang_string('apikey_help', 'mediatimesrc_ignite'),
            '',
            PARAM_ALPHANUMEXT
        ));
    }

    $name = new lang_string('enabledraganddrop', 'mediatimesrc_ignite');
    $description = new lang_string('enabledraganddrop_help', 'mediatimesrc_ignite');
    $setting = new admin_setting_configcheckbox(
        'mediatimesrc_ignite/enabledraganddrop',
        $name,
        $description,
        0
    );
    $settings->add($setting);
}
