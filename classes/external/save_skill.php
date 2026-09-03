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
 * Save a skill.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use tool_adminagent\local\skill;

/**
 * Save a skill.
 */
class save_skill extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'shortname' => new external_value(PARAM_ALPHANUMEXT, ''),
            'name' => new external_value(PARAM_TEXT, ''),
            'description' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, ''),
            'instructions' => new external_value(PARAM_RAW, ''),
            'mainfunctions' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, ''),
            'otherfunctions' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, ''),
            'enabled' => new external_value(PARAM_BOOL, '', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $id
     * @param string $shortname
     * @param string $name
     * @param string $description
     * @param string $instructions
     * @param string $mainfunctions
     * @param string $otherfunctions
     * @param bool $enabled
     * @return array
     */
    public static function execute(
        int $id = 0,
        string $shortname = '',
        string $name = '',
        string $description = '',
        string $instructions = '',
        string $mainfunctions = '',
        string $otherfunctions = '',
        bool $enabled = true
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'id', 'shortname', 'name', 'description', 'instructions', 'mainfunctions', 'otherfunctions', 'enabled'
        ));
        $context = helper::require_manage();
        self::validate_context($context);
        $skill = skill::save($params);
        $export = skill::export($skill);
        $export['id'] = $skill->id;
        return $export;
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'id' => new external_value(PARAM_INT, ''),
            'formatversion' => new external_value(PARAM_INT, ''),
            'shortname' => new external_value(PARAM_ALPHANUMEXT, ''),
            'name' => new external_value(PARAM_TEXT, ''),
            'description' => new external_value(PARAM_RAW, ''),
            'instructions' => new external_value(PARAM_RAW, ''),
            'mainfunctions' => new \core_external\external_multiple_structure(new external_value(PARAM_RAW, '')),
            'otherfunctions' => new \core_external\external_multiple_structure(new external_value(PARAM_RAW, '')),
            'enabled' => new external_value(PARAM_BOOL, ''),
        ]);
    }
}
