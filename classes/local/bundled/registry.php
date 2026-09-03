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
 * Bundled function registry.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

/**
 * Map function names to bundled implementations.
 */
class registry {

    /**
     * Execute a bundled function.
     *
     * @param string $name
     * @param array $args
     * @return array
     */
    public static function execute(string $name, array $args): array {
        $class = self::class_for($name);
        if (!$class) {
            throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', 'Unknown bundled function: ' . $name);
        }
        return $class::execute($name, $args);
    }

    /**
     * Implementing class.
     *
     * @param string $name
     * @return string|null
     */
    public static function class_for(string $name): ?string {
        $map = self::map();
        if (isset($map[$name])) {
            return $map[$name];
        }
        if (preg_match('/^x_mod_([a-z0-9]+)_create_modules$/', $name)) {
            return module_ops::class;
        }
        if (preg_match('/^x_mod_([a-z0-9]+)_update_module$/', $name)) {
            return module_ops::class;
        }
        if (preg_match('/^x_mod_([a-z0-9]+)_pre_update$/', $name)) {
            return module_ops::class;
        }
        if (str_starts_with($name, 'tool_adminagent_')) {
            return meta::class;
        }
        if (str_starts_with($name, 'x_mod_lesson_')) {
            return lesson_ops::class;
        }
        if (str_starts_with($name, 'x_mod_data_')) {
            return data_ops::class;
        }
        if (str_starts_with($name, 'x_mod_feedback_')) {
            return feedback_ops::class;
        }
        if (str_starts_with($name, 'x_mod_workshop_')) {
            return workshop_ops::class;
        }
        if (files::class_has($name)) {
            return files::class;
        }
        if (util::class_has($name)) {
            return util::class;
        }
        if (extras::class_has($name)) {
            return extras::class;
        }
        return null;
    }

    /**
     * Explicit name map.
     *
     * @return array
     */
    protected static function map(): array {
        return [
            'search_tools' => util::class,
            'search_knowledgebase' => util::class,
            'get_unix_timestamp' => util::class,
            'sleep' => util::class,
            'fetch_current_page' => util::class,
            'generate_image' => files::class,
            'convert_file_to_image' => files::class,
            'crop_image' => files::class,
            'resize_image' => files::class,
            'read_file' => files::class,
            'extract_files' => files::class,
            'create_files' => files::class,
            'create_draft_file_area' => files::class,
        ];
    }
}
