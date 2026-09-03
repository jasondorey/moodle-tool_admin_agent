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
 * Placement store.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * CRUD and URL matching for placements.
 */
class placement {

    /**
     * List placements.
     *
     * @return array
     */
    public static function get_all(): array {
        global $DB;
        return array_values($DB->get_records('tool_adminagent_placement', null, 'name ASC'));
    }

    /**
     * Get by id.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        $row = $DB->get_record('tool_adminagent_placement', ['id' => $id]);
        return $row ?: null;
    }

    /**
     * Save a placement.
     *
     * @param array $data
     * @return \stdClass
     */
    public static function save(array $data): \stdClass {
        global $DB;
        $now = time();
        $location = $data['location'] ?? 'floating';
        $allowed = ['h1', 'h2', 'usermenu', 'floating'];
        if (!in_array($location, $allowed, true)) {
            $location = 'floating';
        }
        $skillids = $data['skillids'] ?? [];
        if (is_string($skillids)) {
            $skillids = array_filter(array_map('intval', preg_split('/[\s,]+/', $skillids)));
        }
        $record = (object) [
            'name' => clean_param($data['name'] ?? '', PARAM_TEXT),
            'urlmasks' => trim((string) ($data['urlmasks'] ?? '')),
            'location' => $location,
            'skillids' => json_encode(array_values(array_map('intval', (array) $skillids))),
            'welcome' => $data['welcome'] ?? '',
            'enabled' => empty($data['enabled']) ? 0 : 1,
            'timemodified' => $now,
        ];
        if ($record->name === '' || $record->urlmasks === '') {
            throw new \invalid_parameter_exception('name and urlmasks are required');
        }
        if (!empty($data['id'])) {
            $record->id = (int) $data['id'];
            $DB->update_record('tool_adminagent_placement', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record('tool_adminagent_placement', $record);
        }
        return self::get((int) $record->id);
    }

    /**
     * Delete.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records('tool_adminagent_placement', ['id' => $id]);
    }

    /**
     * Default site-wide floating placement.
     */
    public static function install_defaults(): void {
        global $DB;
        if ($DB->count_records('tool_adminagent_placement')) {
            return;
        }
        self::save([
            'name' => get_string('pluginname', 'tool_adminagent'),
            'urlmasks' => '/*',
            'location' => 'floating',
            'skillids' => [],
            'welcome' => '',
            'enabled' => 1,
        ]);
    }

    /**
     * Decode skill ids.
     *
     * @param \stdClass $placement
     * @return array
     */
    public static function skill_ids(\stdClass $placement): array {
        $ids = json_decode($placement->skillids ?? '[]', true);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /**
     * Does a relative URL match any mask?
     *
     * @param string $path Query string included, leading slash.
     * @param string $masks
     * @return bool
     */
    public static function url_matches(string $path, string $masks): bool {
        $path = '/' . ltrim($path, '/');
        foreach (preg_split('/\s+/', trim($masks)) as $mask) {
            if ($mask === '') {
                continue;
            }
            $regex = '/^' . str_replace('\*', '.*', preg_quote($mask, '/')) . '$/';
            if (preg_match($regex, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * First enabled placement that matches this request and the user can see.
     *
     * @param string $path
     * @param \stdClass|null $user
     * @return \stdClass|null
     */
    public static function match_for_user(string $path, ?\stdClass $user = null): ?\stdClass {
        foreach (self::get_all() as $placement) {
            if (!$placement->enabled) {
                continue;
            }
            if (!self::url_matches($path, $placement->urlmasks)) {
                continue;
            }
            $ids = self::skill_ids($placement);
            if ($ids) {
                $ok = false;
                foreach ($ids as $skillid) {
                    $skill = skill::get($skillid);
                    if ($skill && $skill->enabled && skill::user_can_access($skill, $user)) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    continue;
                }
            }
            return $placement;
        }
        return null;
    }

    /**
     * Set enabled.
     *
     * @param int $id
     * @param int $enabled
     */
    public static function set_enabled(int $id, int $enabled): void {
        global $DB;
        $DB->set_field('tool_adminagent_placement', 'enabled', $enabled ? 1 : 0, ['id' => $id]);
        $DB->set_field('tool_adminagent_placement', 'timemodified', time(), ['id' => $id]);
    }
}
