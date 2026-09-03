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
 * Delete skill or placement.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use tool_adminagent\local\placement;
use tool_adminagent\local\skill;

/**
 * Delete skill or placement.
 */
class delete_item extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHA, ''),
            'id' => new external_value(PARAM_INT, ''),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param string $type
     * @param int $id
     * @return array
     */
    public static function execute(string $type, int $id): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('type', 'id'));
        $context = helper::require_manage();
        self::validate_context($context);
        if ($params['type'] === 'skill') {
            skill::delete($params['id']);
        } else if ($params['type'] === 'placement') {
            placement::delete($params['id']);
        } else {
            throw new \invalid_parameter_exception('Invalid type');
        }
        return ['id' => $params['id'], 'deleted' => true];
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'id' => new external_value(PARAM_INT, ''),
            'deleted' => new external_value(PARAM_BOOL, ''),
        ]);
    }
}
