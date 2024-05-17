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
 * Display a resource in the media library
 *
 * @package    mediatimesrc_vimeo
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_vimeo\output;

use moodle_url;
use stdClass;
use renderable;
use renderer_base;
use templatable;

/**
 * Display a resource in the media library
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video implements renderable, templatable {
    /** @var ?stdClass $content Media Time content */
    protected $content;

    /**
     * Constructor
     *
     * @param stdClass $content Vimeo data object
     */
    public function __construct(stdClass $content) {
        $this->content = $content;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER;
        $context = \context_system::instance();

        return [
            'resource' => $this->content,
            'instance' => json_encode([
                'vimeo_url' => $this->content->link,
                'autopause' => 1,
                'autoplay' => 1,
                'background' => 0,
                'byline' => 1,
                'color' => 1,
                'controls' => 1,
                'dnt' => 1,
                'height' => 1,
                'loop' => 1,
                'maxheight' => 0,
                'maxwidth' => 0,
                'muted' => 1,
                'portrait' => 1,
                'pip' => 1,
                'playsinline' => 1,
                'responsive' => 1,
                'speed' => 1,
                'title' => 1,
                'transparent' => 1,
                'url' => 1,
                'width' => 1,
            ]),
            'interval' => 5,
            'uniqueid' => uniqid(),
        ];
    }
}
