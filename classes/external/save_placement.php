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
 * Save a placement.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use tool_adminagent\local\placement;

/**
 * Save a placement.
 */
class save_placement extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, ''),
            'urlmasks' => new external_value(PARAM_RAW, ''),
            'location' => new external_value(PARAM_ALPHANUMEXT, '', VALUE_DEFAULT, 'floating'),
            'skillids' => new external_multiple_structure(new external_value(PARAM_INT, ''), '', VALUE_DEFAULT, []),
            'welcome' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, ''),
            'enabled' => new external_value(PARAM_BOOL, '', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $id
     * @param string $name
     * @param string $urlmasks
     * @param string $location
     * @param array $skillids
     * @param string $welcome
     * @param bool $enabled
     * @return array
     */
    public static function execute(
        int $id = 0,
        string $name = '',
        string $urlmasks = '',
        string $location = 'floating',
        array $skillids = [],
        string $welcome = '',
        bool $enabled = true
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'id', 'name', 'urlmasks', 'location', 'skillids', 'welcome', 'enabled'
        ));
        $context = helper::require_manage();
        self::validate_context($context);
        $p = placement::save($params);
        return [
            'id' => $p->id,
            'name' => $p->name,
            'urlmasks' => $p->urlmasks,
            'location' => $p->location,
            'skillids' => placement::skill_ids($p),
            'welcome' => $p->welcome,
            'enabled' => (bool) $p->enabled,
        ];
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'id' => new external_value(PARAM_INT, ''),
            'name' => new external_value(PARAM_TEXT, ''),
            'urlmasks' => new external_value(PARAM_RAW, ''),
            'location' => new external_value(PARAM_ALPHANUMEXT, ''),
            'skillids' => new external_multiple_structure(new external_value(PARAM_INT, '')),
            'welcome' => new external_value(PARAM_RAW, ''),
            'enabled' => new external_value(PARAM_BOOL, ''),
        ]);
    }
}
