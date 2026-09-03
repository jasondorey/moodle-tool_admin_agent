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
 * Delete a conversation the current user owns.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_adminagent\local\conversation;

/**
 * Delete a conversation.
 */
class delete_conversation extends external_api {

    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'conversationid' => new external_value(PARAM_INT, 'Conversation id'),
        ]);
    }

    /**
     * Delete a conversation the current user owns.
     *
     * @param int $conversationid
     * @return array
     */
    public static function execute(int $conversationid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('conversationid'));
        $context = helper::require_use();
        self::validate_context($context);
        conversation::require($params['conversationid'], $USER->id);
        conversation::delete($params['conversationid']);
        return ['id' => $params['conversationid'], 'deleted' => true];
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, ''),
            'deleted' => new external_value(PARAM_BOOL, ''),
        ]);
    }
}
