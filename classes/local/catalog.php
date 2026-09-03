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
 * Function catalog.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * Sync and query the agent's function catalog.
 */
class catalog {

    /**
     * Path to the shipped metadata fixture.
     *
     * @return string
     */
    public static function fixture_path(): string {
        global $CFG;
        return $CFG->dirroot . '/admin/tool/adminagent/fixtures/functions.json';
    }

    /**
     * Load fixture records keyed by function name.
     *
     * @return array
     */
    public static function load_fixture(): array {
        $path = self::fixture_path();
        if (!is_readable($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            if (!empty($row['functionname'])) {
                $out[$row['functionname']] = $row;
            }
        }
        return $out;
    }

    /**
     * Sync Moodle external functions and bundled tools into the catalog table.
     */
    public static function sync(): void {
        global $DB;

        $fixture = self::load_fixture();
        $now = time();
        $seen = [];

        $wsfunctions = $DB->get_records('external_functions', null, '', 'name, classname, methodname, component');
        foreach ($wsfunctions as $ws) {
            $name = $ws->name;
            $seen[$name] = true;
            $meta = $fixture[$name] ?? [];
            $type = self::detect_ws_type($name, $meta);
            $record = $DB->get_record('tool_adminagent_function', ['functionname' => $name]);
            $relevance = (int) ($meta['relevancescore'] ?? 0);
            $description = $meta['description'] ?? self::ws_description($name);
            $capabilities = $meta['capabilities'] ?? '';
            $tags = self::encode_tags($meta['tags'] ?? []);
            $enabled = $record ? (int) $record->enabled : self::default_enabled($name, $type, $relevance, false);

            $data = (object) [
                'functionname' => $name,
                'bundled' => 0,
                'type' => $type,
                'enabled' => $enabled,
                'relevance' => $relevance,
                'description' => $description,
                'capabilities' => $capabilities,
                'tags' => $tags,
                'timemodified' => $now,
            ];
            if ($record) {
                $data->id = $record->id;
                // Keep admin enable/disable; refresh metadata.
                $data->enabled = $record->enabled;
                $DB->update_record('tool_adminagent_function', $data);
            } else {
                $DB->insert_record('tool_adminagent_function', $data);
            }
        }

        foreach ($fixture as $name => $meta) {
            if (empty($meta['bundled'])) {
                continue;
            }
            $seen[$name] = true;
            $type = $meta['type'] ?? 'Write';
            $record = $DB->get_record('tool_adminagent_function', ['functionname' => $name]);
            $data = (object) [
                'functionname' => $name,
                'bundled' => 1,
                'type' => $type,
                'enabled' => $record ? $record->enabled : (int) !empty($meta['enabled']),
                'relevance' => (int) ($meta['relevancescore'] ?? 0),
                'description' => $meta['description'] ?? '',
                'capabilities' => $meta['capabilities'] ?? '',
                'tags' => self::encode_tags($meta['tags'] ?? []),
                'timemodified' => $now,
            ];
            if ($record) {
                $data->id = $record->id;
                $data->enabled = $record->enabled;
                $DB->update_record('tool_adminagent_function', $data);
            } else {
                $DB->insert_record('tool_adminagent_function', $data);
            }
        }

        // Always register built-in discovery tool.
        if (empty($seen['search_tools'])) {
            $record = $DB->get_record('tool_adminagent_function', ['functionname' => 'search_tools']);
            $data = (object) [
                'functionname' => 'search_tools',
                'bundled' => 1,
                'type' => 'Read',
                'enabled' => 1,
                'relevance' => 10,
                'description' => 'Find functions that match a natural-language prompt. Returns recommended function names.',
                'capabilities' => '',
                'tags' => json_encode(['utility', 'lookup']),
                'timemodified' => $now,
            ];
            if ($record) {
                $data->id = $record->id;
                $DB->update_record('tool_adminagent_function', $data);
            } else {
                $DB->insert_record('tool_adminagent_function', $data);
            }
        }
    }

    /**
     * Default enable rules from the Functions tab callout.
     *
     * @param string $name
     * @param string $type
     * @param int $relevance
     * @param bool $bundled
     * @return int
     */
    public static function default_enabled(string $name, string $type, int $relevance, bool $bundled): int {
        if ($bundled) {
            return $relevance >= 5 ? 1 : 0;
        }
        $isajax = str_contains($type, 'AJAX');
        $isnonauth = str_contains($type, 'Non-auth');
        if ($isnonauth) {
            return 0;
        }
        $core = str_starts_with($name, 'core_') || str_starts_with($name, 'mod_')
            || str_starts_with($name, 'enrol_') || str_starts_with($name, 'gradereport_')
            || str_starts_with($name, 'qbank_') || str_starts_with($name, 'report_');
        if ($core) {
            return $relevance >= 5 ? 1 : 0;
        }
        // Add-on: non-AJAX on automatically.
        return $isajax ? 0 : 1;
    }

    /**
     * Detect read/write/ajax/non-auth from WS definition and fixture.
     *
     * @param string $name
     * @param array $meta
     * @return string
     */
    protected static function detect_ws_type(string $name, array $meta): string {
        if (!empty($meta['type'])) {
            return $meta['type'];
        }
        try {
            $info = \core_external\external_api::external_function_info($name);
            $parts = [];
            $parts[] = (!empty($info->type) && $info->type === 'write') ? 'Write' : 'Read';
            if (!empty($info->ajax)) {
                $parts[] = 'AJAX';
            }
            if (isset($info->loginrequired) && !$info->loginrequired) {
                $parts[] = 'Non-auth';
            }
            return implode(' ', $parts);
        } catch (\Throwable $e) {
            return 'Read';
        }
    }

    /**
     * Description from WS info when fixture is missing.
     *
     * @param string $name
     * @return string
     */
    protected static function ws_description(string $name): string {
        try {
            $info = \core_external\external_api::external_function_info($name);
            return (string) ($info->description ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Encode tags.
     *
     * @param array|string $tags
     * @return string
     */
    public static function encode_tags($tags): string {
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }
        return json_encode(array_values($tags));
    }

    /**
     * Decode tags.
     *
     * @param string|null $raw
     * @return array
     */
    public static function decode_tags(?string $raw): array {
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    /**
     * All catalog rows.
     *
     * @return array
     */
    public static function get_all(): array {
        global $DB;
        return array_values($DB->get_records('tool_adminagent_function', null, 'functionname ASC'));
    }

    /**
     * Enabled functions the current user may actually call.
     *
     * @param \stdClass|null $user
     * @return array
     */
    public static function get_available(?\stdClass $user = null): array {
        global $DB, $USER;
        $user = $user ?: $USER;
        $rows = $DB->get_records('tool_adminagent_function', ['enabled' => 1], 'functionname ASC');
        $out = [];
        foreach ($rows as $row) {
            if (self::user_can_use($row, $user)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * Whether the user has at least one listed capability (or none are required).
     *
     * @param \stdClass $row
     * @param \stdClass $user
     * @return bool
     */
    public static function user_can_use(\stdClass $row, \stdClass $user): bool {
        $caps = array_filter(array_map('trim', preg_split('/[,\s]+/', (string) $row->capabilities)));
        if (!$caps) {
            return true;
        }
        $context = \context_system::instance();
        foreach ($caps as $cap) {
            if (has_capability($cap, $context, $user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get one catalog row.
     *
     * @param string $name
     * @return \stdClass|null
     */
    public static function get(string $name): ?\stdClass {
        global $DB;
        $row = $DB->get_record('tool_adminagent_function', ['functionname' => $name]);
        return $row ?: null;
    }

    /**
     * Is this a write function?
     *
     * @param string $type
     * @return bool
     */
    public static function is_write(string $type): bool {
        return str_starts_with($type, 'Write');
    }

    /**
     * Does a function name match a glob or exact name?
     *
     * @param string $pattern
     * @param string $name
     * @return bool
     */
    public static function matches(string $pattern, string $name): bool {
        $pattern = trim($pattern);
        if ($pattern === $name) {
            return true;
        }
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        return (bool) preg_match($regex, $name);
    }

    /**
     * Expand a list of names/globs against the catalog.
     *
     * @param array $patterns
     * @return array
     */
    public static function expand_patterns(array $patterns): array {
        $all = self::get_all();
        $names = [];
        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }
            foreach ($all as $row) {
                if (self::matches($pattern, $row->functionname)) {
                    $names[$row->functionname] = $row;
                }
            }
        }
        return array_values($names);
    }

    /**
     * Toggle enabled.
     *
     * @param int $id
     * @param int $enabled
     */
    public static function set_enabled(int $id, int $enabled): void {
        global $DB;
        $DB->set_field('tool_adminagent_function', 'enabled', $enabled ? 1 : 0, ['id' => $id]);
        $DB->set_field('tool_adminagent_function', 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Build a JSON schema for a function (WS or bundled).
     *
     * @param string $name
     * @return array
     */
    public static function json_schema(string $name): array {
        $row = self::get($name);
        $description = $row->description ?? $name;
        if ($row && $row->bundled) {
            $class = bundled\registry::class_for($name);
            if ($class && method_exists($class, 'schema')) {
                $schema = $class === bundled\util::class ? $class::schema($name) : $class::schema();
                $schema['description'] = $schema['description'] ?? $description;
                return $schema;
            }
        }
        try {
            $info = \core_external\external_api::external_function_info($name);
            $properties = self::external_to_schema($info->parameters_desc ?? null);
            return [
                'type' => 'object',
                'description' => (string) ($info->description ?? $description),
                'properties' => $properties['properties'] ?? new \stdClass(),
                'required' => $properties['required'] ?? [],
            ];
        } catch (\Throwable $e) {
            return [
                'type' => 'object',
                'description' => $description,
                'properties' => new \stdClass(),
            ];
        }
    }

    /**
     * Convert a Moodle external_description to JSON schema.
     *
     * @param mixed $desc
     * @return array
     */
    public static function external_to_schema($desc): array {
        if ($desc === null) {
            return ['type' => 'object', 'properties' => new \stdClass()];
        }
        if ($desc instanceof \core_external\external_value) {
            $type = match ($desc->type) {
                PARAM_INT, PARAM_FLOAT => 'number',
                PARAM_BOOL => 'boolean',
                default => 'string',
            };
            $schema = ['type' => $type];
            if (!empty($desc->desc)) {
                $schema['description'] = (string) $desc->desc;
            }
            return $schema;
        }
        if ($desc instanceof \core_external\external_multiple_structure) {
            return [
                'type' => 'array',
                'items' => self::external_to_schema($desc->content),
                'description' => (string) ($desc->desc ?? ''),
            ];
        }
        if ($desc instanceof \core_external\external_single_structure) {
            $properties = [];
            $required = [];
            foreach ($desc->keys as $key => $sub) {
                $properties[$key] = self::external_to_schema($sub);
                $requiredflag = $sub->required ?? VALUE_REQUIRED;
                if ($requiredflag !== VALUE_OPTIONAL && $requiredflag !== VALUE_DEFAULT) {
                    $required[] = $key;
                }
            }
            return [
                'type' => 'object',
                'properties' => $properties ?: new \stdClass(),
                'required' => $required,
                'description' => (string) ($desc->desc ?? ''),
            ];
        }
        return ['type' => 'object'];
    }

    /**
     * Format a catalog row for the admin table / JSON.
     *
     * @param \stdClass $row
     * @return array
     */
    public static function export_row(\stdClass $row): array {
        $tags = self::decode_tags($row->tags);
        $type = $row->type;
        $badges = [];
        if (str_contains($type, 'Write')) {
            $badges[] = ['label' => get_string('typewrite', 'tool_adminagent'), 'class' => 'write'];
        }
        if (str_contains($type, 'Read')) {
            $badges[] = ['label' => get_string('typeread', 'tool_adminagent'), 'class' => 'read'];
        }
        if (str_contains($type, 'AJAX')) {
            $badges[] = ['label' => get_string('typeajax', 'tool_adminagent'), 'class' => 'ajax'];
        }
        if (str_contains($type, 'Non-auth')) {
            $badges[] = ['label' => get_string('typenonauth', 'tool_adminagent'), 'class' => 'nonauth'];
        }
        if (!empty($row->bundled)) {
            $badges[] = ['label' => get_string('typebundled', 'tool_adminagent'), 'class' => 'bundled'];
        }
        return [
            'id' => $row->id,
            'functionname' => $row->functionname,
            'type' => $type,
            'badges' => $badges,
            'enabled' => (bool) $row->enabled,
            'relevance' => (int) $row->relevance,
            'description' => $row->description,
            'capabilities' => $row->capabilities,
            'tags' => $tags,
            'tagslist' => implode(', ', $tags),
            'bundled' => (bool) $row->bundled,
        ];
    }
}
