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

namespace tool_mediatime\privacy;

use context_module;
use core_privacy\tests\provider_testcase;
use tool_mediatime\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the Media Time privacy provider.
 *
 * @package   tool_mediatime
 * @copyright 2025 bdecent gmbh <https://bdecent.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_mediatime\privacy\provider
 * @group     tool_mediatime
 */
final class provider_test extends provider_testcase {
    /** @var array */
    protected $users = [];
    /** @var array */
    protected $resources = [];
    /** @var array */
    protected $contexts = [];

    /**
     * Set up for each test.
     */
    public function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $pg = $dg->get_plugin_generator('tool_mediatime');

        $this->users[1] = $dg->create_user();
        $this->users[2] = $dg->create_user();
        $this->users[3] = $dg->create_user();
        $this->users[4] = $dg->create_user();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        $dg->enrol_user($this->users[1]->id, $course->id, $teacherrole->id, 'manual');
        $dg->enrol_user($this->users[2]->id, $course->id, $studentrole->id, 'manual');
        $dg->enrol_user($this->users[3]->id, $course->id, $teacherrole->id, 'manual');
        $dg->enrol_user($this->users[4]->id, $course->id, $studentrole->id, 'manual');

        $coursecontext = \context_course::instance($course->id);
        $this->contexts[1] = \context_system::instance();
        $this->contexts[2] = \context_coursecat::instance($course->category);
        $this->contexts[3] = $coursecontext;

        // User 1.
        $this->setUser($this->users[1]);
        $this->resources[1] = $pg->create_resource(['contextid' => SYSCONTEXTID]);
        $this->resources[2] = $pg->create_resource(['contextid' => $coursecontext->id]);
        $this->resources[3] = $pg->create_resource(['contextid' => $this->contexts[2]->id]);

        // User 2.
        $this->setUser($this->users[2]);
        $this->resources[4] = $pg->create_resource(['contextid' => $coursecontext->id]);

        // User 3.
        $this->setUser($this->users[3]);
        $this->resources[5] = $pg->create_resource(['contextid' => $coursecontext->id]);

        // User 4.
        $this->setUser($this->users[4]);
    }

    /**
     * Test getting the contexts for a user.
     */
    public function test_get_contexts_for_userid(): void {

        // Get contexts for the first user.
        $contextids = provider::get_contexts_for_userid($this->users[1]->id)->get_contextids();
        $this->assertEqualsCanonicalizing([
            $this->contexts[1]->id,
            $this->contexts[2]->id,
            $this->contexts[3]->id,
        ], $contextids);

        // Get contexts for the second user.
        $contextids = provider::get_contexts_for_userid($this->users[2]->id)->get_contextids();
        $this->assertEqualsCanonicalizing([
            $this->contexts[3]->id,
        ], $contextids);

        // Get contexts for the third user.
        $contextids = provider::get_contexts_for_userid($this->users[3]->id)->get_contextids();
        $this->assertEqualsCanonicalizing([
            $this->contexts[3]->id,
        ], $contextids);
    }

    /**
     * Export data for user 1
     */
    public function test_export_user_data1(): void {

        // Export all contexts for the first user.
        $contextids = array_values(array_column($this->contexts, 'id'));

        $appctx = new approved_contextlist($this->users[1], 'tool_mediatime', $contextids);
        provider::export_user_data($appctx);

        // Validate exported data for user 1.
        writer::reset();
        $this->setUser($this->users[1]);
        $context = $this->contexts[1];
        $component = 'tool_mediatime';
        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        $this->export_context_data_for_user($this->users[1]->id, $context, $component);
        $this->assertTrue($writer->has_any_data());

        $subcontext = [
            get_string('privacy:resources', 'tool_mediatime'),
        ];
        $data = $writer->get_data($subcontext);
        $this->assertCount(1, $data->resources);

        // Validate exported data for user 2.
        writer::reset();

        // Export all contexts for the second user.
        $appctx = new approved_contextlist($this->users[2], 'tool_mediatime', $contextids);
        provider::export_user_data($appctx);
        $context = $this->contexts[3];
        writer::reset();
        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        $this->export_context_data_for_user($this->users[2]->id, $context, $component);
        $this->assertTrue($writer->has_any_data());

        $subcontext = [
            get_string('privacy:resources', 'tool_mediatime'),
        ];
        $this->assertCount(1, $data->resources);
    }

    /**
     * Test for delete_data_for_user().
     */
    public function test_delete_data_for_user(): void {
        // User 1.
        $appctx = new approved_contextlist(
            $this->users[1],
            'tool_mediatime',
            [
                $this->contexts[1]->id,
                $this->contexts[2]->id,
                $this->contexts[3]->id,
            ]
        );
        provider::delete_data_for_user($appctx);

        provider::export_user_data($appctx);
        $this->assertTrue(writer::with_context($this->contexts[1])->has_any_data());
        $this->assertTrue(writer::with_context($this->contexts[2])->has_any_data());
        $this->assertTrue(writer::with_context($this->contexts[3])->has_any_data());

        // User 2.
        writer::reset();
        $appctx = new approved_contextlist(
            $this->users[2],
            'tool_mediatime',
            [
                $this->contexts[1]->id,
                $this->contexts[2]->id,
                $this->contexts[3]->id,
            ]
        );
        provider::delete_data_for_user($appctx);

        provider::export_user_data($appctx);
        $this->assertFalse(writer::with_context($this->contexts[1])->has_any_data());
        $this->assertFalse(writer::with_context($this->contexts[2])->has_any_data());
        $this->assertTrue(writer::with_context($this->contexts[3])->has_any_data());
    }

    /**
     * Test for delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->contexts[1]);

        $appctx = new approved_contextlist(
            $this->users[1],
            'tool_mediatime',
            [
                $this->contexts[1]->id,
                $this->contexts[2]->id,
                $this->contexts[3]->id,
            ]
        );
        provider::export_user_data($appctx);
        $this->assertFalse(writer::with_context($this->contexts[1])->has_any_data());
        $this->assertTrue(writer::with_context($this->contexts[2])->has_any_data());
        $this->assertTrue(writer::with_context($this->contexts[3])->has_any_data());

        writer::reset();
        $appctx = new approved_contextlist($this->users[2], 'tool_mediatime', [
            $this->contexts[1]->id,
            $this->contexts[2]->id,
            $this->contexts[2]->id,
        ]);
        provider::export_user_data($appctx);
        $this->assertFalse(writer::with_context($this->contexts[1])->has_any_data());

        writer::reset();
        $appctx = new approved_contextlist($this->users[3], 'tool_mediatime', [$this->contexts[1]->id]);
        provider::export_user_data($appctx);
        $this->assertFalse(writer::with_context($this->contexts[1])->has_any_data());
    }
}
