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
 * Conversation persistence.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * Chat sessions, messages and tool calls.
 */
class conversation {

    /**
     * Create a conversation.
     *
     * @param int $userid
     * @param int|null $placementid
     * @param string $layout
     * @return \stdClass
     */
    public static function create(int $userid, ?int $placementid, string $layout = 'overlay'): \stdClass {
        global $DB;
        $now = time();
        $record = (object) [
            'userid' => $userid,
            'placementid' => $placementid,
            'title' => '',
            'layout' => $layout,
            'autoapprove' => approval::NONE,
            'providermeta' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('tool_adminagent_conversation', $record);
        return $record;
    }

    /**
     * Get a conversation the user owns.
     *
     * @param int $id
     * @param int $userid
     * @return \stdClass
     */
    public static function require(int $id, int $userid): \stdClass {
        global $DB;
        $record = $DB->get_record('tool_adminagent_conversation', ['id' => $id, 'userid' => $userid], '*', MUST_EXIST);
        return $record;
    }

    /**
     * Latest conversation for a user.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function latest(int $userid): ?\stdClass {
        global $DB;
        // Exclude conversations that have no stored messages.
        $sql = "SELECT c.id, c.title, c.timemodified
                  FROM {tool_adminagent_conversation} c
                  JOIN {tool_adminagent_message} m ON m.conversationid = c.id
                 WHERE c.userid = :userid
              GROUP BY c.id, c.title, c.timemodified
              ORDER BY c.timemodified DESC";
        $records = $DB->get_records_sql($sql, ['userid' => $userid], 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Recent conversations for a user, newest first.
     *
     * @param int $userid
     * @param int $limit
     * @return array
     */
    public static function list_for_user(int $userid, int $limit = 50): array {
        global $DB;
        // Exclude conversations that have no stored messages.
        $sql = "SELECT c.id, c.title, c.timemodified
                  FROM {tool_adminagent_conversation} c
                  JOIN {tool_adminagent_message} m ON m.conversationid = c.id
                 WHERE c.userid = :userid
              GROUP BY c.id, c.title, c.timemodified
              ORDER BY c.timemodified DESC";
        $records = $DB->get_records_sql($sql, ['userid' => $userid], 0, $limit);
        $out = [];
        foreach ($records as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'title' => (string) ($row->title ?? ''),
                'timemodified' => (int) $row->timemodified,
                'datedisplay' => trim(userdate((int) $row->timemodified, '%e/%m/%y')),
            ];
        }
        return $out;
    }

    /**
     * Add a message.
     *
     * @param int $conversationid
     * @param string $role
     * @param string $content
     * @param int $inputtokens
     * @param int $outputtokens
     * @return \stdClass
     */
    public static function add_message(
        int $conversationid,
        string $role,
        string $content,
        int $inputtokens = 0,
        int $outputtokens = 0
    ): \stdClass {
        global $DB;
        $record = (object) [
            'conversationid' => $conversationid,
            'role' => $role,
            'content' => $content,
            'inputtokens' => $inputtokens,
            'outputtokens' => $outputtokens,
            'feedback' => '',
            'timecreated' => time(),
        ];
        $record->id = $DB->insert_record('tool_adminagent_message', $record);
        $DB->set_field('tool_adminagent_conversation', 'timemodified', time(), ['id' => $conversationid]);
        if ($role === 'user') {
            $conv = $DB->get_record('tool_adminagent_conversation', ['id' => $conversationid]);
            if ($conv && $conv->title === '') {
                $title = \core_text::substr(trim($content), 0, 80);
                $DB->set_field('tool_adminagent_conversation', 'title', $title, ['id' => $conversationid]);
            }
        }
        return $record;
    }

    /**
     * Messages in order.
     *
     * @param int $conversationid
     * @return array
     */
    public static function messages(int $conversationid): array {
        global $DB;
        return array_values($DB->get_records('tool_adminagent_message', ['conversationid' => $conversationid], 'id ASC'));
    }

    /**
     * Tool calls in order.
     *
     * @param int $conversationid
     * @return array
     */
    public static function toolcalls(int $conversationid): array {
        global $DB;
        return array_values($DB->get_records('tool_adminagent_toolcall', ['conversationid' => $conversationid], 'id ASC'));
    }

    /**
     * Pending calls.
     *
     * @param int $conversationid
     * @return array
     */
    public static function pending_calls(int $conversationid): array {
        global $DB;
        return array_values($DB->get_records('tool_adminagent_toolcall', [
            'conversationid' => $conversationid,
            'status' => 'pending',
        ], 'id ASC'));
    }

