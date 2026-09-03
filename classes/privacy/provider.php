<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Privacy provider for tool_adminagent.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Return metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_adminagent_conversation', [
            'userid' => 'privacy:metadata:tool_adminagent_conversation:userid',
            'title' => 'privacy:metadata:tool_adminagent_conversation:title',
            'timecreated' => 'privacy:metadata:tool_adminagent_conversation:timecreated',
            'timemodified' => 'privacy:metadata:tool_adminagent_conversation:timemodified',
        ], 'privacy:metadata:tool_adminagent_conversation');

        $collection->add_database_table('tool_adminagent_message', [
            'content' => 'privacy:metadata:tool_adminagent_message:content',
            'role' => 'privacy:metadata:tool_adminagent_message:role',
            'feedback' => 'privacy:metadata:tool_adminagent_message:feedback',
            'timecreated' => 'privacy:metadata:tool_adminagent_message:timecreated',
        ], 'privacy:metadata:tool_adminagent_message');

        $collection->add_database_table('tool_adminagent_toolcall', [
            'functionname' => 'privacy:metadata:tool_adminagent_toolcall:functionname',
            'arguments' => 'privacy:metadata:tool_adminagent_toolcall:arguments',
            'result' => 'privacy:metadata:tool_adminagent_toolcall:result',
        ], 'privacy:metadata:tool_adminagent_toolcall');

        $collection->add_external_location_link(
            'aiprovider',
            ['messages' => 'privacy:metadata:tool_adminagent_message:content'],
            'privacy:metadata:externalprovider'
        );

        return $collection;
    }

    /**
     * Get contexts for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if ($DB->record_exists('tool_adminagent_conversation', ['userid' => $userid])) {
            $usercontext = \context_user::instance($userid);
            $list->add_from_sql('id', 'SELECT id FROM {context} WHERE id = :id', ['id' => $usercontext->id]);
            $sys = \context_system::instance();
            $list->add_from_sql('id', 'SELECT id FROM {context} WHERE id = :sid', ['sid' => $sys->id]);
        }
        return $list;
    }

    /**
     * Get users in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER) {
            $userlist->add_user($context->instanceid);
        }
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $sql = "SELECT userid FROM {tool_adminagent_conversation}";
            $userlist->add_from_sql('userid', $sql, []);
        }
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $conversations = $DB->get_records('tool_adminagent_conversation', ['userid' => $userid]);
        foreach ($conversations as $conversation) {
            $messages = $DB->get_records('tool_adminagent_message', ['conversationid' => $conversation->id], 'id ASC');
            $calls = $DB->get_records('tool_adminagent_toolcall', ['conversationid' => $conversation->id], 'id ASC');
            writer::with_context(\context_user::instance($userid))->export_data(
                [get_string('pluginname', 'tool_adminagent'), $conversation->title . '-' . $conversation->id],
                (object) [
                    'conversation' => $conversation,
                    'messages' => array_values($messages),
                    'toolcalls' => array_values($calls),
                ]
            );
        }
    }

    /**
     * Delete data for all users in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records('tool_adminagent_toolcall');
            $DB->delete_records('tool_adminagent_message');
            $DB->delete_records('tool_adminagent_conversation');
        }
        if ($context->contextlevel == CONTEXT_USER) {
            self::delete_user_data($context->instanceid);
        }
    }

    /**
     * Delete data for a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        self::delete_user_data($contextlist->get_user()->id);
    }

    /**
     * Delete data for users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_data($userid);
        }
    }

    /**
     * Delete one user's chats.
     *
     * @param int $userid
     */
    protected static function delete_user_data(int $userid): void {
        global $DB;
        $ids = $DB->get_fieldset_select('tool_adminagent_conversation', 'id', 'userid = ?', [$userid]);
        if (!$ids) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($ids);
        $DB->delete_records_select('tool_adminagent_toolcall', "conversationid $insql", $params);
        $DB->delete_records_select('tool_adminagent_message', "conversationid $insql", $params);
        $DB->delete_records('tool_adminagent_conversation', ['userid' => $userid]);
    }
}
