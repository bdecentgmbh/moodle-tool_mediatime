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

namespace mediatimesrc_ubicast\output;

use core\http_client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for displaying UbiCast Nudgis resources
 *
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_mediatime
 * @covers     \mediatimesrc_ubicast\output\media_resource
 */
final class media_resource_test extends \advanced_testcase {
    /**
     * Configure the source and queue mocked responses
     *
     * @param array $responses Responses to return in order
     */
    protected function mock_responses(array $responses): void {
        set_config('url', 'https://nudgis.example.com', 'mediatimesrc_ubicast');
        set_config('apikey', 'abc-123', 'mediatimesrc_ubicast');
        \core\di::set(http_client::class, new http_client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    }

    /**
     * Create a resource record
     *
     * @param array $content Stored Nudgis media information
     * @return \stdClass
     */
    protected function create_resource(array $content): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('tool_mediatime')->create_resource([
            'source' => 'ubicast',
            'name' => 'Ducks resource',
            'content' => json_encode($content),
        ]);
    }

    /**
     * Playable videos play from the Nudgis download url
     */
    public function test_video_url(): void {
        global $PAGE;

        $this->resetAfterTest();
        $output = $PAGE->get_renderer('core');
        set_config('url', 'https://nudgis.example.com/', 'mediatimesrc_ubicast');
        $record = $this->create_resource(['oid' => 'v1', 'title' => 'Ducks', 'ready' => true, 'playable' => true]);

        $resource = new media_resource($record);
        $url = $resource->video_url($output);

        $this->assertEquals('https://nudgis.example.com/api/v2/download/v1.mp4?playable=yes&quality=high&usage=external', $url);
        $this->assertEquals('video/mp4', resourcelib_guess_url_mimetype($url));
        $this->assertStringEndsWith("/mediatimesrc_ubicast/thumbnail/$record->id/thumb.jpg", $resource->image_url($output));
        $this->assertEquals('Ducks', $resource->get_title());
    }

    /**
     * Videos get a url only once the MP4 exists, checked at most once a minute
     */
    public function test_refresh_processing(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $output = $PAGE->get_renderer('core');
        $clock = $this->mock_clock_with_frozen(1800000000);
        $media = fn($ready) => new Response(200, [], json_encode(['success' => true, 'info' => [
            'oid' => 'v1',
            'title' => 'Ducks',
            'ready' => $ready,
            'description' => '<p>Big &amp; blue</p>',
        ]]));
        $this->mock_responses([
            $media(false),
            $media(true),
            new Response(404, [], json_encode(['error' => 'Page not found (404)'])),
            $media(true),
            new Response(200, [], json_encode(['success' => true, 'url' => 'https://cdn.example.com/media.mp4'])),
        ]);
        $record = $this->create_resource(['oid' => 'v1', 'title' => 'Ducks', 'ready' => false]);
        $load = fn() => new media_resource($DB->get_record('tool_mediatime', ['id' => $record->id]));

        // Still processing.
        $this->assertEquals('', $load()->video_url($output));
        $content = json_decode($DB->get_field('tool_mediatime', 'content', ['id' => $record->id]));
        $this->assertEquals('Big & blue', $content->description);
        $this->assertEquals('Ducks resource', $content->name);

        // No new request within a minute; the mock would run out of responses otherwise.
        $clock->bump(30);
        $this->assertEquals('', $load()->video_url($output));

        // Ready for display, but transcoding has not produced the MP4 yet.
        $clock->bump(31);
        $this->assertEquals('', $load()->video_url($output));

        $clock->bump(61);
        $this->assertNotEmpty($load()->video_url($output));
    }

    /**
     * Thumbnails are downloaded once and kept, because Nudgis links work only once
     */
    public function test_thumbnail_file(): void {
        global $PAGE;

        $this->resetAfterTest();
        $output = $PAGE->get_renderer('core');

        $processing = $this->create_resource(['oid' => 'v2', 'ready' => false]);
        $this->mock_responses([
            new Response(200, [], json_encode(['success' => true, 'info' => ['oid' => 'v2', 'ready' => false]])),
        ]);
        $resource = new media_resource($processing);
        $this->assertStringContainsString('f/video', $resource->image_url($output));
        $this->assertNull($resource->thumbnail_file());

        $this->mock_responses([
            new Response(200, [], json_encode(['success' => true, 'info' => [
                'oid' => 'v1',
                'ready' => true,
                'thumb' => 'https://nudgis.example.com/thumbnails/videos/v1/thumb_medium.jpg?st=abc&e=1800014400',
            ]])),
            new Response(200, ['Content-Type' => 'image/jpeg'], 'jpeg data'),
        ]);
        $record = $this->create_resource(['oid' => 'v1', 'ready' => true, 'playable' => true]);
        $file = (new media_resource($record))->thumbnail_file();
        $this->assertEquals('jpeg data', $file->get_content());

        // The second request uses the stored file; the mock has no responses left.
        $this->assertEquals($file->get_id(), (new media_resource($record))->thumbnail_file()->get_id());
    }
}
