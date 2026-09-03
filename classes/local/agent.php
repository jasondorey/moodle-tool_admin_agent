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
 * Agent runtime loop.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

use tool_adminagent\ai\provider_resolver;

/**
 * Drive one or more model turns until a reply or pending approval.
 */
class agent {

    /**
     * Continue a conversation, optionally after a new user message.
     *
     * @param \stdClass $conversation
     * @param string|null $usermessage
     * @param string|null $pageurl
     * @return array Conversation export plus status.
     */
    public static function turn(\stdClass $conversation, ?string $usermessage = null, ?string $pageurl = null): array {
        if ($usermessage !== null && $usermessage !== '') {
            // Store the raw user text (no page URL) so the bubble stays clean.
            $usermsg = conversation::add_message((int) $conversation->id, 'user', $usermessage);
            // Persist the latest page URL in providermeta so llm_messages() can inject it.
            if ($pageurl) {
                $meta = json_decode($conversation->providermeta ?: '{}', true) ?: [];
                $meta['pageurl'] = $pageurl;
                $encoded = json_encode($meta);
                conversation::update($conversation->id, ['providermeta' => $encoded]);
                $conversation->providermeta = $encoded;
            }
            self::prefetch_search($conversation, $usermessage, (int) $usermsg->id);
        }

        $client = provider_resolver::client();
        if (!$client) {
            throw new \moodle_exception('noprovider', 'tool_adminagent');
        }

        $max = 12;
        for ($i = 0; $i < $max; $i++) {
            $pending = conversation::pending_calls($conversation->id);
            if ($pending) {
                return self::payload($conversation, 'pending');
            }

            $available = catalog::get_available();
            $tools = self::tools_for_turn($conversation, $available);
            $messages = self::llm_messages($conversation);
            $state = json_decode($conversation->providermeta ?: '{}', true) ?: [];

            $result = $client->chat($messages, $tools, $state);
            if ($result->state) {
                $meta = json_decode($conversation->providermeta ?: '{}', true) ?: [];
                foreach ($result->state as $key => $value) {
                    $meta[$key] = $value;
                }
                $encoded = json_encode($meta);
                conversation::update($conversation->id, ['providermeta' => $encoded]);
                $conversation->providermeta = $encoded;
            }

            if ($result->toolcalls) {
                $assistantcontent = $result->text;
                $msg = conversation::add_message(
                    (int) $conversation->id,
                    'assistant',
                    (string) ($assistantcontent ?? ''),
                    (int) $result->inputtokens,
                    (int) $result->outputtokens
                );
                $needapproval = false;
                foreach ($result->toolcalls as $call) {
                    $name = $call['name'];
                    $row = catalog::get($name);
                    $rw = ($row && catalog::is_write($row->type)) ? 'W' : 'R';
                    $record = conversation::add_toolcall([
                        'conversationid' => $conversation->id,
                        'messageid' => $msg->id,
                        'callid' => $call['id'],
                        'functionname' => $name,
                        'arguments' => $call['arguments'],
                        'status' => 'pending',
                        'readwrite' => $rw,
                    ]);
                    if ($row && approval::auto_allows($conversation->autoapprove, $row)) {
                        self::execute_call($record);
                    } else {
                        $needapproval = true;
                    }
                }
                if ($needapproval) {
                    return self::payload($conversation, 'pending');
                }
                continue;
            }

            conversation::add_message(
                (int) $conversation->id,
                'assistant',
                $result->text !== '' ? $result->text : '...',
                (int) $result->inputtokens,
                (int) $result->outputtokens
            );
            return self::payload($conversation, 'complete');
        }

        return self::payload($conversation, 'complete');
    }

    /**
     * Approve or deny a pending call then continue.
     *
     * @param \stdClass $conversation
     * @param int $callid
     * @param bool $approve
     * @return array
     */
    public static function resolve(\stdClass $conversation, int $callid, bool $approve): array {
        $calls = conversation::toolcalls($conversation->id);
        $target = null;
        foreach ($calls as $call) {
            if ((int) $call->id === $callid) {
                $target = $call;
                break;
            }
        }
        if (!$target || $target->status !== 'pending') {
            throw new \invalid_parameter_exception('Call is not pending');
        }
        if ($approve) {
            self::execute_call($target);
        } else {
            conversation::update_toolcall($target->id, [
                'status' => 'denied',
                'result' => json_encode(['error' => get_string('functiondenied', 'tool_adminagent')]),
            ]);
            conversation::add_message((int) $conversation->id, 'tool', get_string('functiondenied', 'tool_adminagent'));
        }
        return self::turn($conversation);
    }

