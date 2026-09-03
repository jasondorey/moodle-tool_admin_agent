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
 * Widget bootstrap WS.
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
use tool_adminagent\local\catalog;
use tool_adminagent\local\conversation;
use tool_adminagent\local\discovery;
use tool_adminagent\local\placement;

/**
 * Return widget config.
 */
class get_widget_config extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'path' => new external_value(PARAM_RAW, 'Current relative URL', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $path
     * @return array
     */
    public static function execute(string $path = ''): array {
        global $USER, $CFG;
        $params = self::validate_parameters(self::execute_parameters(), ['path' => $path]);
        $context = helper::require_use();
        self::validate_context($context);
        $placement = placement::match_for_user($params['path'] ?: '/', $USER);
        $latest = conversation::latest($USER->id);
        $types = helper::viewtypes();
        return [
            'firstname' => $USER->firstname,
            'userid' => (int) $USER->id,
            'placementid' => $placement->id ?? 0,
            'location' => $placement->location ?? 'floating',
            'welcome' => $placement->welcome ?? '',
            'allowfeedback' => (bool) get_config('tool_adminagent', 'allowfeedback'),
            'maxfilesize' => (int) get_config('tool_adminagent', 'maxfilesize'),
            'wwwroot' => $CFG->wwwroot,
            'fullscreenurl' => (new \moodle_url('/admin/tool/adminagent/chat.php'))->out(false),
            'hasrecent' => $latest !== null,
            'recent' => $latest ? [
                'id' => $latest->id,
                'title' => $latest->title,
                'timemodified' => $latest->timemodified,
            ] : ['id' => 0, 'title' => '', 'timemodified' => 0],
            'viewoverlay' => !empty($types['overlay']),
            'viewsidebar' => !empty($types['sidebar']),
            'viewfullscreen' => !empty($types['fullscreen']),
            'dynamicdiscovery' => discovery::enabled(catalog::get_available()),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'firstname' => new external_value(PARAM_TEXT, ''),
            'userid' => new external_value(PARAM_INT, ''),
            'placementid' => new external_value(PARAM_INT, ''),
            'location' => new external_value(PARAM_ALPHANUMEXT, ''),
            'welcome' => new external_value(PARAM_RAW, ''),
            'allowfeedback' => new external_value(PARAM_BOOL, ''),
            'maxfilesize' => new external_value(PARAM_INT, ''),
            'wwwroot' => new external_value(PARAM_URL, ''),
            'fullscreenurl' => new external_value(PARAM_URL, ''),
            'hasrecent' => new external_value(PARAM_BOOL, ''),
            'recent' => new external_single_structure([
                'id' => new external_value(PARAM_INT, ''),
                'title' => new external_value(PARAM_TEXT, ''),
                'timemodified' => new external_value(PARAM_INT, ''),
            ]),
            'viewoverlay' => new external_value(PARAM_BOOL, ''),
            'viewsidebar' => new external_value(PARAM_BOOL, ''),
            'viewfullscreen' => new external_value(PARAM_BOOL, ''),
            'dynamicdiscovery' => new external_value(PARAM_BOOL, ''),
        ]);
    }
}
