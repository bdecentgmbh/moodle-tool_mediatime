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

declare(strict_types=1);

namespace tool_mediatime\reportbuilder\datasource;

use core\context\{course, coursecat, user};
use core_reportbuilder_generator;
use core_reportbuilder\local\filters\{boolean_select, date, filesize, select, text};
use core_reportbuilder\tests\core_reportbuilder_testcase;

/**
 * Unit tests for media resources datasource
 *
 * @package     tool_mediatime
 * @copyright   2025 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \tool_mediatime\reportbuilder\datasource\media_resources
 * @group       tool_mediatime
 */
final class media_resources_test extends core_reportbuilder_testcase {

    /**
     * Test default datasource
     */
    public function test_datasource_default(): void {
        $this->resetAfterTest();

        $coursecat = $this->getDataGenerator()->create_category();
        $coursecatcontext = \context_coursecat::instance($coursecat->id);
        $course = $this->getDataGenerator()->create_course(['category' => $coursecat->id]);
        $coursecontext = course::instance($course->id);

        $user = $this->getDataGenerator()->create_user();
        $usercontext = user::instance($user->id);

        $this->setUser($user);
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mediatime');
        $generator->create_resource(['contextid' => $coursecontext->id]);
        $generator->create_resource(['contextid' => $coursecatcontext->id]);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Media resources', 'source' => media_resources::class, 'default' => 1]);

        $content = $this->get_custom_report_content($report->get('id'));

        $this->assertCount(2, $content);

        // Default columns are context, user, name, time created. Sorted by context and time created.
        [$contextname, $userfullname, $filename, $timecreated] = array_values($content[0]);
        $this->assertEquals($coursecatcontext->get_context_name(), $contextname);
        $this->assertEquals(fullname($user), $userfullname);
        $this->assertEquals('Resource 1', $filename);
        $this->assertNotEmpty($timecreated);

        [$contextname, $userfullname, $filename, $timecreated] = array_values($content[1]);
        $this->assertEquals($coursecontext->get_context_name(), $contextname);
        $this->assertEquals(fullname($user), $userfullname);
        $this->assertEquals('Resource 1', $filename);
        $this->assertNotEmpty($timecreated);
    }

    /**
     * Test datasource columns that aren't added by default
     */
    public function test_datasource_non_default_columns(): void {
        global $OUTPUT;

        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $categorycontext = coursecat::instance($category->id);

        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $coursecontext = course::instance($course->id);

        $user = $this->getDataGenerator()->create_user();
        $usercontext = user::instance($user->id);

        $this->setUser($user);
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mediatime');
        $generator->create_resource(['contextid' => $coursecontext->id]);
        $generator->create_resource(['contextid' => $categorycontext->id]);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Media resources', 'source' => media_resources::class, 'default' => 0]);

        // Consistent order, sorted by context and content hash.
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'context:link',
            'sortenabled' => 1, 'sortorder' => 1]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'context:name']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'context:level']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'context:path']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'context:parent']);

        $content = $this->get_custom_report_content($report->get('id'));

        $this->assertEquals([
            [
                "<a href=\"{$categorycontext->get_url()}\">{$categorycontext->get_context_name()}</a>",
                $categorycontext->get_context_name(),
                'Category',
                $categorycontext->path,
                'System',
            ],
            [
                "<a href=\"{$coursecontext->get_url()}\">{$coursecontext->get_context_name()}</a>",
                $coursecontext->get_context_name(),
                'Course',
                $coursecontext->path,
                $categorycontext->get_context_name(),
            ],
        ], array_map('array_values', $content));
    }

    /**
     * Data provider for {@see test_datasource_filters}
     *
     * @return array[]
     */
    public static function datasource_filters_provider(): array {
        return [
            // File.
            'Filter name' => ['media_resource:name', [
                'media_resource:name_operator' => text::IS_EQUAL_TO,
                'media_resource:name_value' => 'Resource 1',
            ], 1],
            'Filter type' => ['media_resource:source', [
                'media_resource:source_operator' => text::IS_EQUAL_TO,
                'media_resource:source_value' => 'videotime',
            ], 1],
            'Filter type (non match)' => ['media_resource:source', [
                'media_resource:source_operator' => text::IS_EQUAL_TO,
                'media_resource:source_value' => 'streamio',
            ], 0],
            'Filter time created' => ['media_resource:timecreated', [
                'media_resource:timecreated_operator' => date::DATE_RANGE,
                'media_resource:timecreated_from' => 1622502000,
            ], 1],
            'Filter time created (non match)' => ['media_resource:timecreated', [
                'media_resource:timecreated_operator' => date::DATE_RANGE,
                'media_resource:timecreated_to' => 1622502000,
            ], 0],

            // Context.
            'Context level' => ['context:level', [
                'context:level_operator' => select::EQUAL_TO,
                'context:level_value' => CONTEXT_COURSE,
            ], 1],
            'Context level (no match)' => ['context:level', [
                'context:level_operator' => select::EQUAL_TO,
                'context:level_value' => CONTEXT_BLOCK,
            ], 0],
            'Context path' => ['context:path', [
                'context:path_operator' => text::STARTS_WITH,
                'context:path_value' => '/1/',
            ], 1],
            'Context path (no match)' => ['context:path', [
                'context:path_operator' => text::STARTS_WITH,
                'context:path_value' => '/1/2/3/',
            ], 0],

            // User.
            'Filter user' => ['user:username', [
                'user:username_operator' => text::IS_EQUAL_TO,
                'user:username_value' => 'alfie',
            ], 1],
            'Filter user (no match)' => ['user:username', [
                'user:username_operator' => text::IS_EQUAL_TO,
                'user:username_value' => 'lionel',
            ], 0],
        ];
    }

    /**
     * Test datasource filters
     *
     * @param string $filtername
     * @param array $filtervalues
     * @param int $expectmatchcount
     *
     * @dataProvider datasource_filters_provider
     */
    public function test_datasource_filters(
        string $filtername,
        array $filtervalues,
        int $expectmatchcount
    ): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user(['username' => 'alfie']);
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = course::instance($course->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mediatime');
        $generator->create_resource(['contextid' => $coursecontext->id]);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');

        // Create report containing single column, and given filter.
        $report = $generator->create_report(['name' => 'Media Time', 'source' => media_resources::class, 'default' => 0]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'context:name']);

        // Add filter, set it's values.
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => $filtername]);
        $content = $this->get_custom_report_content($report->get('id'), 0, $filtervalues);

        $this->assertCount($expectmatchcount, $content);
    }

    /**
     * Stress test datasource
     *
     * In order to execute this test PHPUNIT_LONGTEST should be defined as true in phpunit.xml or directly in config.php
     */
    public function test_stress_datasource(): void {
        if (!PHPUNIT_LONGTEST) {
            $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = course::instance($course->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mediatime');
        $generator->create_resource(['contextid' => $coursecontext->id]);

        $this->datasource_stress_test_columns(media_resources::class);
        $this->datasource_stress_test_columns_aggregation(media_resources::class);
        $this->datasource_stress_test_conditions(media_resources::class, 'media_resource:type');
    }
}
