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

namespace mediatimesrc_ubicast\task;

use core\http_client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for the task that updates UbiCast Nudgis resources
 *
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_mediatime
 * @covers     \mediatimesrc_ubicast\task\update_media
 */
final class update_media_test extends \advanced_testcase {
    /**
     * Responses Nudgis gives for one refresh
     *
     * @param array $info Media information
     * @return Response[]
     */
    protected static function refresh(array $info): array {
        return [
            new Response(200, [], json_encode(['success' => true, 'info' => $info + ['ready' => true]])),
            new Response(200, [], json_encode(['success' => true, 'url' => 'https://cdn.example.com/media.mp4'])),
            new Response(200, [], json_encode(['success' => true, 'subtitles' => []])),
            new Response(200, [], json_encode(['success' => true, 'chapters' => []])),
        ];
    }

    /**
     * Changes on Nudgis are stored and outdated cached files removed
     */
    public function test_execute(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enabled', 1, 'mediatimesrc_ubicast');
        set_config('url', 'https://nudgis.example.com', 'mediatimesrc_ubicast');
        set_config('apikey', 'abc-123', 'mediatimesrc_ubicast');
        \core\di::set(http_client::class, new http_client(['handler' => HandlerStack::create(new MockHandler(array_merge(
            self::refresh([
                'oid' => 'v1',
                'title' => 'Same',
                'thumb' => 'https://nudgis.example.com/t/a/thumb.jpg?st=new',
                'views' => 9,
            ]),
            self::refresh(['oid' => 'v2', 'title' => 'New title', 'thumb' => 'https://nudgis.example.com/t/b2/thumb.jpg?st=new'])
        )))]));

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mediatime');
        $stored = fn($oid, $title, $thumb) => json_encode([
            'oid' => $oid, 'title' => $title, 'ready' => true, 'thumb' => $thumb, 'description' => '', 'name' => 'Resource',
            'playable' => true, 'uploaded' => true, 'subtitles' => [], 'chapters' => [], 'views' => 1, 'checked' => 1,
        ]);
        $same = $generator->create_resource([
            'source' => 'ubicast',
            'name' => 'Resource',
            'content' => $stored('v1', 'Same', 'https://nudgis.example.com/t/a/thumb.jpg?st=old'),
        ]);
        $changed = $generator->create_resource([
            'source' => 'ubicast',
            'name' => 'Resource',
            'content' => $stored('v2', 'Old title', 'https://nudgis.example.com/t/b/thumb.jpg?st=old'),
        ]);
        $fs = get_file_storage();
        foreach ([$same, $changed] as $record) {
            $fs->create_file_from_string([
                'contextid' => $record->contextid,
                'component' => 'mediatimesrc_ubicast',
                'filearea' => 'thumbnail',
                'itemid' => $record->id,
                'filepath' => '/',
                'filename' => 'thumb.jpg',
            ], 'image');
        }

        $this->expectOutputRegex('/Updated 1 UbiCast Nudgis resources/');
        (new update_media())->execute();

        // A new view count and a new thumbnail link are no change.
        $this->assertEquals($same->content, $DB->get_field('tool_mediatime', 'content', ['id' => $same->id]));
        $this->assertFalse($fs->is_area_empty($same->contextid, 'mediatimesrc_ubicast', 'thumbnail', $same->id));

        $content = json_decode($DB->get_field('tool_mediatime', 'content', ['id' => $changed->id]));
        $this->assertEquals('New title', $content->title);
        $this->assertTrue($content->uploaded);
        $this->assertTrue($fs->is_area_empty($changed->contextid, 'mediatimesrc_ubicast', 'thumbnail', $changed->id));
    }
}
