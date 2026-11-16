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

namespace mediatimesrc_ubicast\task;

use mediatimesrc_ubicast\api;
use mediatimesrc_ubicast\manager;
use mediatimesrc_ubicast\output\media_resource;
use moodle_exception;

/**
 * Update UbiCast Nudgis resources with changes made on Nudgis
 *
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_media extends \core\task\scheduled_task {
    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('updatemedia', 'mediatimesrc_ubicast');
    }

    /**
     * Refresh the stored information of every UbiCast resource
     */
    public function execute() {
        global $DB;

        if (!get_config('mediatimesrc_ubicast', 'enabled')) {
            return;
        }
        try {
            $api = new api();
        } catch (moodle_exception $e) {
            mtrace($e->getMessage());
            return;
        }

        $updated = 0;
        $records = $DB->get_recordset('tool_mediatime', ['source' => 'ubicast'], 'id');
        foreach ($records as $record) {
            $content = json_decode($record->content);
            if (empty($content->oid)) {
                continue;
            }
            try {
                $media = media_resource::fetch_content($api, $content->oid, $content->name ?? $record->name, $content);
            } catch (moodle_exception $e) {
                mtrace("Resource $record->id ($content->oid): " . $e->getMessage());
                continue;
            }

            // Thumbnail links differ on every request; the image changed when its path changed.
            $thumbpath = fn($item) => parse_url($item->thumb ?? '', PHP_URL_PATH);
            $fileschanged = $thumbpath($media) !== $thumbpath($content)
                || array_map([media_resource::class, 'subtitle_filename'], $media->subtitles)
                    != array_map([media_resource::class, 'subtitle_filename'], $content->subtitles ?? []);
            if ($fileschanged) {
                manager::delete_cached_files($record->contextid, $record->id);
            }

            // Ignore fields that change without a change to the video, such as view counts.
            $compare = fn($item) => array_diff_key((array)json_decode(json_encode($item)), array_flip([
                'checked', 'comments', 'comments_last_month', 'comments_last_year', 'thumb', 'views', 'views_last_month',
                'views_last_year',
            ]));
            if ($fileschanged || $compare($media) != $compare($content)) {
                $DB->set_field('tool_mediatime', 'content', json_encode($media), ['id' => $record->id]);
                $updated++;
            }
        }
        $records->close();

        mtrace("Updated $updated UbiCast Nudgis resources.");
    }
}
