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
 * Execute catalog functions.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * Run a Moodle WS or bundled function as the current user.
 */
class executor {

    /**
     * Execute a function and return a JSON-serialisable result.
     *
     * @param string $name
     * @param array $args
     * @return array
     */
    public static function run(string $name, array $args): array {
        $row = catalog::get($name);
        if (!$row || !$row->enabled) {
            throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', 'Function is not enabled: ' . $name);
        }
        if (!catalog::user_can_use($row, $GLOBALS['USER'])) {
            throw new \required_capability_exception(\context_system::instance(),
                $row->capabilities ?: 'tool/adminagent:use', 'nopermissions', '');
        }

        if ($name === 'search_tools') {
            $available = catalog::get_available();
            $prompt = (string) ($args['prompt'] ?? '');
            return ['tools' => discovery::search($prompt, $available)];
        }

        if ($row->bundled) {
            $result = bundled\registry::execute($name, $args);
        } else {
            $result = self::run_ws($name, $args);
        }

        return self::truncate($result);
    }

    /**
     * Call a Moodle external function as the current user.
     *
     * Invokes the implementation directly so we do not nest a second
     * webservice login/sesskey check inside our own AJAX call.
     *
     * @param string $name
     * @param array $args
     * @return array
     */
    public static function run_ws(string $name, array $args): array {
        $info = \core_external\external_api::external_function_info($name);
        $args = self::normalise_args($info, $args);
        $classname = $info->classname;
        $method = $info->methodname;
        if (!class_exists($classname) || !method_exists($classname, $method)) {
            throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', 'Function is not callable: ' . $name);
        }
        $params = $classname::validate_parameters($info->parameters_desc, $args);
        $result = call_user_func_array([$classname, $method], array_values($params));
        if ($info->returns_desc !== null) {
            $result = $classname::clean_returnvalue($info->returns_desc, $result);
        }
        return is_array($result) ? $result : ['result' => $result];
    }

    /**
     * Wrap a single-key WS payload when the model omitted the wrapper.
     *
     * @param object $info
     * @param array $args
     * @return array
     */
    protected static function normalise_args(object $info, array $args): array {
        $desc = $info->parameters_desc ?? null;
        if (!$desc instanceof \core_external\external_single_structure) {
            return $args;
        }
        $keys = array_keys($desc->keys);
        if (count($keys) !== 1) {
            return $args;
        }
        $only = $keys[0];
        if (array_key_exists($only, $args)) {
            return $args;
        }
        $sub = $desc->keys[$only];
        if ($sub instanceof \core_external\external_multiple_structure) {
            return [$only => [$args]];
        }
        if ($sub instanceof \core_external\external_single_structure) {
            return [$only => $args];
        }
        return $args;
    }

    /**
     * Truncate oversized results.
     *
     * @param array $result
     * @return array
     */
    public static function truncate(array $result): array {
        $limitkb = (int) get_config('tool_adminagent', 'maxfunctionresultsize');
        if ($limitkb <= 0) {
            return $result;
        }
        $json = json_encode($result);
        if (strlen($json) <= $limitkb * 1024) {
            return $result;
        }
        return [
            'truncated' => true,
            'message' => get_string('functiontruncated', 'tool_adminagent'),
            'bytes' => strlen($json),
        ];
    }
}
