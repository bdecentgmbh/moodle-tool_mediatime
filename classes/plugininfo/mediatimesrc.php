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
 * Subplugin info class.
 *
 * @package     tool_mediatime
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mediatime\plugininfo;

use core\plugininfo\base;
use moodle_url;

/**
 * Subplugin info class.
 *
 * @package     tool_mediatime
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mediatimesrc extends base {
    /**
     * Allow uninstall
     *
     * @return bool
     */
    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Loads plugin settings to the settings tree
     *
     * This function usually includes settings.php file in plugins folder.
     * Alternatively it can create a link to some settings page (instance of admin_externalpage)
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig whether the current user has moodle/site:config capability
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // In case settings.php wants to refer to them.
        $ADMIN      = $adminroot; // May be used in settings.php.
        $plugininfo = $this; // Also can be used inside settings.php.

        if (!$this->is_installed_and_upgraded()) {
            return;
        }
        if (!file_exists($this->full_path('settings.php'))) {
            return;
        }
        $section  = $this->get_settings_section_name();
        $settings = new \admin_settingpage(
            $section,
            $this->displayname,
            'moodle/site:config',
            $this->is_enabled() === false
        );

        include($this->full_path('settings.php')); // This may also set $settings to null.

        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    /**
     * Enable plugin
     *
     * @param string $pluginname
     * @param int $enabled
     * @return bool
     */
    public static function enable_plugin(string $pluginname, int $enabled): bool {
        $haschanged = false;

        $plugin = 'mediatimesrc_' . $pluginname;
        $oldvalue = get_config($plugin, 'enabled');
        // Only set value if there is no config setting or if the value is different from the previous one.
        if ($oldvalue === false || ((bool) $oldvalue != $enabled)) {
            set_config('enabled', $enabled, $plugin);
            $haschanged = true;

            add_to_config_log('enabled', !$enabled, $enabled, $plugin);
            \core_plugin_manager::reset_caches();
        }

        return $haschanged;
    }

    /**
     * Returns the information about plugin availability
     *
     * True means that the plugin is enabled. False means that the plugin is
     * disabled. Null means that the information is not available, or the
     * plugin does not support configurable availability or the availability
     * can not be changed.
     *
     * @return null|bool
     */
    public function is_enabled() {
        return !empty(get_config($this->type . '_' . $this->name, 'enabled'));
    }

    /**
     * Get settings section name
     *
     * @return string
     */
    public function get_settings_section_name(): string {
        return $this->type . '_' . $this->name . '_settings';
    }

    /**
     * Get settings url
     *
     * @return moodle_url|null
     */
    public function get_settings_url(): ?moodle_url {
        global $CFG;
        if (!file_exists($this->full_path('settings.php'))) {
            return null;
        }
        return new moodle_url('/admin/settings.php', [
            'section' => $this->get_settings_section_name(),
        ]);
    }

    /**
     * Get a list of enabled plugins
     *
     * @return array
     * @throws \dml_exception
     */
    public static function get_enabled_plugins(): array {
        // Get all available plugins.
        $plugins = \core_plugin_manager::instance()->get_installed_plugins('mediatimesrc');

        // Check they are enabled using get_config (which is cached and hopefully fast).
        $enabled = [];
        foreach ($plugins as $plugin => $version) {
            if (get_config("mediatimesrc_$plugin", 'enabled')) {
                $enabled[$plugin] = $plugin;
            }
        }

        return $enabled;
    }
}
