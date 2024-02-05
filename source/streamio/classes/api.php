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
 * Manage Streamio source files
 *
 * @package    mediatimesrc_streamio
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mediatimesrc_streamio;

use moodle_exception;
use stdClass;

/**
 * Manage Streamio source files
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var $username Streamio account password */
    protected ?string $password = null;

    /** @var $username Streamio account username */
    protected ?string $username = null;

    /**
     * Constructor
     */
    public function __construct() {
        global $DB, $USER;

        if (!$username = get_config('mediatimesrc_streamio', 'username')) {
            throw new moodle_exception('credentialsnotconfigured');
        }
        if (!$password = get_config('mediatimesrc_streamio', 'password')) {
            throw new moodle_exception('credentialsnotconfigured');
        }
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Submit request to Streamio
     *
     * @param string $endpoint
     * @param ?array $params Options for request
     * @param string $method HTTP method to use
     * @return mixed
     */
    public function request($endpoint, $params = [], $method = 'GET') {
        $ch = curl_init('https://streamio.com/api/v1' . $endpoint);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic " . base64_encode("$this->username:$this->password"),
            "Accept: application/json",
            "Content-type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        curl_close($ch);

        if ($response === false) {
            throw new moodle_exception('streamiorequesterror');
        }

        return json_decode($response);
    }

    /**
     * Create upload token for Streamio
     *
     * @param null|array $params Fields to set for video
     * @return string
     */
    public function create_token(?array $params = []) {
        return $this->request('/videos/create_token', $params, 'POST');
    }
}
