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
use moodle_exception;

/**
 * Tests for the UbiCast Nudgis API client
 *
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      tool_mediatime
 * @covers     \mediatimesrc_ubicast\api
 */
final class api_test extends \advanced_testcase {
    /** @var array Requests sent to the mocked Nudgis */
    protected array $history = [];

    /**
     * Configure the source and queue mocked responses
     *
     * @param array $responses Responses to return in order
     */
    protected function mock_responses(array $responses): void {
        set_config('url', 'https://nudgis.example.com/', 'mediatimesrc_ubicast');
        set_config('apikey', 'abc-123', 'mediatimesrc_ubicast');

        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        \core\di::set(http_client::class, new http_client(['handler' => $stack]));
    }

    /**
     * Build a JSON response
     *
     * @param array $body Response data
     * @param int $status HTTP status
     * @return Response
     */
    protected static function json(array $body, int $status = 200): Response {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    /**
     * Requests carry the API key and use the portal url
     */
    public function test_request(): void {
        $this->resetAfterTest();
        $this->mock_responses([self::json(['success' => true, 'info' => ['oid' => 'v1', 'title' => 'Ducks']])]);

        $media = (new api())->get_media('v1');

        $this->assertEquals('Ducks', $media->title);
        $request = $this->history[0]['request'];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://nudgis.example.com/api/v2/medias/get/', (string)$request->getUri()->withQuery(''));
        $this->assertStringContainsString('oid=v1', $request->getUri()->getQuery());
        $this->assertEquals('abc-123', $request->getHeaderLine('api-key'));
    }

    /**
     * Missing configuration is reported
     */
    public function test_not_configured(): void {
        $this->resetAfterTest();

        $this->expectException(moodle_exception::class);
        new api();
    }

    /**
     * Error responses become exceptions with the Nudgis message
     */
    public function test_request_error(): void {
        $this->resetAfterTest();
        $this->mock_responses([
            self::json(['success' => false, 'error' => 'Media not found'], 404),
            self::json(['success' => false, 'error' => 'Old style error']),
        ]);
        $api = new api();

        try {
            $api->get_media('v404');
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('Media not found', $e->getMessage());
        }

        $this->expectExceptionMessageMatches('/Old style error/');
        $api->get_media('v200');
    }

    /**
     * Anonymous access is read from the default permissions
     */
    public function test_is_public(): void {
        $this->resetAfterTest();
        $anonymous = fn($permission) => self::json(['success' => true, 'groups' => [
            ['id' => null, 'ref' => 'users-anonymous', 'permissions' => ['can_access_media' => $permission]],
            ['id' => 2, 'ref' => 'users-authenticated', 'permissions' => ['can_access_media' => ['val' => true]]],
        ]]);
        $this->mock_responses([
            $anonymous(['inherit_val' => true, 'inherit_from' => 'Public channel']),
            $anonymous([]),
            $anonymous(['val' => false, 'inherit_val' => true]),
            $anonymous(['val' => true]),
        ]);
        $api = new api();

        $this->assertTrue($api->is_public('v1'));
        $this->assertFalse($api->is_public('v2'));
        $this->assertFalse($api->is_public('v3'));
        $this->assertTrue($api->is_public('v4'));
        $this->assertStringContainsString('recursive=yes', $this->history[0]['request']->getUri()->getQuery());
    }

    /**
     * An empty query lists the latest videos, otherwise Nudgis is searched
     */
    public function test_search_videos(): void {
        $this->resetAfterTest();
        $this->mock_responses([
            self::json(['success' => true, 'items' => [['oid' => 'v1', 'title' => 'Latest']], 'more' => false]),
            self::json(['success' => true, 'videos' => [['oid' => 'v2', 'title' => 'Found']]]),
            self::json(['success' => true, 'videos' => [['oid' => 'v126d6380db22pq9cmp0', 'title' => 'Linked']]]),
        ]);
        $api = new api();

        $this->assertEquals('v1', $api->search_videos('')[0]->oid);
        $this->assertEquals('v2', $api->search_videos('ducks')[0]->oid);
        $this->assertStringContainsString('/api/v2/latest/', (string)$this->history[0]['request']->getUri());
        $this->assertStringContainsString('search=ducks', $this->history[1]['request']->getUri()->getQuery());
        $this->assertStringContainsString('content=v', $this->history[1]['request']->getUri()->getQuery());

        // A pasted link searches for the object id it contains.
        $api->search_videos('https://nudgis.example.com/permalink/v126d6380db22pq9cmp0/iframe/');
        $this->assertStringContainsString('search=v126d6380db22pq9cmp0&', $this->history[2]['request']->getUri()->getQuery());
    }

    /**
     * Players get the download url UbiCast recommends for integrations
     */
    public function test_video_url(): void {
        $this->resetAfterTest();
        set_config('url', 'https://nudgis.example.com/', 'mediatimesrc_ubicast');

        $this->assertEquals(
            'https://nudgis.example.com/api/v2/download/v126d6380db22pq9cmp0.mp4?playable=yes&quality=high&usage=external',
            api::video_url('v126d6380db22pq9cmp0')
        );
    }

    /**
     * Uploads send the file in chunks, complete the upload and create the media
     */
    public function test_add_media(): void {
        $this->resetAfterTest();
        set_config('channel', 'c126d6380df2fs35wf44', 'mediatimesrc_ubicast');
        $this->mock_responses([
            self::json(['success' => true, 'upload_id' => 'up1']),
            self::json(['success' => true, 'upload_id' => 'up1', 'filename' => 'ducks.mp4']),
            self::json(['success' => true, 'oid' => 'v9', 'slug' => 'ducks']),
            self::json(['success' => true, 'info' => ['oid' => 'v9', 'title' => 'Ducks', 'ready' => false]]),
        ]);
        $path = make_request_directory() . '/ducks.mp4';
        file_put_contents($path, str_repeat('x', 1000));

        $media = (new api())->add_media($path, 'ducks.mp4', ['title' => 'Ducks', 'validated' => 'yes']);

        $this->assertEquals('v9', $media->oid);
        $this->assertCount(4, $this->history);

        $upload = $this->history[0]['request'];
        $this->assertStringEndsWith('/api/v2/upload/', (string)$upload->getUri());
        $this->assertEquals('bytes 0-999/1000', $upload->getHeaderLine('Content-Range'));
        $this->assertStringContainsString('filename="ducks.mp4"', (string)$upload->getBody());

        $complete = $this->history[1]['request'];
        $this->assertStringEndsWith('/api/v2/upload/complete/', (string)$complete->getUri());
        parse_str((string)$complete->getBody(), $fields);
        $this->assertEquals(['upload_id' => 'up1', 'expected_size' => '1000'], $fields);

        parse_str((string)$this->history[2]['request']->getBody(), $fields);
        $this->assertEquals('up1', $fields['code']);
        $this->assertEquals('Ducks', $fields['title']);
        $this->assertEquals('yes', $fields['validated']);
        $this->assertEquals('mscid-c126d6380df2fs35wf44', $fields['channel']);
    }

    /**
     * Downloads are limited to the portal
     */
    public function test_download(): void {
        $this->resetAfterTest();
        $this->mock_responses([new Response(200, [], 'image')]);
        $api = new api();

        $this->assertEquals('image', $api->download('https://nudgis.example.com/thumbnails/videos/v1/thumb.jpg?st=a'));
        $this->assertStringNotContainsString('api-key', implode(',', array_keys($this->history[0]['request']->getHeaders())));

        $this->expectException(moodle_exception::class);
        $api->download('https://elsewhere.example.com/thumb.jpg');
    }

    /**
     * File names are made acceptable to the upload API
     */
    public function test_upload_filename(): void {
        $this->assertEquals('ducks.mp4', api::upload_filename('ducks.mp4'));
        $this->assertEquals('ducks.mp4', api::upload_filename('ducks'));
        $this->assertEquals('hidden.mov', api::upload_filename('.hidden.mov'));
    }
}