    /**
     * Run or refresh catalog discovery for a user message.
     *
     * The first message records a visible search_tools call. Later messages that
     * look like a new task silently replace the discovered set (no extra card).
     * Short follow-ups keep the current set. Functions already called stay loaded.
     *
     * @param \stdClass $conversation
     * @param string $prompt
     * @param int $messageid User message to attach the first card to.
     * @return bool Whether discovery ran.
     */
    public static function prefetch_search(\stdClass $conversation, string $prompt, int $messageid): bool {
        $available = catalog::get_available();
        if (!discovery::enabled($available)) {
            return false;
        }
        $current = self::discovered_names($conversation);
        $hasvisible = false;
        foreach (conversation::toolcalls($conversation->id) as $call) {
            if ($call->functionname === 'search_tools' && $call->status === 'done') {
                $hasvisible = true;
                break;
            }
        }
        if (!$hasvisible) {
            $record = conversation::add_toolcall([
                'conversationid' => $conversation->id,
                'messageid' => $messageid,
                'callid' => 'prefetch_search_' . uniqid(),
                'functionname' => 'search_tools',
                'arguments' => json_encode(['prompt' => $prompt]),
                'status' => 'pending',
                'readwrite' => 'R',
            ]);
            self::execute_call($record);
            foreach (conversation::toolcalls($conversation->id) as $call) {
                if ((int) $call->id === (int) $record->id) {
                    $decoded = json_decode($call->result ?: '{}', true);
                    $names = [];
                    foreach ($decoded['tools'] ?? [] as $found) {
                        if (is_string($found) && $found !== '') {
                            $names[] = $found;
                        }
                    }
                    self::set_discovered($conversation, $names);
                    break;
                }
            }
            return true;
        }
        if (!discovery::is_new_task($prompt, $current, $available)) {
            return false;
        }
        self::set_discovered($conversation, discovery::search($prompt, $available));
        return true;
    }

    /**
     * Names loaded by discovery for this conversation.
     *
     * @param \stdClass $conversation
     * @return array
     */
    public static function discovered_names(\stdClass $conversation): array {
        $state = json_decode($conversation->providermeta ?: '{}', true) ?: [];
        $names = $state['discovered'] ?? [];
        if (is_array($names) && $names) {
            return array_values(array_filter($names, 'is_string'));
        }
        foreach (array_reverse(conversation::toolcalls($conversation->id)) as $call) {
            if ($call->functionname !== 'search_tools' || !$call->result) {
                continue;
            }
            $decoded = json_decode($call->result, true);
            $found = [];
            foreach ($decoded['tools'] ?? [] as $name) {
                if (is_string($name) && $name !== '') {
                    $found[] = $name;
                }
            }
            if ($found) {
                return $found;
            }
        }
        return [];
    }

    /**
     * Persist the current discovered function set.
     *
     * @param \stdClass $conversation
     * @param array $names
     */
    public static function set_discovered(\stdClass $conversation, array $names): void {
        $state = json_decode($conversation->providermeta ?: '{}', true) ?: [];
        $state['discovered'] = array_values(array_filter($names, 'is_string'));
        $encoded = json_encode($state);
        conversation::update((int) $conversation->id, ['providermeta' => $encoded]);
        $conversation->providermeta = $encoded;
    }

    /**
     * Run one stored call.
     *
     * @param \stdClass $call
     */
    public static function execute_call(\stdClass $call): void {
        conversation::update_toolcall((int) $call->id, ['status' => 'running']);
        $args = json_decode($call->arguments ?: '{}', true);
        if (!is_array($args)) {
            $args = [];
        }
        $conversationid = (int) $call->conversationid;
        try {
            $result = executor::run($call->functionname, $args);
            $encoded = json_encode($result);
            if ($encoded === false) {
                $encoded = '{"error":"Unable to encode function result"}';
            }
            conversation::update_toolcall((int) $call->id, [
                'status' => 'done',
                'result' => $encoded,
            ]);
            conversation::add_message($conversationid, 'tool', $encoded);
        } catch (\Throwable $e) {
            $encoded = json_encode(['error' => $e->getMessage()]);
            conversation::update_toolcall((int) $call->id, [
                'status' => 'error',
                'result' => $encoded,
            ]);
            conversation::add_message($conversationid, 'tool', $encoded);
        }
    }

    /**
     * Whether a stored call is a server-side discovery step, not a model tool call.
     *
     * @param \stdClass $call
     * @return bool
     */
    public static function is_prefetch_search(\stdClass $call): bool {
        $id = (string) ($call->callid ?? '');
        return str_starts_with($id, 'prefetch_search_') || str_starts_with($id, 'call_search_');
    }

