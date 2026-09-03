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
 * Gemini client.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\ai;

/**
 * generateContent with function calling.
 */
class gemini extends client {

    /**
     * Chat turn.
     *
     * @param array $messages
     * @param array $tools
     * @param array $state
     * @return result
     */
    public function chat(array $messages, array $tools, array $state = []): result {
        $model = $this->model('gemini-2.0-flash');
        $key = $this->apikey();
        $endpoint = rtrim((string) ($this->config['endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $url = $endpoint . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);

        $contents = [];
        $system = '';
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            if ($role === 'system') {
                $system .= $message['content'] . "\n";
                continue;
            }
            if ($role === 'tool') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $message['name'] ?? 'tool',
                            'response' => json_decode($message['content'] ?? '{}', true) ?: ['result' => $message['content']],
                        ],
                    ]],
                ];
                continue;
            }
            if ($role === 'assistant' && !empty($message['tool_calls'])) {
                $parts = [];
                foreach ($message['tool_calls'] as $call) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => $call['function']['name'] ?? '',
                            'args' => json_decode($call['function']['arguments'] ?? '{}', true) ?: new \stdClass(),
                        ],
                    ];
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];
                continue;
            }
            $contents[] = [
                'role' => $role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content'] ?? '']],
            ];
        }

        $body = ['contents' => $contents];
        if ($system !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        if ($tools) {
            $decls = [];
            foreach ($tools as $tool) {
                $fn = $tool['function'] ?? $tool;
                $decls[] = [
                    'name' => $fn['name'],
                    'description' => $fn['description'] ?? '',
                    'parameters' => $fn['parameters'] ?? ['type' => 'object'],
                ];
            }
            $body['tools'] = [['functionDeclarations' => $decls]];
        }
        $decoded = $this->post_json($url, ['Content-Type' => 'application/json'], $body);
        $result = new result();
        $parts = $decoded['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (!empty($part['text'])) {
                $result->text .= $part['text'];
            }
            if (!empty($part['functionCall'])) {
                $result->toolcalls[] = [
                    'id' => uniqid('call_'),
                    'name' => $part['functionCall']['name'] ?? '',
                    'arguments' => json_encode($part['functionCall']['args'] ?? new \stdClass()),
                ];
            }
        }
        $result->inputtokens = (int) ($decoded['usageMetadata']['promptTokenCount'] ?? 0);
        $result->outputtokens = (int) ($decoded['usageMetadata']['candidatesTokenCount'] ?? 0);
        return $result;
    }
}
