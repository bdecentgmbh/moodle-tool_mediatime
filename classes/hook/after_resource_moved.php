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

namespace tool_mediatime\hook;

use core\hook\stoppable_trait;
use core\context;
use stdClass;

/**
 * Allow plugins to make specific changes when resource is moved to new context
 *
 * @package    tool_mediatime
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allow plugins to make specific changes when resource is moved to new context')]
#[\core\attribute\tags('mediatime')]
class after_resource_moved {
    /**
     * Constructor for the hook
     *
     * @param context_module $context Module context of meeting
     * @param stdClass|cm_info $cm Course module record
     */
    public function __construct(
        /** @var stdClass $record Old resource record */
        public readonly stdClass $record,
        /** @var int $contextid Ne context id */
        public readonly int $contextid
    ) {
    }

    /**
     * Get context
     *
     * @return context
     */
    public function get_context(): context {
        return context::instance_by_id($this->contextid);
    }

    /**
     * Get record
     *
     * @return stdClass
     */
    public function get_record(): stdClass {
        return $this->record;
    }
}
