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
 * Fake chat client tests.
 *
 * @package    tool_adminagent
 * @category   test
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent;

use tool_adminagent\ai\fake;

/**
 * Fake chat client tests.
 *
 * @covers \tool_adminagent\ai\fake
 */
final class fake_client_test extends \advanced_testcase {

    /**
     * The first turn requests search_tools.
     */
    public function test_first_turn_requests_search_tools(): void {
        $client = new fake([]);
        $result = $client->chat([['role' => 'user', 'content' => 'Hello']], []);
        $this->assertNotEmpty($result->toolcalls);
        $this->assertSame('search_tools', $result->toolcalls[0]['name']);
        $this->assertSame('', $result->text);
    }

    /**
     * After a non-search tool result the driver returns a canned reply.
     */
    public function test_after_tool_result_returns_text(): void {
        $this->resetAfterTest();
        $client = new fake([]);
        $result = $client->chat([
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'tool', 'name' => 'get_unix_timestamp', 'content' => '{}'],
        ], []);
        $this->assertSame([], $result->toolcalls);
        $this->assertSame(get_string('testdriverreply', 'tool_adminagent'), $result->text);
    }

    /**
     * Prefetch hint in the user message requests a follow-up function.
     */
    public function test_matched_functions_hint_requests_follow_up(): void {
        $client = new fake([]);
        $result = $client->chat([
            ['role' => 'user', 'content' => "Hello\n\n[Matched functions: core_course_duplicate_course]"],
        ], []);
        $this->assertNotEmpty($result->toolcalls);
        $this->assertSame('get_unix_timestamp', $result->toolcalls[0]['name']);
        $this->assertSame('', $result->text);
    }
}
