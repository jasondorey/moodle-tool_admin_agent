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
 * Canned chat client for automated tests.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\ai;

/**
 * Deterministic driver used during Behat so tests never call a live LLM.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake extends client {

    /**
     * Search first when nothing has been discovered; after search_tools, request
     * a read that still needs approval; after any other tool, return a canned reply.
     *
     * @param array $messages
     * @param array $tools
     * @param array $state
     * @return result
     */
    public function chat(array $messages, array $tools, array $state = []): result {
        $out = new result();
        $hassearch = false;
        $hasother = false;
        $hasmatched = false;
        foreach ($messages as $message) {
            $content = (string) ($message['content'] ?? '');
            if (str_contains($content, 'Matched functions:')) {
                $hasmatched = true;
            }
            if (($message['role'] ?? '') !== 'tool') {
                continue;
            }
            if (($message['name'] ?? '') === 'search_tools') {
                $hassearch = true;
            } else {
                $hasother = true;
            }
        }
        if ($hasother) {
            $out->text = get_string('testdriverreply', 'tool_adminagent');
            return $out;
        }
        if ($hassearch || $hasmatched) {
            $out->toolcalls[] = [
                'id' => 'call_behat_timestamp',
                'name' => 'get_unix_timestamp',
                'arguments' => json_encode(new \stdClass()),
            ];
            return $out;
        }
        $out->toolcalls[] = [
            'id' => 'call_behat_search',
            'name' => 'search_tools',
            'arguments' => json_encode(['prompt' => 'list available functions']),
        ];
        return $out;
    }
}
