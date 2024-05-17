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
 * @package    mediatimesrc_streamio
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_streamio\output;

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
        $context = \context_system::instance();
        $videourl = $this->video_url($output);
        if (substr($videourl, -5) == '.m3u8') {
            $type = 'video/x-mpegURL';
	} else {
            $type = resourcelib_guess_url_mimetype($videourl);
        }

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
                'type' => $type,
            ]),
            'poster' => $this->image_url($output),
        ];

        return [
            'canedit' => has_capability('tool/mediatime:manage', $context) || ($USER->id == $this->record->usermodified),
            'id' => $this->record->id,
            'libraryhome' => new moodle_url('/admin/tool/mediatime/index.php'),
            'resource' => $content,
            'video' => $output->render_from_template('mediatimesrc_streamio/video', $content),
        ];
    }

    /**
     * Return url for poster image
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function image_url(renderer_base $output) {
        return 'https://' . json_decode($this->record->content)->screenshot->normal;
    }

    /**
     * Return url for video content
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function video_url($output) {
        $this->videourl = '';

        $id = json_decode($this->record->content)->id;

        return "https://streamio.com/api/v1/videos/$id/public_show.m3u8";
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
