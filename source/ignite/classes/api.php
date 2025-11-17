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

namespace mediatimesrc_ignite;

use moodle_exception;
use GuzzleHttp\Client;
use stdClass;
use stored_file;

/**
 * Ignite API client
 *
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var $apikey Ignite apikey */
    protected ?string $apikey = null;

    /** @var $client GuzzleHttp client */
    protected ?Client $client = null;

    /**
     * Constructor
     */
    public function __construct() {
        global $DB, $USER;

        if (!$this->apikey = get_config('mediatimesrc_ignite', 'apikey')) {
            throw new moodle_exception('credentialsnotconfigured');
        }

        $this->client = new Client();
    }

    /**
     * Submit request to Ignite cloud
     *
     * @param string $endpoint
     * @param ?array $params Options for request
     * @param string $method HTTP method to use
     * @return mixed
     */
    public function request($endpoint, $params = [], $method = 'GET') {
        $headers = [
            "Authorization" => "Bearer $this->apikey",
            "Content-type" => "application/json",
        ];

        if (empty($params)) {
            $options = ['headers' => $headers];
        } else {
            $options = [
                'body' => json_encode($params),
                'headers' => $headers,
            ];
        }
        $response = $this->client->request($method, "https://app.ignitevideo.cloud/api$endpoint", $options);
        return json_decode($response->getBody(), false);
    }

    /**
     * Put stored file to S3 url
     *
     * @param string $url Url suplied by Ignite
     * @param stored_file $file
     */
    public function put_file($fullpath, $data) {
        $result = $this->request('/videos/upload', [
            'title' => $data->title ?: $data->name,
            'visibility' => 'public',
        ], 'PUT');

        $ch = curl_init($result->signedUrl);

        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_PUT, true);

        $filehandle = fopen($fullpath, 'r');

        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($fullpath));
        curl_setopt($ch, CURLOPT_INFILE, $filehandle);

        curl_exec($ch);
        curl_close($ch);
        fclose($filehandle);

        return $this->request("/videos/$result->videoId");
    }
}
