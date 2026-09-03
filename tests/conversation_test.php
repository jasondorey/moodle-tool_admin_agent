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
 * Conversation list and delete tests.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent;

use tool_adminagent\local\conversation;

/**
 * Conversation list and delete tests.
 *
 * @covers \tool_adminagent\local\conversation
 */
final class conversation_test extends \advanced_testcase {

    /**
     * List returns the owner's chats newest first, not other users'.
     */
    public function test_list_for_user_newest_first(): void {
        $this->resetAfterTest();
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $older = conversation::create((int) $owner->id, null, 'overlay');
        $newer = conversation::create((int) $owner->id, null, 'overlay');
        $empty = conversation::create((int) $owner->id, null, 'overlay');
        conversation::create((int) $other->id, null, 'overlay');

        conversation::add_message((int) $older->id, 'user', 'Older chat title here');
        conversation::add_message((int) $newer->id, 'user', 'Newer chat title here');

        $DB->set_field('tool_adminagent_conversation', 'timemodified', time() - 60, ['id' => $older->id]);
        $DB->set_field('tool_adminagent_conversation', 'timemodified', time(), ['id' => $newer->id]);

        $list = conversation::list_for_user((int) $owner->id);
        $this->assertCount(2, $list);
        $this->assertSame((int) $newer->id, $list[0]['id']);
        $this->assertSame((int) $older->id, $list[1]['id']);
        $this->assertNotSame((int) $empty->id, $list[0]['id']);
        $this->assertNotSame((int) $empty->id, $list[1]['id']);
        $this->assertStringContainsString('Newer', $list[0]['title']);
        $this->assertMatchesRegularExpression('#^\d{1,2}/\d{2}/\d{2}$#', $list[0]['datedisplay']);
    }

    /**
     * Delete removes the conversation plus its messages and tool calls.
     */
    public function test_delete_removes_children(): void {
        $this->resetAfterTest();
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $conv = conversation::create((int) $user->id, null, 'overlay');
        $msg = conversation::add_message((int) $conv->id, 'user', 'Delete me');
        conversation::add_toolcall([
            'conversationid' => (int) $conv->id,
            'messageid' => (int) $msg->id,
            'functionname' => 'search_tools',
            'arguments' => '{}',
            'status' => 'done',
        ]);

        conversation::delete((int) $conv->id);

        $this->assertFalse($DB->record_exists('tool_adminagent_conversation', ['id' => $conv->id]));
        $this->assertFalse($DB->record_exists('tool_adminagent_message', ['conversationid' => $conv->id]));
        $this->assertFalse($DB->record_exists('tool_adminagent_toolcall', ['conversationid' => $conv->id]));
    }
}
