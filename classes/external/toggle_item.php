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
 * Toggle enabled flags.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use tool_adminagent\local\catalog;
use tool_adminagent\local\placement;
use tool_adminagent\local\skill;

/**
 * Toggle enabled flags.
 */
class toggle_item extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHA, 'skill, placement or function'),
            'id' => new external_value(PARAM_INT, ''),
            'enabled' => new external_value(PARAM_BOOL, ''),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param string $type
     * @param int $id
     * @param bool $enabled
     * @return array
     */
    public static function execute(string $type, int $id, bool $enabled): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('type', 'id', 'enabled'));
        $context = helper::require_manage();
        self::validate_context($context);
        match ($params['type']) {
            'skill' => skill::set_enabled($params['id'], (int) $params['enabled']),
            'placement' => placement::set_enabled($params['id'], (int) $params['enabled']),
            'function' => catalog::set_enabled($params['id'], (int) $params['enabled']),
            default => throw new \invalid_parameter_exception('Invalid type'),
        };
        return ['id' => $params['id'], 'enabled' => $params['enabled']];
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'id' => new external_value(PARAM_INT, ''),
            'enabled' => new external_value(PARAM_BOOL, ''),
        ]);
    }
}