    /**
     * Insert a tool call.
     *
     * @param array $data
     * @return \stdClass
     */
    public static function add_toolcall(array $data): \stdClass {
        global $DB;
        $now = time();
        $record = (object) [
            'conversationid' => $data['conversationid'],
            'messageid' => $data['messageid'] ?? null,
            'callid' => $data['callid'] ?? ('call_' . uniqid()),
            'functionname' => $data['functionname'],
            'arguments' => is_string($data['arguments'] ?? '') ? $data['arguments'] : json_encode($data['arguments'] ?? new \stdClass()),
            'result' => $data['result'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'readwrite' => $data['readwrite'] ?? 'R',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('tool_adminagent_toolcall', $record);
        return $record;
    }

    /**
     * Update a tool call.
     *
     * @param int $id
     * @param array $fields
     * @return \stdClass
     */
    public static function update_toolcall(int $id, array $fields): \stdClass {
        global $DB;
        $record = $DB->get_record('tool_adminagent_toolcall', ['id' => $id], '*', MUST_EXIST);
        foreach ($fields as $key => $value) {
            $record->$key = $value;
        }
        $record->timemodified = time();
        $DB->update_record('tool_adminagent_toolcall', $record);
        return $record;
    }

    /**
     * Export a conversation for the chat UI.
     *
     * @param \stdClass $conversation
     * @return array
     */
    public static function export(\stdClass $conversation): array {
        $callsbymsg = [];
        foreach (self::toolcalls($conversation->id) as $call) {
            $callsbymsg[(int) ($call->messageid ?? 0)][] = $call;
        }
        $messages = [];
        foreach (self::messages($conversation->id) as $message) {
            if (!in_array($message->role, ['user', 'assistant'], true)) {
                continue;
            }
            $hascontent = $message->content !== '';
            $hascalls = !empty($callsbymsg[(int) $message->id]);
            if (!$hascontent && !$hascalls) {
                continue;
            }
            $messages[] = [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'timecreated' => $message->timecreated,
                'feedback' => $message->feedback ?? '',
            ];
        }
        $calls = [];
        foreach (self::toolcalls($conversation->id) as $call) {
            $calls[] = self::export_call($call);
        }
        return [
            'id' => (int) $conversation->id,
            'title' => (string) ($conversation->title ?? ''),
            'layout' => (string) ($conversation->layout ?? 'overlay'),
            'autoapprove' => (string) ($conversation->autoapprove ?? 'none'),
            'timemodified' => (int) $conversation->timemodified,
            'messages' => $messages,
            'toolcalls' => $calls,
        ];
    }

    /**
     * Export one tool call.
     *
     * @param \stdClass $call
     * @return array
     */
    public static function export_call(\stdClass $call): array {
        return [
            'id' => (int) $call->id,
            'messageid' => (int) ($call->messageid ?? 0),
            'callid' => (string) ($call->callid ?? ''),
            'functionname' => (string) ($call->functionname ?? ''),
            'arguments' => (string) ($call->arguments ?? ''),
            'result' => (string) ($call->result ?? ''),
            'status' => (string) ($call->status ?? ''),
            'readwrite' => (string) ($call->readwrite ?? 'R'),
            'pending' => $call->status === 'pending',
            'done' => in_array($call->status, ['done', 'denied', 'error'], true),
            'running' => $call->status === 'running',
        ];
    }

    /**
     * Set layout / autoapprove.
     *
     * @param int $id
     * @param array $fields
     */
    public static function update(int $id, array $fields): void {
        global $DB;
        $record = $DB->get_record('tool_adminagent_conversation', ['id' => $id], '*', MUST_EXIST);
        foreach ($fields as $key => $value) {
            $record->$key = $value;
        }
        $record->timemodified = time();
        $DB->update_record('tool_adminagent_conversation', $record);
    }

    /**
     * Delete a conversation and children.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        $conv = $DB->get_record('tool_adminagent_conversation', ['id' => $id]);
        if ($conv) {
            $fs = get_file_storage();
            $usercontext = \context_user::instance((int) $conv->userid);
            $fs->delete_area_files($usercontext->id, 'tool_adminagent', 'conversation', $id);
        }
        $DB->delete_records('tool_adminagent_toolcall', ['conversationid' => $id]);
        $DB->delete_records('tool_adminagent_message', ['conversationid' => $id]);
        $DB->delete_records('tool_adminagent_conversation', ['id' => $id]);
    }
}
