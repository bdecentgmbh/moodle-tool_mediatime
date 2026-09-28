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

use mediatimesrc_ignite\admin\autocomplete;
use mediatimesrc_ignite\api;
use mediatimesrc_ignite\form\edit_resource;

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

    $categories = explode(',', get_config('mediatimesrc_ignite', 'categories'));
    try {
        $choices = api::categories_menu($categories);
    } catch (Exception $e) {
        $choices = [];
    }
    $attributes = [
        'manageurl' => '',
        'ajax' => 'mediatimesrc_ignite/category_datasource',
        'multiple' => true,
        'delimiter' => ',',
        'tags' => true,
    ];
    $settings->add(new autocomplete(
        'mediatimesrc_ignite/categories',
        new lang_string('defaultcategories', 'mediatimesrc_ignite'),
        new lang_string('defaultcategories_desc', 'mediatimesrc_ignite'),
        '',
        $choices,
        $attributes,
        ''
    ));

    $languages = [
        '' => get_string('none'),
        'userpreference' => get_string('userpreference', 'admin'),
    ];
    foreach (\get_string_manager()->get_list_of_translations() as $key => $language) {
        if (!empty(edit_resource::supported_code($key))) {
            $languages[$key] = $language;
        }
    }
    $settings->add(new admin_setting_configselect(
        'mediatimesrc_ignite/subtitlelanguage',
        get_string('defaultsubtitlelanguage', 'mediatimesrc_ignite'),
        get_string('defaultsubtitlelanguage_desc', 'mediatimesrc_ignite'),
        '',
        $languages
    ));

    $name = new lang_string('enabledraganddrop', 'mediatimesrc_ignite');
    $description = new lang_string('enabledraganddrop_help', 'mediatimesrc_ignite');
    $setting = new admin_setting_configcheckbox(
        'mediatimesrc_ignite/enabledraganddrop',
        $name,
        $description,
        0
    );
    $settings->add($setting);

    $settings->add(new admin_setting_configselect(
        'mediatimesrc_ignite/dndsubtitlelanguage',
        get_string('dndsubtitlelanguage', 'mediatimesrc_ignite'),
        get_string('dndsubtitlelanguage_desc', 'mediatimesrc_ignite'),
        '',
        $languages
    ));
}
