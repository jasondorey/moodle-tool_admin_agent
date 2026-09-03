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
 * Send a chat message.
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
use tool_adminagent\local\approval;
use tool_adminagent\local\conversation;

/**
 * Send a chat message.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {

    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'conversationid' => new external_value(PARAM_INT, 'Conversation id'),
            'message' => new external_value(PARAM_RAW, 'User message'),
            'pageurl' => new external_value(PARAM_RAW, 'Current page path', VALUE_DEFAULT, ''),
            'autoapprove' => new external_value(PARAM_ALPHANUMEXT, 'Auto-approve mode', VALUE_DEFAULT, 'none'),
            'attachments' => new external_value(PARAM_RAW, 'JSON list of attached filenames', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Send a user message and run the agent loop.
     *
     * @param int $conversationid
     * @param string $message
     * @param string $pageurl
     * @param string $autoapprove
     * @param string $attachments
     * @return array
     */
    public static function execute(
        int $conversationid,
        string $message,
        string $pageurl = '',
        string $autoapprove = 'none',
        string $attachments = ''
    ): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('conversationid', 'message', 'pageurl', 'autoapprove', 'attachments')
        );
        $context = helper::require_use();
        self::validate_context($context);
        $conv = conversation::require($params['conversationid'], $USER->id);
        conversation::update($conv->id, ['autoapprove' => approval::normalise($params['autoapprove'])]);
        $conv->autoapprove = approval::normalise($params['autoapprove']);
        $text = $params['message'];
        if ($params['attachments'] !== '') {
            $names = json_decode($params['attachments'], true);
            if (is_array($names) && $names) {
                $text .= "\n\n[Attached files: " . implode(', ', $names) . ']';
            }
        }
        try {
            return agent::turn($conv, $text, $params['pageurl']);
        } catch (\moodle_exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', $e->getMessage());
        }
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
