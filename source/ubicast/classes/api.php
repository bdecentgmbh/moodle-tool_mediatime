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
use GuzzleHttp\Exception\GuzzleException;
use moodle_exception;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * UbiCast Nudgis API client
 *
 * @package    mediatimesrc_ubicast
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var int Chunk size recommended by Nudgis for chunked uploads */
    public const CHUNK_SIZE = 26214400;

    /** @var string Nudgis portal url without trailing slash */
    protected string $url;

    /** @var string Nudgis API key */
    protected string $apikey;

    /** @var http_client HTTP client */
    protected http_client $client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->url = rtrim((string)get_config('mediatimesrc_ubicast', 'url'), '/');
        $this->apikey = (string)get_config('mediatimesrc_ubicast', 'apikey');

        if (empty($this->url) || empty($this->apikey)) {
            throw new moodle_exception('credentialsnotconfigured', 'mediatimesrc_ubicast');
        }

        $this->client = \core\di::get(http_client::class);
    }

    /**
     * Return the portal url
     *
     * @return string
     */
    public function get_url(): string {
        return $this->url;
    }

    /**
     * Send a request to the Nudgis API and return the raw response
     *
     * @param string $endpoint API call relative to /api/v2/, for example 'medias/get'
     * @param array $params Query parameters for GET or form data for POST
     * @param string $method GET or POST
     * @param array $options Additional Guzzle request options
     * @return ResponseInterface
     */
    protected function send(string $endpoint, array $params = [], string $method = 'GET', array $options = []): ResponseInterface {
        $url = $this->url . '/api/v2/' . trim($endpoint, '/') . '/';
        $options['headers'] = ($options['headers'] ?? []) + [
            'api-key' => $this->apikey,
            'Accept-Language' => substr(current_language(), 0, 2),
        ];
        $options['http_errors'] = false;
        $options['timeout'] = $options['timeout'] ?? 30;
        if ($method == 'GET') {
            $options['query'] = $params;
        } else if (!empty($params) && empty($options['multipart'])) {
            $options['form_params'] = $params;
        }

        try {
            return $this->client->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new moodle_exception('apierror', 'mediatimesrc_ubicast', '', $e->getMessage());
        }
    }

    /**
     * Submit request to Nudgis and return decoded response
     *
     * @param string $endpoint API call relative to /api/v2/, for example 'medias/get'
     * @param array $params Query parameters for GET or form data for POST
     * @param string $method GET or POST
     * @param array $options Additional Guzzle request options
     * @return stdClass
     */
    public function request(string $endpoint, array $params = [], string $method = 'GET', array $options = []): stdClass {
        $response = $this->send($endpoint, $params, $method, $options);
        $result = json_decode((string)$response->getBody());

        // Nudgis before version 11 reports errors with status 200 and success false.
        if (!is_object($result) || $response->getStatusCode() >= 400 || (($result->success ?? true) === false)) {
            $message = $result->error ?? $result->message ?? $response->getReasonPhrase();
            if (is_array($message) || is_object($message)) {
                $message = json_encode($message);
            }
            throw new moodle_exception('apierror', 'mediatimesrc_ubicast', '', $message);
        }

        return $result;
    }

    /**
     * Get information about a media
     *
     * @param string $oid Media object id
     * @return stdClass
     */
    public function get_media(string $oid): stdClass {
        return $this->request('medias/get', ['oid' => $oid, 'full' => 'yes'])->info;
    }

    /**
     * Change the title or description of a media
     *
     * @param string $oid Media object id
     * @param array $fields Fields to change, for example title and description
     */
    public function edit_media(string $oid, array $fields): void {
        $this->request('medias/edit', ['oid' => $oid] + $fields, 'POST');
    }

    /**
     * Delete a media; Nudgis keeps it in its recycle bin when that is enabled
     *
     * @param string $oid Media object id
     */
    public function delete_media(string $oid): void {
        $this->request('medias/delete', ['oid' => $oid], 'POST');
    }

    /**
     * Get the validated subtitles of a media
     *
     * @param string $oid Media object id
     * @return array Subtitle objects with id, language, revision and file url
     */
    public function subtitles(string $oid): array {
        $subtitles = $this->request('subtitles', ['oid' => $oid])->subtitles ?? [];

        return array_values(array_filter($subtitles, fn($subtitle) => !empty($subtitle->validated) && !empty($subtitle->file)));
    }

    /**
     * Get the validated chapters of a media
     *
     * @param string $oid Media object id
     * @return array Chapter objects with title and time in seconds
     */
    public function chapters(string $oid): array {
        $chapters = [];
        foreach ($this->request('annotations/chapters/list', ['oid' => $oid])->chapters ?? [] as $chapter) {
            if (!empty($chapter->validated)) {
                $chapters[] = (object)[
                    'title' => $chapter->title ?? '',
                    'time' => (float)($chapter->time_in_sec ?? 0),
                ];
            }
        }
        usort($chapters, fn($a, $b) => $a->time <=> $b->time);

        return $chapters;
    }

    /**
     * Search videos visible to the API account
     *
     * A pasted object id or Nudgis link finds that video.
     *
     * @param string $query Search words, empty for the latest videos
     * @param int $limit Maximum number of videos
     * @return array List of media objects
     */
    public function search_videos(string $query, int $limit = 25): array {
        if (trim($query) === '') {
            return $this->request('latest', ['content' => 'v', 'count' => $limit])->items ?? [];
        }

        // Nudgis returns the exact match when the search words are an object id.
        if (preg_match('/(?<![0-9a-z])(v[0-9a-z]{19})(?![0-9a-z])/', $query, $matches)) {
            $query = $matches[1];
        }

        return $this->request('search', [
            'search' => $query,
            'content' => 'v',
            'limit' => $limit,
            'fields' => 'title_description_keywords_speaker',
        ])->videos ?? [];
    }

    /**
     * Check whether anonymous users may watch a media, or the media in a channel
     *
     * @param string $oid Media or channel object id
     * @return bool
     */
    public function is_public(string $oid): bool {
        $result = $this->request('perms/get/default', ['oid' => $oid, 'recursive' => 'yes']);
        foreach ($result->groups ?? [] as $group) {
            if (($group->ref ?? '') === 'users-anonymous') {
                $permission = $group->permissions->can_access_media ?? new stdClass();
                return !empty($permission->val ?? $permission->inherit_val ?? false);
            }
        }

        return false;
    }

    /**
     * Check whether the MP4 file behind the video url exists
     *
     * Nudgis marks a video ready as soon as one resource can be displayed, but
     * the download url only works after transcoding has produced the MP4.
     *
     * @param string $oid Media object id
     * @return bool
     */
    public function has_video(string $oid): bool {
        try {
            $result = $this->request('download', [
                'oid' => $oid,
                'formats' => 'mp4',
                'playable' => 'yes',
                'quality' => 'high',
                'redirect' => 'no',
                'usage' => 'external',
            ]);
        } catch (moodle_exception $e) {
            return false;
        }

        return !empty($result->url);
    }

    /**
     * Return the url players should use for a video
     *
     * This is the url pattern UbiCast recommends for integrations. Nudgis
     * redirects it to the MP4 file, so it only plays in the browser for
     * media that anonymous users may watch.
     *
     * @param string $oid Media object id
     * @return string
     */
    public static function video_url(string $oid): string {
        return rtrim((string)get_config('mediatimesrc_ubicast', 'url'), '/') . "/api/v2/download/$oid.mp4?" . http_build_query([
            'playable' => 'yes',
            'quality' => 'high',
            'usage' => 'external',
        ], '', '&');
    }

    /**
     * Download a file that Nudgis links to, such as a signed thumbnail link
     *
     * @param string $url Absolute url on the portal
     * @param int $timeout Request timeout in seconds
     * @return string File content
     */
    public function download(string $url, int $timeout = 30): string {
        if (!str_starts_with($url, $this->url . '/')) {
            throw new moodle_exception('apierror', 'mediatimesrc_ubicast', '', $url);
        }

        try {
            $response = $this->client->request('GET', $url, ['http_errors' => false, 'timeout' => $timeout]);
        } catch (GuzzleException $e) {
            throw new moodle_exception('apierror', 'mediatimesrc_ubicast', '', $e->getMessage());
        }
        if ($response->getStatusCode() >= 400) {
            throw new moodle_exception('apierror', 'mediatimesrc_ubicast', '', $response->getReasonPhrase());
        }

        return (string)$response->getBody();
    }

    /**
     * Upload a file with the chunked upload API
     *
     * @param string $path Path of local file
     * @param string $filename File name to report to Nudgis
     * @return string Upload id
     */
    public function upload_file(string $path, string $filename): string {
        $size = filesize($path);
        $handle = fopen($path, 'rb');
        $uploadid = null;
        $offset = 0;

        while ($offset < $size) {
            $chunk = fread($handle, self::CHUNK_SIZE);
            $end = $offset + strlen($chunk) - 1;
            $multipart = [[
                'name' => 'file',
                'contents' => $chunk,
                'filename' => $filename,
            ]];
            if ($uploadid) {
                $multipart[] = ['name' => 'upload_id', 'contents' => $uploadid];
            }
            $result = $this->request('upload', [], 'POST', [
                'headers' => ['Content-Range' => "bytes $offset-$end/$size"],
                'multipart' => $multipart,
                'timeout' => 600,
            ]);
            $uploadid = $result->upload_id;
            $offset = $end + 1;
        }
        fclose($handle);

        $this->request('upload/complete', [
            'upload_id' => $uploadid,
            'expected_size' => $size,
        ], 'POST', ['timeout' => 600]);

        return $uploadid;
    }

    /**
     * Upload a video file and create a media for it
     *
     * @param string $path Path of local file
     * @param string $filename Original file name
     * @param array $metadata Media fields for medias/add, for example title and description
     * @return stdClass Media information
     */
    public function add_media(string $path, string $filename, array $metadata): stdClass {
        global $CFG;

        \core_php_time_limit::raise();

        $metadata['code'] = $this->upload_file($path, self::upload_filename($filename));
        $metadata['origin'] = 'Moodle Media Time ' . parse_url($CFG->wwwroot, PHP_URL_HOST);
        if ($channel = get_config('mediatimesrc_ubicast', 'channel')) {
            $metadata['channel'] = "mscid-$channel";
        }
        $result = $this->request('medias/add', $metadata, 'POST', ['timeout' => 600]);

        return $this->get_media($result->oid);
    }

    /**
     * Return a file name that the Nudgis upload API accepts
     *
     * @param string $filename Original file name
     * @return string
     */
    public static function upload_filename(string $filename): string {
        $filename = ltrim(str_replace(['/', '..'], '', clean_filename($filename)), '.');
        if (!str_contains($filename, '.')) {
            $filename .= '.mp4';
        }

        return $filename;
    }
}
