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

namespace mediatimesrc_vimeo\event;

use core\event\base;

/**
 * The resource_updated event class.
 *
 * @package     mediatimesrc_vimeo
 * @category    event
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_updated extends \tool_mediatime\event\resource_updated {
    /**
     * Returns event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_resource_updated', 'mediatimesrc_vimeo');
    }
}
