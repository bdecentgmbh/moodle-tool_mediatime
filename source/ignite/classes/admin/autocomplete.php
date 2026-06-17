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

namespace mediatimesrc_ignite\admin;

use mediatimesrc_ignite\api;
use moodle_exception;

/**
 * Auto complete setting class.
 *
 * @package    mediatimesrc_ignite
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autocomplete extends \core_admin\local\settings\autocomplete {
    /**
     * Saves setting(s) provided through $data
     *
     * @param array $data
     */
    public function write_setting($data) {
        if (!is_array($data)) {
            return ''; // Ignore it.
        }

        unset($data['xxxxx']);

        try {
            $api = new api();
            $save = $api->create_categories($data);
        } catch (moodle_exception $e) {
            return ($this->config_write($this->name, '') ? '' : get_string('errorsetting', 'admin'));
        }

        return ($this->config_write($this->name, implode($this->delimiter, $save)) ? '' : get_string('errorsetting', 'admin'));
    }

    /**
     * Returns XHTML autocomplete field
     *
     * @param array $data Array of values to select by default
     * @param string $query
     * @return string XHTML autocomplete field
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $default = $this->get_defaultsetting();
        if (empty($default)) {
            $default = [];
        }

        if (is_null($data)) {
            $data = [];
        }

        $context = [
                'id' => $this->get_id(),
                'name' => $this->get_full_name(),
        ];

        $defaults = [];
        $options = [];
        $template = 'core_admin/local/settings/autocomplete';

        foreach ($this->choices as $value => $name) {
            if (in_array($value, $default)) {
                $defaults[] = $name;
            }
            $options[] = [
                    'value' => $value,
                    'text' => $name,
                    'selected' => in_array($value, $data),
                    'disabled' => false,
            ];
        }

        $context['options'] = $options;
        $context['tags'] = $this->tags;
        $context['ajax'] = $this->ajax;
        $context['placeholder'] = $this->placeholder;
        $context['casesensitive'] = $this->casesensitive;
        $context['multiple'] = $this->multiple;
        $context['showsuggestions'] = $this->showsuggestions;
        $context['manageurl'] = $this->manageurl;
        $context['managetext'] = $this->managetext;

        if (is_null($default)) {
            $defaultinfo = null;
        } if (!empty($defaults)) {
            $defaultinfo = implode(', ', $defaults);
        } else {
            $defaultinfo = get_string('none');
        }

        $element = $OUTPUT->render_from_template($template, $context);

        return format_admin_setting($this, $this->visiblename, $element, $this->description, true, '', $defaultinfo, $query);
    }
}
