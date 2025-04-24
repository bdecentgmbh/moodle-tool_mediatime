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

namespace mediatimesrc_vimeo\task;

/**
 * Delete uploaded files to be sent to Vimeo
 *
 * @package     mediatimesrc_vimeo
 * @copyright   2025 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_uploads extends \core\task\scheduled_task {
    /**
     * Get name
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('delete_uploads', 'mediatimesrc_vimeo');
    }

    /**
     * Execute task to delete files
     */
    public function execute() {
        global $DB;

        $contextids = array_unique($DB->get_fieldset_sql(
            "SELECT contextid
               FROM {files}
              WHERE component = 'mediatimesrc_vimeo'
                    AND filearea = 'videofile'
           GROUP BY contextid
           ORDER BY MIN(timecreated)
              LIMIT 100"
        ));
        $fs = get_file_storage();
        foreach($contextids as $contextid) {
            $files = $fs->get_area_files($contextid, 'mediatimesrc_vimeo', 'videofile');
            foreach($files as $file) {
                if ($file->get_timecreated() < time() - HOURSECS) {
                    $file->delete();
                }
            }
        }
    }
}
