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

namespace mediatimesrc_ubicast;

use core\http_client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use mod_videotime\hook\dndupload_register;

/**
 * Tests for the UbiCast Nudgis source manager
 *
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_mediatime
 * @covers     \mediatimesrc_ubicast\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Create a resource in a course with a cached thumbnail and subtitle
     *
     * @param array $content Stored Nudgis media information
     * @return \stdClass
     */
    protected function create_resource(array $content): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $record = $this->getDataGenerator()->get_plugin_generator('tool_mediatime')->create_resource([
            'contextid' => \context_course::instance($course->id)->id,
            'source' => 'ubicast',
            'content' => json_encode($content + ['oid' => 'v1', 'ready' => true, 'playable' => true]),
        ]);
        foreach (['thumbnail' => 'thumb.jpg', 'texttrack' => 'subtitle_8_1.vtt'] as $filearea => $filename) {
            get_file_storage()->create_file_from_string([
                'contextid' => $record->contextid,
                'component' => 'mediatimesrc_ubicast',
                'filearea' => $filearea,
                'itemid' => $record->id,
                'filepath' => '/',
                'filename' => $filename,
            ], 'content');
        }

        return $record;
    }

    /**
     * Deleting removes the resource and its cached files, and only removes uploaded videos on Nudgis
     */
    public function test_delete_resource(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('url', 'https://nudgis.example.com', 'mediatimesrc_ubicast');
        set_config('apikey', 'abc-123', 'mediatimesrc_ubicast');
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode(['success' => true]))]));
        $stack->push(Middleware::history($history));
        \core\di::set(http_client::class, new http_client(['handler' => $stack]));

        $selected = $this->create_resource(['can_delete' => true]);
        (new manager($selected))->delete_resource(true);
        $this->assertFalse($DB->record_exists('tool_mediatime', ['id' => $selected->id]));
        $this->assertTrue(get_file_storage()->is_area_empty($selected->contextid, 'mediatimesrc_ubicast', false, $selected->id));
        $this->assertCount(0, $history);

        $uploaded = $this->create_resource(['can_delete' => true, 'uploaded' => true]);
        (new manager($uploaded))->delete_resource(true);
        $this->assertFalse($DB->record_exists('tool_mediatime', ['id' => $uploaded->id]));
        $this->assertCount(1, $history);
        $this->assertStringEndsWith('/api/v2/medias/delete/', (string)$history[0]['request']->getUri());
    }

    /**
     * Drag and drop is offered only when enabled and an upload channel is set
     */
    public function test_dndupload_register(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'mediatimesrc_ubicast');

        $extensions = function () {
            $hook = new dndupload_register();
            manager::dndupload_register($hook);
            return $hook->get_extensions();
        };

        set_config('channel', 'c126d6380df2fs35wf44', 'mediatimesrc_ubicast');
        $this->assertEmpty($extensions());

        set_config('enabledraganddrop', 1, 'mediatimesrc_ubicast');
        $this->assertContains('mp4', $extensions());

        set_config('channel', '', 'mediatimesrc_ubicast');
        $this->assertEmpty($extensions());
    }
}
