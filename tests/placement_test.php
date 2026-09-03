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
 * Placement URL mask tests.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent;

use tool_adminagent\local\placement;

/**
 * Placement URL mask tests.
 *
 * @covers \tool_adminagent\local\placement
 */
final class placement_test extends \advanced_testcase {

    /**
     * Placement URL masks match paths.
     */
    public function test_url_masks(): void {
        $this->assertTrue(placement::url_matches('/course/view.php?id=2', '/*'));
        $this->assertTrue(placement::url_matches('/admin/settings.php?section=tool_adminagent', '/admin/settings.php*'));
        $this->assertFalse(placement::url_matches('/course/view.php', '/admin/*'));
    }

    /**
     * Default placement is installed.
     */
    public function test_default_placement_install(): void {
        $this->resetAfterTest();
        global $DB;
        $DB->delete_records('tool_adminagent_placement');
        placement::install_defaults();
        $this->assertGreaterThan(0, $DB->count_records('tool_adminagent_placement'));
        $row = $DB->get_record('tool_adminagent_placement', ['urlmasks' => '/*'], '*', MUST_EXIST);
        $this->assertSame('floating', $row->location);
    }
}
