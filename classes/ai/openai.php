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
 * OpenAI / Azure OpenAI client.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\ai;

/**
 * Chat Completions with optional Responses API.
 */
class openai extends client {

    /**
     * Chat turn.
     *
     * @param array $messages
     * @param array $tools
     * @param array $state
     * @return result
     */
    public function chat(array $messages, array $tools, array $state = []): result {
        $type = get_config('tool_adminagent', 'openai_api_type') ?: 'responses';
        if ($type === 'responses' && str_contains($this->endpoint(), 'api.openai.com')) {
            return $this->responses($messages, $tools, $state);
        }
        return $this->completions($messages, $tools);
    }

    /**
     * Chat Completions.
     *
     * @param array $messages
     * @param array $tools
     * @return result
     */
    protected function completions(array $messages, array $tools): result {
        $url = rtrim($this->endpoint(), '/') . '/chat/completions';
        $body = [
            'model' => $this->model('gpt-4o'),
            'messages' => $messages,
        ];
        if ($tools) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apikey(),
        ];
        if (!empty($this->config['api-key']) && str_contains($url, 'azure')) {
            $headers['api-key'] = $this->config['api-key'];
            unset($headers['Authorization']);
        }
        $decoded = $this->post_json($url, $headers, $body);
        $choice = $decoded['choices'][0]['message'] ?? [];
        $result = new result();
        $result->text = (string) ($choice['content'] ?? '');
        $result->inputtokens = (int) ($decoded['usage']['prompt_tokens'] ?? 0);
        $result->outputtokens = (int) ($decoded['usage']['completion_tokens'] ?? 0);
        foreach ($choice['tool_calls'] ?? [] as $call) {
            $result->toolcalls[] = [
                'id' => $call['id'] ?? uniqid('call_'),
                'name' => $call['function']['name'] ?? '',
                'arguments' => $call['function']['arguments'] ?? '{}',
            ];
        }
        return $result;
    }

    /**
     * Responses API.
     *
     * @param array $messages
     * @param array $tools
     * @param array $state
     * @return result
     */
    protected function responses(array $messages, array $tools, array $state): result {
        $url = rtrim($this->endpoint(), '/') . '/responses';
        $custom = trim((string) get_config('tool_adminagent', 'responses_endpoints'));
        if ($custom) {
            $lines = preg_split('/\s+/', $custom);
            $url = $lines[0];
        }
        $input = $this->responses_input($messages);
        $system = '';
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $system .= $message['content'] . "\n";
            }
        }
        $body = [
            'model' => $this->model('gpt-4o'),
            'input' => $input,
        ];
        if ($system !== '') {
            $body['instructions'] = $system;
        }
        if (!empty($state['response_id'])) {
            $continuation = $this->responses_continuation($input);
            if ($continuation) {
                $body['previous_response_id'] = $state['response_id'];
                $body['input'] = $continuation;
            }
        }
        if ($tools) {
            $body['tools'] = $this->responses_tools($tools);
        }
        $decoded = $this->post_json($url, [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apikey(),
        ], $body);
        $result = new result();
        $result->state['response_id'] = $decoded['id'] ?? '';
        $result->inputtokens = (int) ($decoded['usage']['input_tokens'] ?? 0);
        $result->outputtokens = (int) ($decoded['usage']['output_tokens'] ?? 0);
        foreach ($decoded['output'] ?? [] as $item) {
            if (($item['type'] ?? '') === 'message') {
                foreach ($item['content'] ?? [] as $c) {
                    $result->text .= $c['text'] ?? '';
                }
            }
            if (($item['type'] ?? '') === 'function_call') {
                $result->toolcalls[] = [
                    'id' => $item['call_id'] ?? $item['id'] ?? uniqid('call_'),
                    'name' => $item['name'] ?? '',
                    'arguments' => $item['arguments'] ?? '{}',
                ];
            }
        }
        return $result;
    }

    /**
     * Convert Chat Completions messages to Responses API input items.
     *
     * @param array $messages
     * @return array
     */
    protected function responses_input(array $messages): array {
        $input = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            if ($role === 'system') {
                continue;
            }
            if ($role === 'tool') {
                $input[] = [
                    'type' => 'function_call_output',
                    'call_id' => (string) ($message['tool_call_id'] ?? ''),
                    'output' => (string) ($message['content'] ?? '{}'),
                ];
                continue;
            }
            if ($role === 'assistant') {
                $content = (string) ($message['content'] ?? '');
                if ($content !== '') {
                    $input[] = [
                        'role' => 'assistant',
                        'content' => $content,
                    ];
                }
                foreach ($message['tool_calls'] ?? [] as $call) {
                    $input[] = [
                        'type' => 'function_call',
                        'call_id' => (string) ($call['id'] ?? ''),
                        'name' => (string) ($call['function']['name'] ?? ''),
                        'arguments' => (string) ($call['function']['arguments'] ?? '{}'),
                    ];
                }
                continue;
            }
            $input[] = [
                'role' => 'user',
                'content' => (string) ($message['content'] ?? ''),
            ];
        }
        return $input;
    }

    /**
     * New function outputs to send with previous_response_id.
     *
     * @param array $input
     * @return array
     */
    protected function responses_continuation(array $input): array {
        $new = [];
        for ($i = count($input) - 1; $i >= 0; $i--) {
            $item = $input[$i];
            if (($item['type'] ?? '') === 'function_call_output') {
                array_unshift($new, $item);
                continue;
            }
            break;
        }
        return $new;
    }

    /**
     * Convert Chat Completions tool definitions to the Responses API shape.
     *
     * @param array $tools
     * @return array
     */
    protected function responses_tools(array $tools): array {
        $out = [];
        foreach ($tools as $tool) {
            $fn = $tool['function'] ?? $tool;
            $out[] = [
                'type' => 'function',
                'name' => $fn['name'] ?? '',
                'description' => $fn['description'] ?? '',
                'parameters' => $fn['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ];
        }
        return $out;
    }

    /**
     * Provider base URL without a completions/responses suffix.
     *
     * @return string
     */
    protected function endpoint(): string {
        $raw = rtrim((string) ($this->config['endpoint'] ?? $this->config['baseurl'] ?? $this->config['url'] ?? 'https://api.openai.com/v1'), '/');
        foreach (['/chat/completions', '/completions', '/responses'] as $suffix) {
            if (str_ends_with($raw, $suffix)) {
                $raw = substr($raw, 0, -strlen($suffix));
            }
        }
        $raw = rtrim($raw, '/');
        return $raw !== '' ? $raw : 'https://api.openai.com/v1';
    }
}
