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

use core_tag_tag;
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

    /** @var $record Source specifific resource renderable */
    protected $resource;

    /**
     * Constructor
     *
     * @param stdClass $record Media Time resource record
     */
    public function __construct(stdClass $record) {
        $this->record = $record;
        $resourceclass = "\\mediatimesrc_$record->source\\output\\media_resource";
        $this->resource = new $resourceclass($record);
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
            'canedit' => has_capability('moodle/tag:edit', $context) || $USER->id == $this->record->usermodified,
            'id' => $this->record->id,
            'libraryhome' => new moodle_url('/admin/tool/mediatime/index.php'),
            'resource' => $output->render($this->resource),
            'tags' => $this->tags($output),
        ];
    }

    public function tags($output) {
        return $output->tag_list(
            core_tag_tag::get_item_tags(
                'tool_mediatime',
                'media_resources',
                $this->record->id
            ),
            null,
            'mediatime-tags'
        );
    }

    public function video_url($output) {
        return $this->resource->video_url($output);
    }

    public function image_url($output) {
        return $this->resource->image_url($output);
    }

    public function video_file_content($output) {
        return $this->resource->video_file_content($output);
    }
}
