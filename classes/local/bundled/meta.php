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
 * Skill and placement authoring tools.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

use tool_adminagent\local\catalog;
use tool_adminagent\local\placement;
use tool_adminagent\local\skill;

/**
 * tool_adminagent_* bundled functions.
 */
class meta {

    /**
     * Schema fallback.
     *
     * @return array
     */
    public static function schema(): array {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    /**
     * Execute a meta function.
     *
     * @param string $name
     * @param array $args
     * @return array
     */
    public static function execute(string $name, array $args): array {
        require_capability('tool/adminagent:manage', \context_system::instance());
        return match ($name) {
            'tool_adminagent_list_functions' => self::list_functions($args),
            'tool_adminagent_search_skills' => self::search_skills($args),
            'tool_adminagent_search_placements' => self::search_placements($args),
            'tool_adminagent_create_skill' => self::export_skill(skill::save($args)),
            'tool_adminagent_update_skill' => self::export_skill(skill::save($args)),
            'tool_adminagent_skill_pre_update' => self::export_skill(skill::get((int) ($args['id'] ?? 0))),
            'tool_adminagent_delete_skill' => self::delete_skill($args),
            'tool_adminagent_create_placement' => self::export_placement(placement::save($args)),
            'tool_adminagent_update_placement' => self::export_placement(placement::save($args)),
            'tool_adminagent_placement_pre_update' => self::export_placement(placement::get((int) ($args['id'] ?? 0))),
            'tool_adminagent_delete_placement' => self::delete_placement($args),
            default => throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', $name),
        };
    }

    /**
     * List/search catalog functions for skill authoring.
     *
     * @param array $args
     * @return array
     */
    protected static function list_functions(array $args): array {
        $query = strtolower((string) ($args['query'] ?? $args['prompt'] ?? ''));
        $out = [];
        foreach (catalog::get_all() as $row) {
            if ($query !== '') {
                $hay = strtolower($row->functionname . ' ' . $row->description . ' ' . $row->tags);
                if (!str_contains($hay, $query)) {
                    continue;
                }
            }
            $out[] = catalog::export_row($row);
            if (count($out) >= 50) {
                break;
            }
        }
        return ['functions' => $out];
    }

    /**
     * Search skills.
     *
     * @param array $args
     * @return array
     */
    protected static function search_skills(array $args): array {
        $query = strtolower((string) ($args['query'] ?? ''));
        $out = [];
        foreach (skill::get_all() as $s) {
            if ($query === '' || str_contains(strtolower($s->name . $s->shortname . $s->description), $query)) {
                $out[] = skill::export($s);
            }
        }
        return ['skills' => $out];
    }

    /**
     * Search placements.
     *
     * @param array $args
     * @return array
     */
    protected static function search_placements(array $args): array {
        $query = strtolower((string) ($args['query'] ?? ''));
        $out = [];
        foreach (placement::get_all() as $p) {
            if ($query === '' || str_contains(strtolower($p->name . $p->urlmasks), $query)) {
                $out[] = self::export_placement($p);
            }
        }
        return ['placements' => $out];
    }

    /**
     * Export skill with capability warning.
     *
     * @param \stdClass|null $skill
     * @return array
     */
    protected static function export_skill(?\stdClass $skill): array {
        if (!$skill) {
            throw new \invalid_parameter_exception('Skill not found');
        }
        $data = skill::export($skill);
        $mains = catalog::expand_patterns($data['mainfunctions']);
        $caps = [];
        foreach ($mains as $row) {
            if ($row->capabilities) {
                $caps[] = $row->capabilities;
            }
        }
        $data['capabilities'] = array_values(array_unique($caps));
        $data['id'] = $skill->id;
        return $data;
    }

    /**
     * Export placement.
     *
     * @param \stdClass|null $placement
     * @return array
     */
    protected static function export_placement(?\stdClass $placement): array {
        if (!$placement) {
            throw new \invalid_parameter_exception('Placement not found');
        }
        return [
            'id' => $placement->id,
            'name' => $placement->name,
            'urlmasks' => $placement->urlmasks,
            'location' => $placement->location,
            'skillids' => placement::skill_ids($placement),
            'welcome' => $placement->welcome,
            'enabled' => (bool) $placement->enabled,
        ];
    }

    /**
     * Delete skill.
     *
     * @param array $args
     * @return array
     */
    protected static function delete_skill(array $args): array {
        skill::delete((int) ($args['id'] ?? 0));
        return ['deleted' => true];
    }

    /**
     * Delete placement.
     *
     * @param array $args
     * @return array
     */
    protected static function delete_placement(array $args): array {
        placement::delete((int) ($args['id'] ?? 0));
        return ['deleted' => true];
    }
}
