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
 * Record thumbs up/down on an assistant message.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use tool_adminagent\local\conversation;

/**
 * Record thumbs up/down on an assistant message.
 */
class submit_feedback extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'messageid' => new external_value(PARAM_INT, ''),
            'rating' => new external_value(PARAM_ALPHA, 'up or down'),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $messageid
     * @param string $rating
     * @return array
     */
    public static function execute(int $messageid, string $rating): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('messageid', 'rating'));
        $context = helper::require_use();
        self::validate_context($context);
        if (!get_config('tool_adminagent', 'allowfeedback')) {
            throw new \moodle_exception('errorfeedbackdisabled', 'tool_adminagent');
        }
        $rating = $params['rating'] === 'down' ? 'down' : 'up';
        $message = $DB->get_record('tool_adminagent_message', ['id' => $params['messageid']], '*', MUST_EXIST);
        conversation::require($message->conversationid, $USER->id);
        $DB->set_field('tool_adminagent_message', 'feedback', $rating, ['id' => $message->id]);
        return ['id' => $message->id, 'rating' => $rating];
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'id' => new external_value(PARAM_INT, ''),
            'rating' => new external_value(PARAM_ALPHA, ''),
        ]);
    }
}
