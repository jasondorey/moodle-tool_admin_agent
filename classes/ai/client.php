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
 * Chat client contract.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\ai;

/**
 * Multi-turn tool-calling client.
 */
abstract class client {

    /** @var array */
    protected array $config;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Run one model turn.
     *
     * @param array $messages
     * @param array $tools OpenAI-style tool definitions.
     * @param array $state
     * @return result
     */
    abstract public function chat(array $messages, array $tools, array $state = []): result;

    /**
     * HTTP JSON POST.
     *
     * @param string $url
     * @param array $headers
     * @param array $body
     * @return array
     */
    protected function post_json(string $url, array $headers, array $body): array {
        $client = new \core\http_client([
            'headers' => $headers,
            'timeout' => 120,
            'http_errors' => false,
        ]);
        try {
            $response = $client->post($url, ['json' => $body]);
        } catch (\Throwable $e) {
            throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', 'AI provider error: ' . $e->getMessage());
        }
        $code = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $decoded = json_decode($raw, true);
        if ($code >= 400 || !is_array($decoded)) {
            $msg = is_array($decoded) ? ($decoded['error']['message'] ?? json_encode($decoded)) : (string) $raw;
            throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', 'AI provider error (' . $code . '): ' . $msg);
        }
        return $decoded;
    }

    /**
     * API key from config.
     *
     * @return string
     */
    protected function apikey(): string {
        return (string) ($this->config['apikey'] ?? $this->config['api_key'] ?? $this->config['key'] ?? '');
    }

    /**
     * Model name.
     *
     * @param string $default
     * @return string
     */
    protected function model(string $default): string {
        return (string) ($this->config['model'] ?? $this->config['modelid'] ?? $default);
    }
}
