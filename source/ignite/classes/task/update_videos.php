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

namespace mediatimesrc_ignite\task;

use mediatimesrc_ignite\api;
use DateTime;

/**
 * Updates videos modified recently
 *
 * @package     mediatimesrc_ignite
 * @copyright   2026 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_videos extends \core\task\scheduled_task {
    /**
     * Get name
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('updatevideos', 'mediatimesrc_ignite');
    }

    /**
     * Execute task
     */
    public function execute() {
        global $CFG, $DB;

        if (empty(get_config('mediatimesrc_ignite', 'enabled'))) {
            mtrace('Plugin disabled');
            return;
        }
        $clock = \core\di::get(\core\clock::class);

        $lasttime = get_config('mediatimesrc_ignite', 'lasttime') ?: 0;
        $currenttime = $clock->time();

        $api = new api();

        $starttime = new DateTime();
        $starttime->setTimestamp($lasttime);

        $query = http_build_query([
            'sortBy' => 'updatedAt',
            'where' => [
                'updatedAt' => [
                    'greater_than' => $starttime->format(DateTime::ATOM),
                ],
            ],
        ]);

        $list = $api->request("/videos?$query");

        $videos = [];
        foreach ($list->docs as $video) {
            $datetime = new \DateTime($video->updatedAt);
            $timestamp = $datetime->getTimestamp();
            mtrace("Update Ignite video $video->id at timestap $timestamp.");
            $videos[$video->id] = $video;
        }

        if (empty($videos)) {
            set_config('lasttime', $currenttime, 'mediatimesrc_ignite');
            return;
        }

        try {
            $transaction = $DB->start_delegated_transaction();
            foreach ($DB->get_records('tool_mediatime', ['source' => 'ignite']) as $record) {
                $content = json_decode($record->content);
                if (!empty($content->id) && key_exists($content->id, $videos)) {
                    mtrace("Updating $record->name");
                    $video = $videos[$content->id];
                    $video->name = $content->name;
                    $record->content = json_encode($video);
                    $record->timemodified = $clock->time();
                    $DB->update_record('tool_mediatime', $record);
                }
            }

            if (empty($list->next)) {
                set_config('lasttime', $currenttime, 'mediatimesrc_ignite');
            } else {
                $starttime->setTimestamp($lasttime);
                set_config('lasttime', $starttime->getTimestamp(), 'mediatimesrc_ignite');
            }

            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
    }
}
