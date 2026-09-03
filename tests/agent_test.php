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
 * Agent discovery prefetch tests.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent;

use tool_adminagent\local\agent;
use tool_adminagent\local\catalog;
use tool_adminagent\local\conversation;

/**
 * Agent discovery prefetch tests.
 *
 * @covers \tool_adminagent\local\agent
 */
final class agent_test extends \advanced_testcase {

    /**
     * A new user message records a completed search_tools call when discovery is on.
     */
    public function test_prefetch_search_records_completed_call(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        catalog::sync();
        set_config('dynamicdiscovery', 'on', 'tool_adminagent');

        $user = \core_user::get_user($GLOBALS['USER']->id);
        $conv = conversation::create((int) $user->id, null, 'overlay');
        $msg = conversation::add_message((int) $conv->id, 'user', 'duplicate a course');
        $this->assertTrue(agent::prefetch_search($conv, 'duplicate a course', (int) $msg->id));

        $calls = conversation::toolcalls($conv->id);
        $this->assertCount(1, $calls);
        $this->assertSame('search_tools', $calls[0]->functionname);
        $this->assertSame((int) $msg->id, (int) $calls[0]->messageid);
        $this->assertSame('done', $calls[0]->status);
        $decoded = json_decode($calls[0]->result, true);
        $this->assertIsArray($decoded['tools'] ?? null);
        $this->assertContains('core_course_duplicate_course', $decoded['tools']);
    }

    /**
     * Prefetch search is not sent to the provider as a function_call.
     */
    public function test_prefetch_search_omitted_from_llm_messages(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        catalog::sync();
        set_config('dynamicdiscovery', 'on', 'tool_adminagent');

        $user = \core_user::get_user($GLOBALS['USER']->id);
        $conv = conversation::create((int) $user->id, null, 'overlay');
        $msg = conversation::add_message((int) $conv->id, 'user', 'duplicate a course');
        agent::prefetch_search($conv, 'duplicate a course', (int) $msg->id);

        $messages = agent::llm_messages($conv);
        foreach ($messages as $message) {
            $this->assertNotSame('tool', $message['role'] ?? '');
            $this->assertArrayNotHasKey('tool_calls', $message);
            if (($message['role'] ?? '') === 'user') {
                $this->assertStringContainsString('Matched functions:', $message['content']);
            }
        }
    }

    /**
     * Discovery off does not prefetch search_tools.
     */
    public function test_prefetch_search_skipped_when_discovery_off(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        catalog::sync();
        set_config('dynamicdiscovery', 'off', 'tool_adminagent');

        $user = \core_user::get_user($GLOBALS['USER']->id);
        $conv = conversation::create((int) $user->id, null, 'overlay');
        $this->assertFalse(agent::prefetch_search($conv, 'duplicate a course', 0));
        $this->assertSame([], conversation::toolcalls($conv->id));
    }

    /**
     * A follow-up that is a new task refreshes discovery without a second card.
     */
    public function test_prefetch_search_refreshes_on_new_task(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        catalog::sync();
        set_config('dynamicdiscovery', 'on', 'tool_adminagent');

        $user = \core_user::get_user($GLOBALS['USER']->id);
        $conv = conversation::create((int) $user->id, null, 'overlay');
        $first = conversation::add_message((int) $conv->id, 'user', 'create a cohort');
        $this->assertTrue(agent::prefetch_search($conv, 'create a cohort', (int) $first->id));
        $this->assertCount(1, conversation::toolcalls($conv->id));
        $aftercohort = agent::discovered_names($conv);
        $this->assertContains('core_cohort_create_cohorts', $aftercohort);

        $second = conversation::add_message((int) $conv->id, 'user', 'can you create a user');
        $ran = agent::prefetch_search($conv, 'can you create a user', (int) $second->id);
        $this->assertCount(1, conversation::toolcalls($conv->id));
        $afteruser = agent::discovered_names($conv);
        $this->assertContains('core_user_create_users', $afteruser);
        if (!in_array('core_user_create_users', $aftercohort, true)) {
            $this->assertTrue($ran);
        }
    }

    /**
     * A same-topic follow-up does not replace the discovered set.
     */
    public function test_prefetch_search_skips_same_topic(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        catalog::sync();
        set_config('dynamicdiscovery', 'on', 'tool_adminagent');

        $user = \core_user::get_user($GLOBALS['USER']->id);
        $conv = conversation::create((int) $user->id, null, 'overlay');
        $first = conversation::add_message((int) $conv->id, 'user', 'create a cohort');
        agent::prefetch_search($conv, 'create a cohort', (int) $first->id);
        $before = agent::discovered_names($conv);
        $second = conversation::add_message((int) $conv->id, 'user', 'create another cohort');
        $this->assertFalse(agent::prefetch_search($conv, 'create another cohort', (int) $second->id));
        $this->assertSame($before, agent::discovered_names($conv));
        $this->assertCount(1, conversation::toolcalls($conv->id));
    }

    /**
     * A short follow-up does not replace the discovered set.
     */
    public function test_prefetch_search_skips_short_follow_up(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        catalog::sync();
        set_config('dynamicdiscovery', 'on', 'tool_adminagent');

        $user = \core_user::get_user($GLOBALS['USER']->id);
        $conv = conversation::create((int) $user->id, null, 'overlay');
        $first = conversation::add_message((int) $conv->id, 'user', 'duplicate a course');
        agent::prefetch_search($conv, 'duplicate a course', (int) $first->id);
        $before = agent::discovered_names($conv);
        $second = conversation::add_message((int) $conv->id, 'user', 'yes');
        $this->assertFalse(agent::prefetch_search($conv, 'yes', (int) $second->id));
        $this->assertSame($before, agent::discovered_names($conv));
        $this->assertCount(1, conversation::toolcalls($conv->id));
    }
}
