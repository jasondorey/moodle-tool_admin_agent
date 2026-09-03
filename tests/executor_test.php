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
 * Executor truncation tests.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent;

use tool_adminagent\local\executor;

/**
 * Executor truncation tests.
 *
 * @covers \tool_adminagent\local\executor
 */
final class executor_test extends \advanced_testcase {

    /**
     * Large function results are truncated.
     */
    public function test_truncate_respects_limit(): void {
        $this->resetAfterTest();
        set_config('maxfunctionresultsize', 1, 'tool_adminagent'); // 1 KB.
        $big = ['data' => str_repeat('x', 3000)];
        $out = executor::truncate($big);
        $this->assertTrue($out['truncated']);
        $small = ['ok' => true];
        $this->assertSame($small, executor::truncate($small));
    }
}
