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
 * Approve or deny a function call.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use tool_adminagent\local\agent;
use tool_adminagent\local\conversation;

/**
 * Approve or deny a function call.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resolve_call extends external_api {

    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'conversationid' => new external_value(PARAM_INT, 'Conversation id'),
            'callid' => new external_value(PARAM_INT, 'Stored function call id'),
            'approve' => new external_value(PARAM_BOOL, 'True to run the call'),
        ]);
    }

    /**
     * Resolve a pending call and continue the agent loop.
     *
     * @param int $conversationid
     * @param int $callid
     * @param bool $approve
     * @return array
     */
    public static function execute(int $conversationid, int $callid, bool $approve): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('conversationid', 'callid', 'approve'));
        $context = helper::require_use();
        self::validate_context($context);
        $conv = conversation::require($params['conversationid'], $USER->id);
        return agent::resolve($conv, $params['callid'], $params['approve']);
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return helper::conversation_returns();
    }
}
