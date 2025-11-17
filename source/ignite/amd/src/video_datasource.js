// This file is part of Moodle - http://moodle.org/ //
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

/*
 * Video search
 *
 * @package    mediatimesrc_ignite
 * @copyright  2025 bdecent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from "core/ajax";
import Notification from "core/notification";


/**
 * Process the results for auto complete elements.
 *
 * @param {String} selector The selector of the auto complete element.
 * @param {Array} results An array or results.
 * @return {Array} New array of results.
 */
const processResults = (selector, results) => {
    const options = [];

    results.forEach(video => {
        options.push({
            value: video.uri,
            label: video.name
        });
    });

    return options;
};

/**
 * Source of data for Ajax element.
 *
 * @param {String} selector The selector of the auto complete element.
 * @param {String} query The query string.
 * @param {Function} callback A callback function receiving an array of results.
 */
const transport = async(selector, query, callback) => {
    const videos = await Ajax.call([{
        methodname: 'mediatimesrc_ignite_video_search',
        args: {
            query: query
        },
        fail: Notification.exception
    }])[0];

    callback(videos);
};

export default {
    processResults: processResults,
    transport: transport
};
