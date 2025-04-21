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

namespace mod_plenum;

/**
 * PHPUnit Media Time generator testcase
 *
 * @package    tool_mediatime
 * @category   test
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_mediatime_generator
 * @group      tool_mediatime
 */
class generator_test extends \advanced_testcase {
    /**
     * Test Media Time resource creation.
     */
    public function test_generator() {
        global $DB;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('tool_mediatime'));

        $course = $this->getDataGenerator()->create_course();

        /** @var tool_mediatime_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mediatime');
        $this->assertInstanceOf('tool_mediatime_generator', $generator);

        $generator->create_resource(['contextid' => \context_course::instance($course->id)->id]);
        $generator->create_resource(['contextid' => \context_coursecat::instance($course->category)->id]);
        $resource = $generator->create_resource(['contextid' => SYSCONTEXTID]);
        $this->assertEquals(3, $DB->count_records('tool_mediatime'));

        $this->assertEquals($resource->contextid, SYSCONTEXTID);
    }
}
