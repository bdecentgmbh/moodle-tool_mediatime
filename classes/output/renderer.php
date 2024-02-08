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
 * Renderer for tool_mediatime
 *
 * @package     tool_mediatime
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mediatime\output;

use plugin_renderer_base;
use renderable;

/**
 * Renderer for tool_mediatime
 *
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Overrides the parent so that templatable widgets are handled even without their explicit render method.
     *
     * @param renderable $widget
     * @return string
     * @throws \moodle_exception
     */
    public function render(renderable $widget) {

        $namespacedclassname = get_class($widget);
        $parts = [];
        if ($parts = explode('\\', $namespacedclassname)) {
            $plainclassname = $parts[array_key_last($parts)];
            $namespacecomponent = $parts[array_key_first($parts)];
            $rendermethod = 'render_'.$plainclassname;

            if (method_exists($this, $rendermethod)) {
                // Explicit rendering method exists, fall back to the default behaviour.
                return parent::render($widget);
            }

            $interfaces = class_implements($namespacedclassname);

            if (isset($interfaces['templatable'])) {
                // Default implementation of template-based rendering.
                $data = $widget->export_for_template($this);

                if (method_exists($widget, 'get_template_name')) {
                    $templatename = $widget->get_template_name();
                } else {
                    $templatename = $plainclassname;
                }

                if (method_exists($widget, 'get_component_name')) {
                    $component = $widget->get_component_name();
                } else if (count($parts) > 1) {
                    $component = $namespacecomponent;
                } else {
                    $component = 'core';
                }

                return parent::render_from_template($component . '/' . $templatename, $data);

            } else {
                return parent::render($widget);
            }
        }
    }
}
