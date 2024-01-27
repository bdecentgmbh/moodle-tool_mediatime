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
        // The Plenary meeting module stores user provided data.
        \core_privacy\local\metadata\provider,

        // This plugin is a core_user_data_provider.
        \core_privacy\local\request\plugin\provider,

        \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection) : collection {

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
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $sql = "SELECT c.id
                  FROM {tool_mediatime} m
                  JOIN {context} c ON c.contextlevel = :contextlevel
                 WHERE m.usermodified = :usermodified";

        $params = [
            'contextlevel' => CONTEXT_SYSTEM,
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

        if (!is_a($context, \context_system::class)) {
            return;
        }

        $sql = "SELECT usermodified
                  FROM {tool_mediatime}";

        $params = [];

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
        $contexts = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $carry[] = $context->id;
            }
            return $carry;
        }, []);

        if (empty($contexts)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;
        // Get motion data.
        list($insql, $inparams) = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);
        $sql = "SELECT m.id,
                       m.name,
                       m.content,
                       m.source,
                       m.timemodified,
                       m.usermodified,
                  FROM {tool_mediatime} m
                 WHERE m.usermodified = :usermodified
              ORDER BY cmid, m.timemodified";
        $params = array_merge($inparams, [
            'usermodified' => $userid,
        ]);

        $data = [];
        $resources = $DB->get_recordset_sql($sql, $params);
        foreach ($resources as $resource) {
            $data['motions'][] = (object)[
                'content' => $motion->content,
                'name' => $motion->name,
                'usermodified' => $motion->usermodified,
                'timemodified' => transform::datetime($motion->timemodified),
                'source' => $motion->source,
            ];
        }

        $motions->close();

        // Fetch the generic data for system context.
        $contextdata = helper::get_context_data($context, $user);

        // Merge with motion data and write it.
        $contextdata = (object)array_merge((array)$contextdata, $motiondata);
        writer::with_context($context)->export_data([], $contextdata);

        // Write generic module files.
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
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
