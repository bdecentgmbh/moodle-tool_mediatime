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

namespace mediatimesrc_videotime;

/**
 * PHPUnit Media Time vime source manager testcase
 *
 * @package    mediatimesrc_videotime
 * @category   test
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mediatimesrc_videotime\manager
 * @group      tool_mediatime
 */
final class manager_test extends \advanced_testcase {
    /**
     * Test Media Time resource creation.
     */
    public function test_delete(): void {
        global $DB;

        $fs = get_file_storage();

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('tool_mediatime'));

        $clock = $this->mock_clock_with_frozen();

        $course = $this->getDataGenerator()->create_course();

        /** @var tool_mediatime_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mediatime');

        $resource = $generator->create_resource([
            'contextid' => \context_course::instance($course->id)->id,
            'type' => 'videotime',
            'content' => json_encode([
                'name' => 'Sample',
            ]),
        ]);
        $fs->create_file_from_string([
            'contextid' => \context_course::instance($course->id)->id,
            'component' => 'mediatimesrc_videotime',
            'filename' => 'Video file.mp4',
            'filepath' => '/',
            'filearea' => 'videofile',
            'itemid' => 0,
        ], 'xxx');
        $generator->create_resource(['contextid' => \context_coursecat::instance($course->category)->id]);
        $generator->create_resource(['contextid' => SYSCONTEXTID]);
        $this->assertEquals(3, $DB->count_records('tool_mediatime'));

        $manager = new manager($resource);
        $manager->delete_resource();

        $this->assertEquals(2, $DB->count_records('tool_mediatime'));
        $this->assertFalse($DB->get_record('tool_mediatime', ['id' => $resource->id]));

        // Make sure files are deleted.
        $files = $fs->get_area_files(
            \context_course::instance($course->id)->id,
            'mediatimesrc_videotime',
            'videofile',
            $resource->id
        );
        $this->assertEquals(0, count($files));
    }
}
