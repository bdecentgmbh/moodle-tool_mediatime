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
class media_resource implements renderable, templatable {
    /** @var ?stdClass $content Media Time content object */
    protected $content;

    /** @var $context Context */
    protected $context = null;

    /** @var ?stdClass $record Media Time resource record */
    protected $record;

    /**
     * Constructor
     *
     * @param stdClass $record Media Time resource record
     */
    public function __construct(stdClass $record) {
        $this->record = $record;
        $this->context = \context::instance_by_id($record->contextid);
        $this->content = json_decode($record->content ?? '{}');
        $this->content->description = shorten_text($this->content->description ?? '', 300, null);
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
        $api = new \mediatimesrc_vimeo\api();

        $editurl = new moodle_url('/admin/tool/mediatime/index.php', ['edit' => $this->record->id]);
        $removeurl = new moodle_url('/admin/tool/mediatime/index.php', ['delete' => $this->record->id]);

        $video = new video($content);
        return [
            'canedit' => has_capability('tool/mediatime:manage', $this->context) || ($USER->id == $this->record->usermodified),
            'editurl' => $editurl->out(),
            'id' => $this->record->id,
            'libraryhome' => new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $this->record->contextid]),
            'name' => $this->record->name,
            'removeurl' => $removeurl->out(),
            'resource' => $content,
            'video' => $output->render($video),
        ];
    }

    /**
     * Return url for poster image
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function image_url(renderer_base $output) {
        if (empty(json_decode($this->record->content)->pictures)) {
            return '';
        }
        return json_decode($this->record->content)->pictures->sizes[2]->link;
    }

    /**
     * Return resource title
     *
     * @return string
     */
    public function get_title() {
        return json_decode($this->record->content)->name ?? '';
    }

    /**
     * Return url for video content
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function video_url($output) {
        return json_decode($this->record->content)->link;
    }

    /**
     * Return video file content
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function video_file_content($output) {
        return file_get_contents($this->video_url($output));
    }
}
