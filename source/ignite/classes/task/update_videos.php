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

        $query = http_build_query([
            'where' => [
                'updatedAt' => [
                    'greater_than' => $lasttime,
                ],
            ],
            'sort' => 'updatedAt',
            'limit' => 100,
        ]);

        $list = $api->request("/videos?$query");

        $videos = [];
        foreach ($list->docs as $video) {
            $datetime = new \DateTime($video->updatedAt);
            $timestamp = $datetime->getTimestamp();
            $lasttime = $video->updatedAt;
            mtrace("Ignite video $video->id updated at timestap $timestamp.");
            $videos[$video->id] = $video;
        }

        if (empty($videos)) {
            return;
        }

        [$sql, $params] = $DB->get_in_or_equal(array_keys($videos), SQL_PARAMS_NAMED);

        try {
            $transaction = $DB->start_delegated_transaction();
            $rs = $DB->get_recordset_select('tool_mediatime', "source = :source AND id IN (
                SELECT resourceid
                  FROM {mediatimesrc_ignite}
                 WHERE igniteid $sql
            )", $params + ['source' => 'ignite']);

            foreach ($rs as $record) {
                $content = json_decode($record->content);
                if (!empty($content->id) && key_exists($content->id, $videos)) {
                    $video = $videos[$content->id];
                    $videoupdated = new DateTime($video->updatedAt);
                    if ($video->updatedAt != $content->updatedAt ?? '') {
                        mtrace("Updating $record->name");
                        $video->name = $content->name;
                        $record->content = json_encode($video);
                        $record->timemodified = $clock->time();
                        $DB->update_record('tool_mediatime', $record);
                    }
                }
            }
            $rs->close();

            set_config('lasttime', $lasttime, 'mediatimesrc_ignite');

            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
    }
}
