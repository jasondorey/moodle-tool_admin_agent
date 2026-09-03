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
 * Skill store.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * CRUD and import for skills.
 */
class skill {

    /**
     * List skills.
     *
     * @return array
     */
    public static function get_all(): array {
        global $DB;
        return array_values($DB->get_records('tool_adminagent_skill', null, 'name ASC'));
    }

    /**
     * Get by id.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        $row = $DB->get_record('tool_adminagent_skill', ['id' => $id]);
        return $row ?: null;
    }

    /**
     * Get by shortname.
     *
     * @param string $shortname
     * @return \stdClass|null
     */
    public static function get_by_shortname(string $shortname): ?\stdClass {
        global $DB;
        $row = $DB->get_record('tool_adminagent_skill', ['shortname' => $shortname]);
        return $row ?: null;
    }

    /**
     * Decode a JSON list field.
     *
     * @param string|null $raw
     * @return array
     */
    public static function decode_list(?string $raw): array {
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }
        return array_values(array_filter(array_map('trim', preg_split('/\s+/', $raw))));
    }

    /**
     * Encode a list of function names/globs.
     *
     * @param array|string $value
     * @return string
     */
    public static function encode_list($value): string {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value);
        }
        $value = array_values(array_filter(array_map('trim', (array) $value)));
        return json_encode($value);
    }

    /**
     * Save a skill from an array.
     *
     * @param array $data
     * @return \stdClass
     */
    public static function save(array $data): \stdClass {
        global $DB;
        $now = time();
        $record = (object) [
            'shortname' => clean_param($data['shortname'] ?? '', PARAM_ALPHANUMEXT),
            'name' => clean_param($data['name'] ?? '', PARAM_TEXT),
            'description' => $data['description'] ?? '',
            'instructions' => $data['instructions'] ?? '',
            'mainfunctions' => self::encode_list($data['mainfunctions'] ?? []),
            'otherfunctions' => self::encode_list($data['otherfunctions'] ?? []),
            'enabled' => empty($data['enabled']) ? 0 : 1,
            'timemodified' => $now,
        ];
        if (empty($record->shortname) || empty($record->name) || $record->instructions === '') {
            throw new \invalid_parameter_exception('shortname, name and instructions are required');
        }
        $existing = null;
        if (!empty($data['id'])) {
            $existing = $DB->get_record('tool_adminagent_skill', ['id' => (int) $data['id']], '*', MUST_EXIST);
        } else {
            $existing = $DB->get_record('tool_adminagent_skill', ['shortname' => $record->shortname]);
        }
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('tool_adminagent_skill', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record('tool_adminagent_skill', $record);
        }
        return self::get((int) $record->id);
    }

    /**
     * Delete a skill.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records('tool_adminagent_skill', ['id' => $id]);
    }

    /**
     * Import from a skill JSON object.
     *
     * @param array $json
     * @return \stdClass
     */
    public static function import(array $json): \stdClass {
        return self::save([
            'shortname' => $json['shortname'] ?? '',
            'name' => $json['name'] ?? '',
            'description' => $json['description'] ?? '',
            'instructions' => $json['instructions'] ?? '',
            'mainfunctions' => $json['mainfunctions'] ?? [],
            'otherfunctions' => $json['otherfunctions'] ?? [],
            'enabled' => $json['enabled'] ?? true,
        ]);
    }

    /**
     * Export a skill as JSON (same schema as the prototype).
     *
     * @param \stdClass $skill
     * @return array
     */
    public static function export(\stdClass $skill): array {
        return [
            'formatversion' => 2026090200,
            'shortname' => $skill->shortname,
            'name' => $skill->name,
            'description' => $skill->description,
            'instructions' => $skill->instructions,
            'mainfunctions' => self::decode_list($skill->mainfunctions),
            'otherfunctions' => self::decode_list($skill->otherfunctions),
            'enabled' => (bool) $skill->enabled,
        ];
    }

    /**
     * Seed default skills from fixtures.
     */
    public static function install_defaults(): void {
        global $CFG;
        $dir = $CFG->dirroot . '/admin/tool/adminagent/fixtures/skills';
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.json') as $file) {
            $json = json_decode(file_get_contents($file), true);
            if (is_array($json) && !empty($json['shortname'])) {
                self::import($json);
            }
        }
    }

    /**
     * Whether the user can access this skill (at least one main function).
     *
     * @param \stdClass $skill
     * @param \stdClass|null $user
     * @return bool
     */
    public static function user_can_access(\stdClass $skill, ?\stdClass $user = null): bool {
        global $USER;
        $user = $user ?: $USER;
        $mains = self::decode_list($skill->mainfunctions);
        if (!$mains) {
            return true;
        }
        $rows = catalog::expand_patterns($mains);
        foreach ($rows as $row) {
            if ($row->enabled && catalog::user_can_use($row, $user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Skills visible to the current user.
     *
     * @param \stdClass|null $user
     * @return array
     */
    public static function get_available(?\stdClass $user = null): array {
        $out = [];
        foreach (self::get_all() as $skill) {
            if (!$skill->enabled) {
                continue;
            }
            if (self::user_can_access($skill, $user)) {
                $out[] = $skill;
            }
        }
        return $out;
    }

    /**
     * Set enabled.
     *
     * @param int $id
     * @param int $enabled
     */
    public static function set_enabled(int $id, int $enabled): void {
        global $DB;
        $DB->set_field('tool_adminagent_skill', 'enabled', $enabled ? 1 : 0, ['id' => $id]);
        $DB->set_field('tool_adminagent_skill', 'timemodified', time(), ['id' => $id]);
    }
}
