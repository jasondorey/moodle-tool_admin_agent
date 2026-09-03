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
 * Catalog, approval and discovery unit tests.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent;

use tool_adminagent\local\approval;
use tool_adminagent\local\catalog;
use tool_adminagent\local\discovery;
use tool_adminagent\local\skill;

/**
 * Catalog, approval and discovery unit tests.
 *
 * @covers \tool_adminagent\local\catalog
 * @covers \tool_adminagent\local\approval
 * @covers \tool_adminagent\local\discovery
 */
final class catalog_test extends \advanced_testcase {

    /**
     * Glob patterns match function names.
     */
    public function test_glob_matching(): void {
        $this->assertTrue(catalog::matches('mod_lesson/*', 'mod_lesson_get_pages'));
        $this->assertTrue(catalog::matches('x_mod_*_create_modules', 'x_mod_label_create_modules'));
        $this->assertFalse(catalog::matches('mod_lesson/*', 'mod_quiz_get_attempt'));
        $this->assertTrue(catalog::matches('core_course_create_courses', 'core_course_create_courses'));
    }

    /**
     * Default enabled flags follow fixture relevance.
     */
    public function test_default_enabled_rules(): void {
        $this->assertSame(1, catalog::default_enabled('core_course_create_courses', 'Write', 9, false));
        $this->assertSame(0, catalog::default_enabled('core_course_tiny', 'Write AJAX', 2, false));
        $this->assertSame(0, catalog::default_enabled('auth_email_signup_user', 'Write AJAX Non-auth', 2, false));
        $this->assertSame(1, catalog::default_enabled('local_foo_do_thing', 'Write', 0, false));
        $this->assertSame(0, catalog::default_enabled('local_foo_ajax', 'Read AJAX', 0, false));
    }

    /**
     * Write types are detected.
     */
    public function test_is_write(): void {
        $this->assertTrue(catalog::is_write('Write AJAX'));
        $this->assertFalse(catalog::is_write('Read AJAX'));
    }

    /**
     * Auto-approve modes allow the expected function types.
     */
    public function test_approval_modes(): void {
        $read = (object) ['type' => 'Read', 'tags' => json_encode(['read'])];
        $write = (object) ['type' => 'Write', 'tags' => json_encode(['create'])];
        $this->assertFalse(approval::auto_allows(approval::NONE, $read));
        $search = (object) ['type' => 'Read', 'tags' => json_encode(['utility']), 'functionname' => 'search_tools'];
        $this->assertTrue(approval::auto_allows(approval::NONE, $search));
        $this->assertTrue(approval::auto_allows(approval::READONLY, $read));
        $this->assertFalse(approval::auto_allows(approval::READONLY, $write));
        $this->assertTrue(approval::auto_allows(approval::SAFE, $read));
        $this->assertFalse(approval::auto_allows(approval::SAFE, $write));
        $this->assertTrue(approval::auto_allows(approval::ALL, $write));
    }

    /**
     * Discovery tokenises a prompt.
     */
    public function test_discovery_tokens(): void {
        $tokens = discovery::tokens('Create a course titled Sandbox Temp in category Sandbox');
        $this->assertContains('create', $tokens);
        $this->assertContains('course', $tokens);
        $this->assertNotContains('a', $tokens);
    }

    /**
     * Skill function lists encode and decode.
     */
    public function test_skill_list_roundtrip(): void {
        $encoded = skill::encode_list("core_course_create_courses\nmod_lesson/*");
        $decoded = skill::decode_list($encoded);
        $this->assertSame(['core_course_create_courses', 'mod_lesson/*'], $decoded);
    }

    /**
     * Skills can be saved and deleted.
     */
    public function test_skill_crud(): void {
        $this->resetAfterTest();
        $skill = skill::save([
            'shortname' => 'test_skill',
            'name' => 'Test skill',
            'description' => 'd',
            'instructions' => 'Do the thing',
            'mainfunctions' => ['core_course_get_courses'],
            'otherfunctions' => [],
            'enabled' => 1,
        ]);
        $this->assertNotEmpty($skill->id);
        $this->assertSame('test_skill', skill::get($skill->id)->shortname);
        skill::set_enabled($skill->id, 0);
        $this->assertSame(0, (int) skill::get($skill->id)->enabled);
        skill::delete($skill->id);
        $this->assertNull(skill::get($skill->id));
    }

    /**
     * Discovery ranks course-create functions.
     */
    public function test_discovery_search_ranks_course_create(): void {
        $rows = [
            (object) [
                'functionname' => 'core_course_create_courses',
                'description' => 'Create courses',
                'tags' => 'course,create',
                'relevance' => 9,
            ],
            (object) [
                'functionname' => 'mod_forum_get_forums_by_courses',
                'description' => 'List forums',
                'tags' => 'forum',
                'relevance' => 4,
            ],
        ];
        $names = discovery::search('create a course titled Sandbox', $rows, 5);
        $this->assertSame('core_course_create_courses', $names[0]);
    }

    /**
     * Topic-change detection treats a new capability question as a new task.
     */
    public function test_discovery_is_new_task(): void {
        $rows = [
            (object) [
                'functionname' => 'core_cohort_create_cohorts',
                'description' => 'Creates new cohorts',
                'tags' => 'admin,create',
                'relevance' => 8,
            ],
            (object) [
                'functionname' => 'core_user_create_users',
                'description' => 'Create user accounts',
                'tags' => 'admin,create',
                'relevance' => 9,
            ],
        ];
        $this->assertTrue(discovery::is_new_task(
            'can you create a user',
            ['core_cohort_create_cohorts'],
            $rows
        ));
        $this->assertFalse(discovery::is_new_task('yes', ['core_cohort_create_cohorts'], $rows));
        $this->assertFalse(discovery::is_new_task(
            'create another cohort',
            ['core_cohort_create_cohorts'],
            $rows
        ));
    }

    /**
     * External descriptions convert to JSON schema.
     */
    public function test_external_to_schema_converts_values(): void {
        $value = new \core_external\external_value(PARAM_INT, 'Course id');
        $schema = catalog::external_to_schema($value);
        $this->assertSame('number', $schema['type']);
        $this->assertSame('Course id', $schema['description']);

        $single = new \core_external\external_single_structure([
            'fullname' => new \core_external\external_value(PARAM_TEXT, 'Name'),
            'optionalnote' => new \core_external\external_value(PARAM_TEXT, 'Note', VALUE_OPTIONAL),
        ]);
        $object = catalog::external_to_schema($single);
        $this->assertSame('object', $object['type']);
        $this->assertContains('fullname', $object['required']);
        $this->assertNotContains('optionalnote', $object['required']);
    }
}
