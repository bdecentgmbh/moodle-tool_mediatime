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
 * Manage Media Time source plugins
 *
 * @package    tool_mediatime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mediatime\admin;

/**
 * Manage Media Time source plugins
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_mediatimesrc_plugins_page extends \admin_setting {
    /**
     * Class manage_mediatimesrc_plugins_page constructor.
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct(
            'managemediatimesrc',
            new \lang_string('managemediatimesrcplugins', 'tool_mediatime'),
            '',
            ''
        );
    }

    /**
     * Get setting
     *
     * @return bool
     */
    public function get_setting(): bool {
        return true;
    }

    /**
     * Get default setting
     *
     * @return bool
     */
    public function get_defaultsetting(): bool {
        return true;
    }

    /**
     * Write setting
     *
     * @param stdClass $data
     * @return string
     */
    public function write_setting($data): string {
        // Do not write any setting.
        return '';
    }

    /**
     * Find if related
     *
     * @param string $query
     * @return bool
     */
    public function is_related($query): bool {
        if (parent::is_related($query)) {
            return true;
        }
        $types = \core_plugin_manager::instance()->get_plugins_of_type('mediatimesrc');
        foreach ($types as $type) {
            if (
                strpos($type->component, $query) !== false ||
                    strpos(\core_text::strtolower($type->displayname), $query) !== false
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Output HTML
     *
     * @param stdClass $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = ''): string {
        global $CFG, $OUTPUT;
        $return = '';

        $pluginmanager = \core_plugin_manager::instance();
        $types = $pluginmanager->get_plugins_of_type('mediatimesrc');
        if (empty($types)) {
            return new lang_string('noquestionbanks', 'question');
        }
        $txt = new lang_strings(['settings', 'name', 'enable', 'disable', 'default']);
        $txt->uninstall = get_string('uninstallplugin', 'core_admin');

        $table = new \html_table();
        $table->head  = [$txt->name, $txt->enable, $txt->settings, $txt->uninstall];
        $table->align = ['left', 'center', 'center', 'center', 'center'];
        $table->attributes['class'] = 'managemediatimesrctable generaltable admintable';
        $table->data  = [];

        $totalenabled = 0;
        $count = 0;
        foreach ($types as $type) {
            if ($type->is_enabled() && $type->is_installed_and_upgraded()) {
                $totalenabled++;
            }
        }

        foreach ($types as $type) {
            $url = new \moodle_url('/admin/tool/mediatime/subplugins.php', [
                'sesskey' => sesskey(),
                'name' => $type->name,
                'type' => 'mediatimesrc',
            ]);

            $class = '';
            if (
                $pluginmanager->get_plugin_info('mediatimesrc_' . $type->name)->get_status() ===
                    \core_plugin_manager::PLUGIN_STATUS_MISSING
            ) {
                $strtypename = $type->displayname . ' (' . get_string('missingfromdisk') . ')';
            } else {
                $strtypename = $type->displayname;
            }

            if ($type->is_enabled()) {
                $hideshow = \html_writer::link(
                    $url->out(false, ['action' => 'disable']),
                    $OUTPUT->pix_icon('t/hide', $txt->disable, 'moodle', ['class' => 'iconsmall'])
                );
            } else {
                $class = 'dimmed_text';
                $hideshow = \html_writer::link(
                    $url->out(false, ['action' => 'enable']),
                    $OUTPUT->pix_icon('t/show', $txt->enable, 'moodle', ['class' => 'iconsmall'])
                );
            }

            $settings = '';
            if ($type->get_settings_url()) {
                $settings = \html_writer::link($type->get_settings_url(), $txt->settings);
            }

            $uninstall = '';
            if (
                $uninstallurl = \core_plugin_manager::instance()->get_uninstall_url(
                    'mediatimesrc_' . $type->name,
                    'manage'
                )
            ) {
                $uninstall = \html_writer::link($uninstallurl, $txt->uninstall);
            }

            $row = new \html_table_row([$strtypename, $hideshow, $settings, $uninstall]);
            if ($class) {
                $row->attributes['class'] = $class;
            }
            $table->data[] = $row;
            $count++;
        }

        // Sort table data.
        usort($table->data, function ($a, $b) {
            $aid = $a->cells[0]->text;
            $bid = $b->cells[0]->text;

            if ($aid == $bid) {
                return 0;
            }
            return $aid < $bid ? -1 : 1;
        });

        $return .= \html_writer::table($table);
        return highlight($query, $return);
    }
}
