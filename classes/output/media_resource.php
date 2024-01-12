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
 * @package    tool_mediatime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mediatime\output;

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
class media_resource implements renderable, templatable {
    /** @var ?stdClass $record Media Time resource record */
    protected $record;

    /**
     * Constructor
     *
     * @param stdClass $record Media Time resource record
     */
    public function __construct(stdClass $record) {
        $this->record = $record;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER;
        $content = json_decode($this->record->content);
        $resource = new \mediatimesrc_streamio\output\media_resource($this->record);
        $context = \context_system::instance();

        return [
            'canedit' => has_capability('moodle/tag:edit', $context) || $USER->id == $this->record->usermodified,
            'id' => $this->record->id,
            'libraryhome' => new moodle_url('/admin/tool/mediatime/index.php'),
            'resource' => format_text($output->render($resource), FORMAT_HTML, ['context' => $context]),
        ];
    }
}
