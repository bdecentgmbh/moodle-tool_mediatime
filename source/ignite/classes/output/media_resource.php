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

namespace mediatimesrc_ignite\output;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/resourcelib.php");

use moodle_url;
use stdClass;
use renderable;
use renderer_base;
use templatable;

/**
 * Display a resource in the media library
 *
 * @package    mediatimesrc_ignite
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_resource implements renderable, templatable {
    /** @var $context Context */
    protected $context = null;

    /** @var ?stdClass $content Media Time content object */
    protected $content;

    /** @var ?stdClass $record Media Time resource record */
    protected $record;

    /** @var ?stdClass $igniteurl Streamio url for resource */
    protected $igniteurl;

    /** @var ?stdClass $videourl Video url for resource */
    protected $videourl;

    /**
     * Constructor
     *
     * @param stdClass $record Media Time resource record
     */
    public function __construct(stdClass $record) {
        $this->record = $record;
        $this->context = \context::instance_by_id($record->contextid);
        $this->content = json_decode($record->content ?? '{}');
        $this->content->description = shorten_text($this->content->description ?? '', 300);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER;
        $videourl = $this->video_url($output);
        $editurl = new moodle_url('/admin/tool/mediatime/index.php', ['edit' => $this->record->id]);
        $moveurl = new moodle_url('/admin/tool/mediatime/index.php', ['action' => 'move', 'id' => $this->record->id]);
        $removeurl = new moodle_url('/admin/tool/mediatime/index.php', ['delete' => $this->record->id]);
        $playerurl = preg_replace('/(.*)\/videos\/([a-h\d]*).*/', '$1/player/index.html?id=$2', $videourl);

        $content = [
            'elementid' => 'video-' . uniqid(),
            'instance' => json_encode([
                'vimeo_url' => $videourl,
                'controls' => true,
                'responsive' => true,
                'playsinline' => false,
                'autoplay' => false,
                'option_loop' => false,
                'muted' => true,
                'type' => resourcelib_guess_url_mimetype($videourl),
            ]),
            'src' => $playerurl,
            'poster' => $this->image_url($output),
        ] + (array) $this->content;

        return [
            'canedit' => has_capability('tool/mediatime:manage', $this->context) || ($USER->id == $this->record->usermodified),
            'editurl' => $editurl->out(),
            'id' => $this->record->id,
            'libraryhome' => new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $this->record->contextid]),
            'viewlibrary' => has_capability('tool/mediatime:view', $this->context),
            'name' => $this->record->name,
            'moveurl' => $moveurl->out(false),
            'removeurl' => $removeurl->out(),
            'resource' => $this->content,
            'video' => $output->render_from_template('mediatimesrc_ignite/video', $content),
        ];
    }

    /**
     * Return url for poster image
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function image_url(renderer_base $output) {
        return json_decode($this->record->content)->src->thumbnailUrl ?? '';
    }

    /**
     * Return url for video content
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function video_url($output) {
        $this->videourl = '';

        return json_decode($this->record->content)->src->abr->url ?? '';
    }

    /**
     * Return video file content
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function video_file_content($output) {
        $ch = curl_init($this->video_url($output));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
    }
}
