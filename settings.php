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
 * @package     tool_mediatime
 * @category    admin
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_mediatime_settings', new lang_string('pluginname', 'tool_mediatime'));

    $ADMIN->add('tools', new admin_category(
        'toolmediatime',
        new lang_string('pluginname', 'tool_mediatime'),
        false
    ));

    $pluginmanager = core_plugin_manager::instance();
    $ADMIN->add('toolmediatime', new admin_category(
        'mediatimesrcplugins',
        new lang_string('subplugin_mediatimesrc_plural', 'tool_mediatime'),
        false
    ));
    $temp = new admin_settingpage('managemediatimesrcplugins', new lang_string('managemediatimesrcplugins', 'tool_mediatime'));
    $temp->add(new \tool_mediatime\admin\manage_mediatimesrc_plugins_page());
    $ADMIN->add('mediatimesrcplugins', $temp);

    foreach ($pluginmanager->get_plugins_of_type('mediatimesrc') as $plugin) {
        $plugin->load_settings($ADMIN, 'mediatimesrcplugins', $hassiteconfig);
    }
    $settings = null;
}

$ADMIN->add('reports', new admin_externalpage(
    'mediatimelibrary',
    get_string('pluginname', 'tool_mediatime'),
    $CFG->wwwroot . "/admin/tool/mediatime/index.php",
    'tool/mediatime:view'
));
