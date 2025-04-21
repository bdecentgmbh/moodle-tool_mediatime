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
 * Privacy Subsystem implementation for tool_mediatime.
 *
 * @package    tool_mediatime
 * @category   privacy
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mediatime\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for tool_mediatime.
 *
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        return $collection->add_database_table(
            'tool_mediatime',
            [
                'content' => 'privacy:metadata:tool_mediatime:content',
                'name' => 'privacy:metadata:tool_mediatime:name',
                'timecreated' => 'privacy:metadata:tool_mediatime:timecreated',
                'timemodified' => 'privacy:metadata:tool_mediatime:timemodified',
                'usermodified' => 'privacy:metadata:tool_mediatime:usermodified',
            ],
            'privacy:metadata:tool_mediatime'
        );
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT c.id
                  FROM {tool_mediatime} m
                  JOIN {context} c ON c.id = m.contextid
                 WHERE m.usermodified = :usermodified";

        $params = [
            'usermodified' => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (
            !(
            is_a($context, \context_system::class)
            || is_a($context, \context_coursecat::class)
            || is_a($context, \context_course::class)
            )
        ) {
            return;
        }

        $sql = "SELECT usermodified
                  FROM {tool_mediatime}
                 WHERE contextid = :contextid";

        $params = [
            'contetxtid' => $context->id,
        ];

        $userlist->add_from_sql('usermodified', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        // Remove contexts different from CONTEXT_SYSTEM.
        $contexts = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if (
                $context->contextlevel == CONTEXT_SYSTEM
                || $context->contextlevel == CONTEXT_COURSECAT
                || $context->contextlevel == CONTEXT_COURSE
            ) {
                $carry[] = $context->id;
            }
            return $carry;
        }, []);

        if (empty($contexts)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;
        // Get resource data.
        [$insql, $inparams] = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);
        $sql = "SELECT m.id,
                       m.name,
                       m.content,
                       m.source,
                       m.timecreated,
                       m.timemodified,
                       m.usermodified,
                       m.contextid
                  FROM {tool_mediatime} m
                 WHERE m.usermodified = :usermodified
                        AND m.contextid $insql
              ORDER BY m.contextid, m.timemodified";
        $params = array_merge($inparams, [
            'usermodified' => $userid,
        ]) + $inparams;

        $lastctxid = null;
        $data = [];
        $resources = $DB->get_recordset_sql($sql, $params);
        foreach ($resources as $resource) {
            if ($lastctxid != $resource->contextid) {
                if (!empty($data)) {
                    $context = \context::instance_by_id($lastctxid);
                    self::export_resource_data_for_user($data, $context, $user);
                }
                $data = [
                    'resources' => [],
                    'contextid' => $resource->contextid,
                ];
                $lastctxid = $resource->contextid;
            }
            $data['resources'][] = (object)[
                'content' => $resource->content,
                'name' => $resource->name,
                'usermodified' => $resource->usermodified,
                'timecreated' => transform::datetime($resource->timecreated),
                'timemodified' => transform::datetime($resource->timemodified),
                'source' => $resource->source,
            ];
        }

        // Write last context.
        if (!empty($data)) {
            $context = \context::instance_by_id($lastctxid);
            self::export_resource_data_for_user($data, $context, $user);
        }

        $resources->close();
    }

    /**
     * Export the supplied personal data for a single Media Time resource, along with any generic data or area files.
     *
     * @param array $resourcedata the personal data to export for the meeting.
     * @param \context $context the context of resource
     * @param \stdClass $user the user record
     */
    protected static function export_resource_data_for_user(array $resourcedata, \context $context, \stdClass $user) {
        // Fetch the generic module data for the plenary meeting.
        $contextdata = helper::get_context_data($context, $user);
        writer::with_context($context)->export_data([], $contextdata);

        // Merge with resource data and write it.
        $contextdata = (object)array_merge((array)$contextdata, $resourcedata);
        writer::with_context($context)->export_data([
            get_string('privacy:resources', 'tool_mediatime'),
        ], $contextdata);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        $DB->delete_records('tool_mediatime', ['contextid' => $context->id]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        return;
    }
}