    /**
     * Build LLM messages including tool results.
     *
     * Prefetch search_tools rows stay in the chat UI but are not sent as
     * function_call / function_call_output items. Providers reject call IDs
     * the model did not emit.
     *
     * @param \stdClass $conversation
     * @return array
     */
    public static function llm_messages(\stdClass $conversation): array {
        $skills = skill::get_available();
        $messages = [
            ['role' => 'system', 'content' => prompt::build($skills)],
        ];
        // Retrieve the latest page URL from provider meta (stored at send time).
        $meta = json_decode($conversation->providermeta ?: '{}', true) ?: [];
        $pageurl = $meta['pageurl'] ?? '';

        $calls = conversation::toolcalls($conversation->id);
        $callsbymsg = [];
        foreach ($calls as $call) {
            $callsbymsg[(int) $call->messageid][] = $call;
        }
        $usermsgs = [];
        foreach (conversation::messages($conversation->id) as $message) {
            if ($message->role === 'user') {
                $usermsgs[] = $message;
                $messages[] = ['role' => 'user', 'content' => $message->content];
                continue;
            }
            if ($message->role === 'assistant') {
                $related = $callsbymsg[(int) $message->id] ?? [];
                $prefetch = [];
                $real = [];
                foreach ($related as $call) {
                    if (self::is_prefetch_search($call)) {
                        $prefetch[] = $call;
                    } else {
                        $real[] = $call;
                    }
                }
                if ($prefetch) {
                    $names = [];
                    foreach ($prefetch as $call) {
                        $decoded = json_decode($call->result ?: '{}', true);
                        foreach ($decoded['tools'] ?? [] as $found) {
                            if (is_string($found) && $found !== '') {
                                $names[] = $found;
                            }
                        }
                    }
                    if ($names) {
                        $hint = get_string('matchedfunctions', 'tool_adminagent', implode(', ', $names));
                        for ($i = count($messages) - 1; $i >= 0; $i--) {
                            if (($messages[$i]['role'] ?? '') === 'user') {
                                $messages[$i]['content'] .= "\n\n[" . $hint . "]";
                                break;
                            }
                        }
                    }
                }
                if (!$real && trim((string) $message->content) === '') {
                    continue;
                }
                $entry = ['role' => 'assistant', 'content' => $message->content];
                if ($real) {
                    $entry['tool_calls'] = [];
                    foreach ($real as $call) {
                        $entry['tool_calls'][] = [
                            'id' => $call->callid,
                            'type' => 'function',
                            'function' => [
                                'name' => $call->functionname,
                                'arguments' => $call->arguments,
                            ],
                        ];
                    }
                }
                $messages[] = $entry;
                foreach ($real as $call) {
                    if (in_array($call->status, ['done', 'error', 'denied'], true)) {
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $call->callid,
                            'name' => $call->functionname,
                            'content' => $call->result ?: '{}',
                        ];
                    }
                }
            }
        }
        $discovered = self::discovered_names($conversation);
        if ($discovered) {
            $hint = '[' . get_string('matchedfunctions', 'tool_adminagent', implode(', ', $discovered)) . ']';
            for ($i = count($messages) - 1; $i >= 0; $i--) {
                if (($messages[$i]['role'] ?? '') !== 'user') {
                    continue;
                }
                if (!str_contains((string) $messages[$i]['content'], 'Matched functions:')) {
                    $messages[$i]['content'] .= "\n\n" . $hint;
                }
                break;
            }
        }
        // Inject the current page URL into the last user message for LLM context only.
        if ($pageurl) {
            for ($i = count($messages) - 1; $i >= 0; $i--) {
                if (($messages[$i]['role'] ?? '') === 'user') {
                    $messages[$i]['content'] .= "\n\n[Current page: {$pageurl}]";
                    break;
                }
            }
        }
        return $messages;
    }

    /**
     * Tool definitions for this turn.
     *
     * @param \stdClass $conversation
     * @param array $available
     * @return array
     */
    public static function tools_for_turn(\stdClass $conversation, array $available): array {
        $used = [];
        foreach (conversation::toolcalls($conversation->id) as $call) {
            $used[$call->functionname] = true;
        }
        foreach (self::discovered_names($conversation) as $name) {
            $used[$name] = true;
        }
        $dynamic = discovery::enabled($available);
        $include = [];
        if ($dynamic) {
            $include['search_tools'] = true;
            $include['search_knowledgebase'] = true;
            $include['get_unix_timestamp'] = true;
            foreach (array_keys($used) as $name) {
                $include[$name] = true;
            }
        } else {
            foreach ($available as $row) {
                $include[$row->functionname] = true;
            }
        }
        $tools = [];
        foreach ($available as $row) {
            if ($dynamic && empty($include[$row->functionname])) {
                continue;
            }
            $schema = catalog::json_schema($row->functionname);
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $row->functionname,
                    'description' => $row->description ?: $row->functionname,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $schema['properties'] ?? new \stdClass(),
                        'required' => $schema['required'] ?? [],
                    ],
                ],
            ];
        }
        if ($dynamic) {
            $hassearch = false;
            foreach ($tools as $t) {
                if ($t['function']['name'] === 'search_tools') {
                    $hassearch = true;
                    break;
                }
            }
            if (!$hassearch) {
                array_unshift($tools, [
                    'type' => 'function',
                    'function' => [
                        'name' => 'search_tools',
                        'description' => 'Find functions that match a natural-language prompt. Returns recommended function names.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'prompt' => ['type' => 'string', 'description' => 'What the user wants to do'],
                            ],
                            'required' => ['prompt'],
                        ],
                    ],
                ]);
            }
        }
        // OpenAI 128 tool cap.
        return array_slice($tools, 0, 128);
    }

    /**
     * UI payload.
     *
     * @param \stdClass $conversation
     * @param string $status
     * @return array
     */
    protected static function payload(\stdClass $conversation, string $status): array {
        global $DB;
        $conversation = $DB->get_record('tool_adminagent_conversation', ['id' => $conversation->id], '*', MUST_EXIST);
        $export = conversation::export($conversation);
        $export['status'] = $status;
        return $export;
    }
}
