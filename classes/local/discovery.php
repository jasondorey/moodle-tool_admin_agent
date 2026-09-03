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
 * Dynamic function discovery.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * Rank catalog functions against a natural-language prompt.
 */
class discovery {

    /**
     * Whether dynamic discovery should be used for this user.
     *
     * @param array $available
     * @return bool
     */
    public static function enabled(array $available): bool {
        $mode = get_config('tool_adminagent', 'dynamicdiscovery') ?: 'automatic';
        if ($mode === 'on') {
            return true;
        }
        if ($mode === 'off') {
            return false;
        }
        return count($available) > 15;
    }

    /**
     * Rank functions and return name/score rows (no fallback).
     *
     * @param string $prompt
     * @param array $available
     * @param int $limit
     * @return array
     */
    public static function ranked(string $prompt, array $available, int $limit = 12): array {
        $tokens = self::tokens($prompt);
        $scored = [];
        foreach ($available as $row) {
            if ($row->functionname === 'search_tools') {
                continue;
            }
            $hay = strtolower($row->functionname . ' ' . $row->description . ' ' . $row->tags);
            $score = 0;
            foreach ($tokens as $token) {
                if ($token === '') {
                    continue;
                }
                if (str_contains(strtolower($row->functionname), $token)) {
                    $score += 8;
                }
                if (str_contains($hay, $token)) {
                    $score += 3;
                }
            }
            $score += (int) $row->relevance;
            if ($score > 0) {
                $scored[] = ['name' => $row->functionname, 'score' => $score];
            }
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    /**
     * Rank functions and return the top names.
     *
     * @param string $prompt
     * @param array $available Catalog rows.
     * @param int $limit
     * @return array
     */
    public static function search(string $prompt, array $available, int $limit = 12): array {
        $names = array_column(self::ranked($prompt, $available, $limit), 'name');
        if (!$names) {
            // Fall back to highest relevance writes/reads.
            usort($available, fn($a, $b) => $b->relevance <=> $a->relevance);
            foreach ($available as $row) {
                if ($row->functionname === 'search_tools') {
                    continue;
                }
                $names[] = $row->functionname;
                if (count($names) >= $limit) {
                    break;
                }
            }
        }
        return $names;
    }

    /**
     * Whether this prompt is a new kind of work versus the current tool set.
     *
     * @param string $prompt
     * @param array $currentnames
     * @param array $available
     * @return bool
     */
    public static function is_new_task(string $prompt, array $currentnames, array $available): bool {
        $tokens = self::tokens($prompt);
        if (count($tokens) < 2) {
            return false;
        }
        $markers = [
            'can', 'could', 'create', 'delete', 'update', 'enrol', 'enroll',
            'duplicate', 'assign', 'configure', 'add', 'remove', 'backup',
            'restore', 'upload',
        ];
        if (!array_intersect($tokens, $markers)) {
            return false;
        }
        $new = array_column(self::ranked($prompt, $available, 12), 'name');
        if (!$new) {
            return false;
        }
        if (!$currentnames) {
            return true;
        }
        // Same topic if the best match is already loaded (e.g. "create another cohort").
        return !in_array($new[0], $currentnames, true);
    }

    /**
     * Tokenise a prompt.
     *
     * @param string $prompt
     * @return array
     */
    public static function tokens(string $prompt): array {
        $prompt = strtolower($prompt);
        $parts = preg_split('/[^a-z0-9_]+/', $prompt);
        $stop = ['the', 'a', 'an', 'in', 'on', 'of', 'to', 'for', 'and', 'or', 'me', 'my', 'please', 'with', 'this'];
        return array_values(array_filter($parts, fn($t) => $t !== '' && strlen($t) > 1 && !in_array($t, $stop, true)));
    }
}
