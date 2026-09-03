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
 * Anthropic Claude client.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\ai;

/**
 * Messages API with tools.
 */
class anthropic extends client {

    /**
     * Chat turn.
     *
     * @param array $messages
     * @param array $tools
     * @param array $state
     * @return result
     */
    public function chat(array $messages, array $tools, array $state = []): result {
        $system = '';
        $anthromsgs = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            if ($role === 'system') {
                $system .= $message['content'] . "\n";
                continue;
            }
            if ($role === 'tool') {
                $anthromsgs[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => $message['tool_call_id'] ?? '',
                        'content' => $message['content'] ?? '',
                    ]],
                ];
                continue;
            }
            if ($role === 'assistant' && !empty($message['tool_calls'])) {
                $content = [];
                if (!empty($message['content'])) {
                    $content[] = ['type' => 'text', 'text' => $message['content']];
                }
                foreach ($message['tool_calls'] as $call) {
                    $content[] = [
                        'type' => 'tool_use',
                        'id' => $call['id'],
                        'name' => $call['function']['name'],
                        'input' => json_decode($call['function']['arguments'] ?? '{}', true) ?: new \stdClass(),
                    ];
                }
                $anthromsgs[] = ['role' => 'assistant', 'content' => $content];
                continue;
            }
            $anthromsgs[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $message['content'] ?? '',
            ];
        }

        $body = [
            'model' => $this->model('claude-sonnet-4-5'),
            'max_tokens' => 4096,
            'messages' => $anthromsgs,
        ];
        if ($system !== '') {
            $body['system'] = $system;
        }
        if ($tools) {
            $body['tools'] = [];
            foreach ($tools as $tool) {
                $fn = $tool['function'] ?? $tool;
                $body['tools'][] = [
                    'name' => $fn['name'],
                    'description' => $fn['description'] ?? '',
                    'input_schema' => $fn['parameters'] ?? ['type' => 'object'],
                ];
            }
        }
        if (get_config('tool_adminagent', 'anthropic_web_fetch')) {
            $body['tools'][] = ['type' => 'web_fetch_20250910', 'name' => 'web_fetch'];
        }
        $endpoint = rtrim((string) ($this->config['endpoint'] ?? 'https://api.anthropic.com'), '/');
        $decoded = $this->post_json($endpoint . '/v1/messages', [
            'Content-Type' => 'application/json',
            'x-api-key' => $this->apikey(),
            'anthropic-version' => '2023-06-01',
        ], $body);

        $result = new result();
        $result->inputtokens = (int) ($decoded['usage']['input_tokens'] ?? 0);
        $result->outputtokens = (int) ($decoded['usage']['output_tokens'] ?? 0);
        foreach ($decoded['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $result->text .= $block['text'] ?? '';
            }
            if (($block['type'] ?? '') === 'tool_use') {
                $result->toolcalls[] = [
                    'id' => $block['id'] ?? uniqid('call_'),
                    'name' => $block['name'] ?? '',
                    'arguments' => json_encode($block['input'] ?? new \stdClass()),
                ];
            }
        }
        return $result;
    }
}
