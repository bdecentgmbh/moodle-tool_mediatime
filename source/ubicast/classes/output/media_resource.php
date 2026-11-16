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
use context;
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
     * Update the stored media information, at most once a minute, until the video can be played
     */
    protected function refresh() {
        global $DB;

        $clock = \core\di::get(\core\clock::class);
        if (
            empty($this->content->oid)
            || !empty($this->content->playable)
            || ($this->content->checked ?? 0) > $clock->time() - MINSECS
        ) {
            return;
        }

        try {
            $name = $this->content->name ?? $this->record->name;
            $this->content = self::fetch_content(new api(), $this->content->oid, $name, $this->content);
        } catch (moodle_exception $e) {
            return;
        }
        $this->record->content = json_encode($this->content);
        if (!empty($this->record->id)) {
            $DB->set_field('tool_mediatime', 'content', $this->record->content, ['id' => $this->record->id]);
        }
    }

    /**
     * Get the content stored in Media Time for a Nudgis media
     *
     * @param api $api Nudgis API client
     * @param string $oid Media object id
     * @param string $name Media Time resource name
     * @param ?stdClass $previous Content stored before, to keep whether Moodle uploaded the video
     * @return stdClass Nudgis media information with name, subtitles, chapters and whether it can be played
     */
    public static function fetch_content(api $api, string $oid, string $name, ?stdClass $previous = null): stdClass {
        $media = $api->get_media($oid);
        $media->name = $name;
        $media->description = trim(html_to_text($media->description ?? '', 0, false));
        $media->playable = !empty($media->ready) && $api->has_video($oid);
        $media->checked = \core\di::get(\core\clock::class)->time();
        $media->uploaded = !empty($previous->uploaded);
        $media->subtitles = array_map(fn($subtitle) => (object)[
            'id' => $subtitle->id,
            'language' => $subtitle->language ?? '',
            'label' => ($subtitle->title ?? '') ?: ($subtitle->language_display ?? $subtitle->language ?? ''),
            'revision' => $subtitle->revision ?? 0,
            'translated' => !empty($subtitle->auto_translated),
            'file' => $subtitle->file,
        ], $api->subtitles($oid));
        $media->chapters = $api->chapters($oid);

        return $media;
    }

    /**
     * Whether this Moodle may change the video on Nudgis
     *
     * Only videos uploaded from Moodle are changed, by users who may upload,
     * and only when the Nudgis account has the permission.
     *
     * @param stdClass $content Stored content of the resource
     * @param context $context Context of the resource
     * @param string $permission Nudgis permission field, can_edit or can_delete
     * @return bool
     */
    public static function can_change_remote(stdClass $content, context $context, string $permission): bool {
        return !empty($content->uploaded)
            && !empty($content->$permission)
            && has_capability('mediatimesrc/ubicast:upload', $context);
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
            'texttracks' => $this->texttracks(),
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
        if (empty($this->content->oid) || empty($this->content->playable)) {
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

    /**
     * Get chapters as WebVTT file content
     *
     * @return string
     */
    public function chapters() {
        $chapters = array_values((array)($this->content->chapters ?? []));
        if (empty($chapters)) {
            return '';
        }

        $duration = (float)($this->content->duration_s ?? 0);
        $lines = ['WEBVTT'];
        foreach ($chapters as $key => $chapter) {
            $end = $chapters[$key + 1]->time ?? max($duration, $chapter->time + 1);
            $lines[] = '';
            $lines[] = self::vtt_time($chapter->time) . ' --> ' . self::vtt_time($end);
            $lines[] = $chapter->title;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Format a time for WebVTT
     *
     * @param float $seconds Time in seconds
     * @return string
     */
    protected static function vtt_time(float $seconds): string {
        return sprintf('%02d:%02d:%06.3f', floor($seconds / HOURSECS), floor($seconds / MINSECS) % 60, fmod($seconds, MINSECS));
    }

    /**
     * Get text tracks for resource
     *
     * @return array Track objects with type, url, language and label
     */
    public function texttracks(): array {
        $texttracks = [];
        foreach ($this->content->subtitles ?? [] as $subtitle) {
            $texttracks[] = (object)[
                'type' => $this->track_type($subtitle),
                'url' => $this->file_url('texttrack', self::subtitle_filename($subtitle)),
                'language' => self::language_code($subtitle->language),
                'label' => $subtitle->label,
            ];
        }
        if (!empty($this->content->chapters)) {
            $texttracks[] = (object)[
                'type' => 'chapters',
                'url' => $this->file_url('chapters', 'chapters.vtt'),
                'language' => '',
                'label' => '',
            ];
        }

        return $texttracks;
    }

    /**
     * Get number of text tracks for resource
     *
     * @return int
     */
    public function texttrack_count(): int {
        return count($this->texttracks());
    }

    /**
     * Copy text tracks into draft areas for Video Time
     *
     * @return array[array] Draft item id, type and language of each track
     */
    public function texttrack_files(): array {
        global $USER;

        $fs = get_file_storage();
        $languages = get_string_manager()->get_list_of_translations();
        $draft = fn($filename) => [
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => $filename,
        ];

        $texttracks = [];
        foreach ($this->content->subtitles ?? [] as $subtitle) {
            if (!$file = $this->subtitle_file(self::subtitle_filename($subtitle))) {
                continue;
            }
            $fileinfo = $draft($file->get_filename());
            $fs->create_file_from_storedfile($fileinfo, $file);
            $language = self::language_code($subtitle->language);
            $texttracks[] = [
                'texttrack' => $fileinfo['itemid'],
                'type' => $this->track_type($subtitle),
                'srclang' => key_exists($language, $languages) ? $language : '',
            ];
        }
        if ($chapters = $this->chapters()) {
            $fileinfo = $draft('chapters.vtt');
            $fs->create_file_from_string($fileinfo, $chapters);
            $texttracks[] = [
                'texttrack' => $fileinfo['itemid'],
                'type' => 'chapters',
                'srclang' => '',
            ];
        }

        return $texttracks;
    }

    /**
     * Return the cached subtitle file, downloading it from Nudgis when missing
     *
     * @param string $filename File name from subtitle_filename()
     * @return ?stored_file
     */
    public function subtitle_file(string $filename): ?stored_file {
        foreach ($this->content->subtitles ?? [] as $subtitle) {
            if (self::subtitle_filename($subtitle) !== $filename) {
                continue;
            }
            $fileinfo = [
                'contextid' => $this->record->contextid,
                'component' => 'mediatimesrc_ubicast',
                'filearea' => 'texttrack',
                'itemid' => $this->record->id,
                'filepath' => '/',
                'filename' => $filename,
            ];
            $fs = get_file_storage();
            if ($file = $fs->get_file(...array_values($fileinfo))) {
                return $file;
            }
            try {
                return $fs->create_file_from_string($fileinfo, (new api())->download($subtitle->file));
            } catch (moodle_exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * File name for a subtitle; it changes with each Nudgis revision
     *
     * @param stdClass $subtitle Stored subtitle information
     * @return string
     */
    public static function subtitle_filename(stdClass $subtitle): string {
        return "subtitle_{$subtitle->id}_{$subtitle->revision}.vtt";
    }

    /**
     * Track kind: captions in the language of the video, subtitles for translations
     *
     * @param stdClass $subtitle Stored subtitle information
     * @return string
     */
    protected function track_type(stdClass $subtitle): string {
        $language = $this->content->language ?? '';
        if ($language ? $subtitle->language !== $language : !empty($subtitle->translated)) {
            return 'subtitles';
        }

        return 'captions';
    }

    /**
     * Url of a file served by this plugin for the resource
     *
     * @param string $filearea File area
     * @param string $filename File name
     * @return string
     */
    protected function file_url(string $filearea, string $filename): string {
        return moodle_url::make_pluginfile_url(
            $this->record->contextid,
            'mediatimesrc_ubicast',
            $filearea,
            $this->record->id,
            '/',
            $filename
        )->out(false);
    }

    /**
     * Two letter language code for a Nudgis language code
     *
     * Nudgis uses ISO 639-2/B codes, Moodle's language list ISO 639-2/T.
     *
     * @param string $code Nudgis language code, for example ger
     * @return string Two letter code, or empty when unknown
     */
    public static function language_code(string $code): string {
        $code = [
            'alb' => 'sqi', 'arm' => 'hye', 'baq' => 'eus', 'bur' => 'mya', 'chi' => 'zho',
            'cze' => 'ces', 'dut' => 'nld', 'fre' => 'fra', 'geo' => 'kat', 'ger' => 'deu',
            'gre' => 'ell', 'ice' => 'isl', 'mac' => 'mkd', 'mao' => 'mri', 'may' => 'msa',
            'per' => 'fas', 'rum' => 'ron', 'slo' => 'slk', 'tib' => 'bod', 'wel' => 'cym',
        ][$code] ?? $code;
        $stringmanager = get_string_manager();
        $names = $stringmanager->get_list_of_languages('en', 'iso6392');
        if (empty($names[$code])) {
            return '';
        }

        return (string)array_search($names[$code], $stringmanager->get_list_of_languages('en', 'iso6391'));
    }
}
