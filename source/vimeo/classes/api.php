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
use stdClass;

/**
 * Manage Vimeo source files
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api extends \videotimeplugin_repository\api {
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
}
