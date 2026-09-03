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
 * Shared external helpers.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\external;

/**
 * Capability helpers.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Require chat use.
     *
     * @return \context
     */
    public static function require_use(): \context {
        $context = \context_system::instance();
        require_capability('tool/adminagent:use', $context);
        return $context;
    }

    /**
     * Require manage.
     *
     * @return \context
     */
    public static function require_manage(): \context {
        $context = \context_system::instance();
        require_capability('tool/adminagent:manage', $context);
        return $context;
    }

    /**
     * Enabled chat layouts.
     *
     * @return array
     */
    public static function viewtypes(): array {
        $raw = get_config('tool_adminagent', 'viewtypes');
        $defaults = ['overlay' => true, 'sidebar' => true, 'fullscreen' => true];
        if ($raw === false || $raw === null || $raw === '') {
            return $defaults;
        }
        if (is_string($raw) && str_starts_with($raw, 'a:')) {
            $decoded = @unserialize($raw);
            if (is_array($decoded)) {
                return [
                    'overlay' => !empty($decoded['overlay']),
                    'sidebar' => !empty($decoded['sidebar']),
                    'fullscreen' => !empty($decoded['fullscreen']),
                ];
            }
        }
        $types = ['overlay' => false, 'sidebar' => false, 'fullscreen' => false];
        foreach (preg_split('/[,]+/', (string) $raw) as $bit) {
            $bit = trim($bit);
            if (isset($types[$bit])) {
                $types[$bit] = true;
            }
        }
        return $types;
    }

    /**
     * Conversation export structure.
     *
     * @return \core_external\external_single_structure
     */
    public static function conversation_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'id' => new \core_external\external_value(PARAM_INT, ''),
            'title' => new \core_external\external_value(PARAM_TEXT, ''),
            'layout' => new \core_external\external_value(PARAM_ALPHANUMEXT, ''),
            'autoapprove' => new \core_external\external_value(PARAM_ALPHANUMEXT, ''),
            'timemodified' => new \core_external\external_value(PARAM_INT, ''),
            'status' => new \core_external\external_value(PARAM_ALPHANUMEXT, '', VALUE_DEFAULT, ''),
            'messages' => new \core_external\external_multiple_structure(new \core_external\external_single_structure([
                'id' => new \core_external\external_value(PARAM_INT, ''),
                'role' => new \core_external\external_value(PARAM_ALPHANUM, ''),
                'content' => new \core_external\external_value(PARAM_RAW, ''),
                'timecreated' => new \core_external\external_value(PARAM_INT, ''),
                'feedback' => new \core_external\external_value(PARAM_ALPHANUMEXT, '', VALUE_DEFAULT, ''),
            ])),
            'toolcalls' => new \core_external\external_multiple_structure(new \core_external\external_single_structure([
                'id' => new \core_external\external_value(PARAM_INT, ''),
                'messageid' => new \core_external\external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
                'callid' => new \core_external\external_value(PARAM_RAW, ''),
                'functionname' => new \core_external\external_value(PARAM_RAW, ''),
                'arguments' => new \core_external\external_value(PARAM_RAW, ''),
                'result' => new \core_external\external_value(PARAM_RAW, '', VALUE_DEFAULT, ''),
                'status' => new \core_external\external_value(PARAM_ALPHANUMEXT, ''),
                'readwrite' => new \core_external\external_value(PARAM_ALPHA, ''),
                'pending' => new \core_external\external_value(PARAM_BOOL, ''),
                'done' => new \core_external\external_value(PARAM_BOOL, ''),
                'running' => new \core_external\external_value(PARAM_BOOL, ''),
            ])),
        ]);
    }
}
