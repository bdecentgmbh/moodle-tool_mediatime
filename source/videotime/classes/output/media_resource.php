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
 * @package    mediatimesrc_videotime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_videotime\output;

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
    /** @var ?stdClass $content Media Time content object */
    protected $content;

    /** @var $context System context */
    protected $context = null;

    /** @var $context Poster image url */
    protected $poster = null;

    /** @var ?stdClass $record Media Time resource record */
    protected $record;

    /** @var $context Video url */
    protected $videourl = null;

    /**
     * Constructor
     *
     * @param stdClass $record Media Time resource record
     */
    public function __construct(stdClass $record) {
        $this->record = $record;
        if (!empty($record)) {
            $this->context = \context::instance_by_id($record->contextid);
        } else {
            $this->context = \context_system::instance();
        }
        $this->content = json_decode($record->content ?? '{}') ?? new stdClass();
        $this->content->description = shorten_text($this->content->description ?? '{}', 300);
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

        $content = [
            'elementid' => 'video-' . uniqid(),
            'instance' => json_encode([
                'autoplay' => false,
                'controls' => true,
                'playsinline' => false,
                'muted' => true,
                'option_loop' => false,
                'responsive' => true,
                'type' => resourcelib_guess_url_mimetype($videourl),
                'vimeo_url' => $videourl,
            ]),
            'poster' => $this->image_url($output),
        ];

        return [
            'canedit' => has_capability('moodle/tag:edit', $this->context) || $USER->id == $this->record->usermodified,
            'id' => $this->record->id,
            'libraryhome' => new moodle_url('/admin/tool/mediatime/index.php', ['contextid' => $this->record->contextid]),
            'viewlibrary' => has_capability('tool/mediatime:view', $this->context),
            'name' => $this->record->name,
            'editurl' => $editurl->out(),
            'moveurl' => $moveurl->out(false),
            'removeurl' => $removeurl->out(),
            'resource' => json_decode($this->record->content),
            'video' => $output->render_from_template('mediatimesrc_videotime/video', $content),
        ];
    }

    /**
     * Return url for video content
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function image_url($output) {

        $fs = get_file_storage();
        $this->poster = $output->image_url('f/video', 'core');
        foreach ($fs->get_area_files($this->context->id, 'mediatimesrc_videotime', 'posterimage', $this->record->id) as $file) {
            if (!$file->is_directory()) {
                $this->poster = moodle_url::make_pluginfile_url(
                    $this->context->id,
                    'mediatimesrc_videotime',
                    'posterimage',
                    $this->record->id,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
        }

        return $this->poster;
    }

    /**
     * Return url for poster image
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function video_url($output) {
        $this->videourl = '';

        $fs = get_file_storage();
        foreach ($fs->get_area_files($this->context->id, 'mediatimesrc_videotime', 'videofile', $this->record->id) as $file) {
            if (!$file->is_directory()) {
                $this->videourl = moodle_url::make_pluginfile_url(
                    $this->context->id,
                    'mediatimesrc_videotime',
                    'videofile',
                    $this->record->id,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
        }

        return $this->videourl;
    }

    /**
     * Return video file content
     *
     * @param \renderer_base $output
     * @return string url
     */
    public function video_file_content($output) {
        $this->videourl = '';

        $fs = get_file_storage();
        foreach ($fs->get_area_files($this->context->id, 'mediatimesrc_videotime', 'videofile', $this->record->id) as $file) {
            if (!$file->is_directory()) {
                return $file->get_content();
            }
        }

        return $this->videourl;
    }
}
