<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_mediatime\event;

use core\event\base;

/**
 * The resource_updated event class.
 *
 * @package     tool_mediatime
 * @category    event
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_updated extends base {

    // For more information about the Events API please visit {@link https://docs.moodle.org/dev/Events_API}.

    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'tool_mediatime';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['crud'] = 'u';
    }

    /**
     * Returns event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_resource_updated', 'tool_mediatime');
    }

    /**
     * Get the event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' deleted a mediatime resource with id '{$this->objectid}'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/admin/tool/mediatime/index.php', [
            'id' => $this->objectid,
        ]);
    }

    /**
     * Get the object ID mapping.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'tool_mediatime', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * No mapping required for this event because this event is not backed up.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
