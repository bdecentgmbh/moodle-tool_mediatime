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

namespace mediatimesrc_ubicast\output;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/resourcelib.php");

use mediatimesrc_ubicast\api;
use moodle_exception;
use moodle_url;
use stdClass;
use renderable;
use renderer_base;
use stored_file;
use templatable;

/**
 * Display a resource in the media library
 *
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_resource implements renderable, templatable {
    /** @var $context Context */
    protected $context = null;

    /** @var ?stdClass $content Media Time content object */
    protected $content;

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
        $this->content = json_decode($record->content ?? '{}') ?? new stdClass();
        $this->refresh();
    }

    /**
     * Update the stored media information while Nudgis processes the video
     */
    protected function refresh() {
        global $DB;

        if (empty($this->content->oid) || !empty($this->content->ready)) {
            return;
        }

        try {
            $media = (new api())->get_media($this->content->oid);
        } catch (moodle_exception $e) {
            return;
        }
        $this->content = self::content_from_media($media, $this->content->name ?? $this->record->name);
        $this->record->content = json_encode($this->content);
        if (!empty($this->record->id)) {
            $DB->set_field('tool_mediatime', 'content', $this->record->content, ['id' => $this->record->id]);
        }
    }

    /**
     * Build the content stored in Media Time from a Nudgis media object
     *
     * @param stdClass $media Media information from Nudgis
     * @param string $name Media Time resource name
     * @return stdClass
     */
    public static function content_from_media(stdClass $media, string $name): stdClass {
        $media->name = $name;
        $media->description = trim(html_to_text($media->description ?? '', 0, false));

        return $media;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $USER;

        $videourl = $this->video_url($output);
        $editurl = new moodle_url('/admin/tool/mediatime/index.php', ['edit' => $this->record->id]);
        $moveurl = new moodle_url('/admin/tool/mediatime/index.php', ['action' => 'move', 'id' => $this->record->id]);
        $removeurl = new moodle_url('/admin/tool/mediatime/index.php', ['delete' => $this->record->id]);
        $this->content->description = shorten_text($this->content->description ?? '', 300);

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
            'poster' => $this->image_url($output),
            'videourl' => $videourl,
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
            'video' => $output->render_from_template('mediatimesrc_ubicast/video', $content),
        ];
    }

    /**
     * Return url for poster image
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function image_url(renderer_base $output) {
        if (empty($this->content->oid) || empty($this->content->ready)) {
            return $output->image_url('f/video', 'core')->out();
        }

        return moodle_url::make_pluginfile_url(
            $this->record->contextid,
            'mediatimesrc_ubicast',
            'thumbnail',
            $this->record->id,
            '/',
            'thumb.jpg'
        )->out(false);
    }

    /**
     * Return the cached thumbnail, downloading it from Nudgis when missing
     *
     * Nudgis thumbnail links can only be used once, so the image is kept as a file.
     *
     * @return ?stored_file
     */
    public function thumbnail_file(): ?stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->record->contextid, 'mediatimesrc_ubicast', 'thumbnail', $this->record->id, 'id', false);
        if ($files) {
            return reset($files);
        }

        if (empty($this->content->oid) || empty($this->content->ready)) {
            return null;
        }

        try {
            $api = new api();
            $media = $api->get_media($this->content->oid);
            if (empty($media->thumb)) {
                return null;
            }
            $image = $api->download($media->thumb);
        } catch (moodle_exception $e) {
            return null;
        }

        return $fs->create_file_from_string([
            'contextid' => $this->record->contextid,
            'component' => 'mediatimesrc_ubicast',
            'filearea' => 'thumbnail',
            'itemid' => $this->record->id,
            'filepath' => '/',
            'filename' => 'thumb.jpg',
        ], $image);
    }

    /**
     * Return url for video content
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function video_url($output) {
        if (empty($this->content->oid) || empty($this->content->ready)) {
            return '';
        }

        return api::video_url($this->content->oid);
    }

    /**
     * Return video file content
     *
     * @param \renderer_base $output
     * @return string MP4 file content
     */
    public function video_file_content($output) {
        if (empty($this->content->oid)) {
            return '';
        }

        return (new api())->download(api::video_url($this->content->oid), 600);
    }

    /**
     * Return resource title
     *
     * @return string
     */
    public function get_title() {
        return $this->content->title ?? $this->record->name;
    }
}
