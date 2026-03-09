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

/**
 * Manage Vimeo source files
 *
 * @package    mediatimesrc_vimeo
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_vimeo;

use moodle_exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use stdClass;

/**
 * Manage Vimeo source files
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var $apikey Ignite apikey */
    protected ?string $apikey = null;

    /** @var $client GuzzleHttp client */
    protected ?Client $client = null;

    /**
     * Constructor
     *
     * @param int|null $userid Optional user id to use for request
     */
    public function __construct($userid = 0) {
        global $DB;

        if (
            !$this->apikey = get_config('mediatimesrc_vimeo', 'apikey')
                ?: get_config('videotimeplugin_repository', 'vimeo_access_token')
        ) {
            throw new moodle_exception('credentialsnotconfigured');
        }

        $this->client = new Client();
    }

    /**
     * Create place holder for upload
     *
     * @param array $params Upload params
     * @return array
     */
    public function create_token($params = []) {
        return $this->request('/me/videos', $params, 'POST')['body'];
    }

    /**
     * Create folder
     *
     * @param array $params Upload params
     * @return array
     */
    public function create_folder($params) {
        return $this->request('/me/projects', $params, 'POST')['body'];
    }

    /**
     * Get Folders
     */
    public function get_folders() {
        return $this->request('/me/folders', ['fields' => 'name, uri, modified_time'])['body'];
    }

    /**
     * Submit request to Vimeo
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

        if (empty($params) || $method == 'GET') {
            $options = ['headers' => $headers];
        } else {
            $options = [
                'body' => json_encode($params),
                'headers' => $headers,
            ];
        }
        try {
            if (empty($params) || $method != 'GET') {
                $response = $this->client->request($method, "https://api.vimeo.com$endpoint", $options);
            } else {
                $response = $this->client->request(
                    $method,
                    "https://api.vimeo.com$endpoint?" . http_build_query($params),
                    $options
                );
            }
        } catch (RequestException $e) {
            return null;
        }
        return ['body' => json_decode($response->getBody(), true)];
    }
}
