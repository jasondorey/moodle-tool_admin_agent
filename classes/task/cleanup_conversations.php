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
 * Retention task.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\task;

/**
 * Purge old conversations and logs.
 */
class cleanup_conversations extends \core\task\scheduled_task {

    /**
     * Name.
     *
     * @return \lang_string
     */
    public function get_name() {
        return get_string('taskcleanup', 'tool_adminagent');
    }

    /**
     * Run.
     */
    public function execute() {
        global $DB;
        $keep = (int) get_config('tool_adminagent', 'conversationretention');
        $logkeep = (int) get_config('tool_adminagent', 'logretention');
        $now = time();
        if ($keep > 0) {
            $cutoff = $now - $keep;
            $ids = $DB->get_fieldset_select('tool_adminagent_conversation', 'id', 'timemodified < ?', [$cutoff]);
            foreach ($ids as $id) {
                // Remove from history; messages stay until log retention (0 = keep forever).
                $DB->delete_records('tool_adminagent_conversation', ['id' => $id]);
            }
        }
        if ($logkeep > 0) {
            $cutoff = $now - $logkeep;
            // Messages whose conversation is already gone.
            $DB->execute("DELETE FROM {tool_adminagent_message} WHERE conversationid NOT IN (SELECT id FROM {tool_adminagent_conversation})
                AND timecreated < ?", [$cutoff]);
            $DB->execute("DELETE FROM {tool_adminagent_toolcall} WHERE conversationid NOT IN (SELECT id FROM {tool_adminagent_conversation})
                AND timecreated < ?", [$cutoff]);
        }
    }
}
