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
 * Small bundled utilities.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

use tool_adminagent\local\catalog;
use tool_adminagent\local\discovery;
use tool_adminagent\local\knowledgebase;

/**
 * Utility bundled functions.
 */
class util {

    /**
     * Whether this class handles the name.
     *
     * @param string $name
     * @return bool
     */
    public static function class_has(string $name): bool {
        return in_array($name, ['search_tools', 'search_knowledgebase', 'get_unix_timestamp', 'sleep', 'fetch_current_page'], true);
    }

    /**
     * JSON schema.
     *
     * @param string $name unused by registry path but kept for callers
     * @return array
     */
    public static function schema(string $name = ''): array {
        return match ($name) {
            'search_tools' => [
                'type' => 'object',
                'properties' => [
                    'prompt' => [
                        'type' => 'string',
                        'description' => 'What the user wants to do',
                    ],
                ],
                'required' => ['prompt'],
            ],
            'search_knowledgebase' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query — use specific Moodle terms (e.g. "backup automated schedule", "enrolment methods", "gradebook categories")',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max results to return (default 5, max 10)',
                    ],
                ],
                'required' => ['query'],
            ],
            'get_unix_timestamp' => [
                'type' => 'object',
                'properties' => [
                    'iso8601' => [
                        'type' => 'string',
                        'description' => 'ISO 8601 date/time, or omit for now',
                    ],
                ],
            ],
            default => [
                'type' => 'object',
                'properties' => new \stdClass(),
            ],
        };
    }

    /**
     * Execute.
     *
     * @param string $name
     * @param array $args
     * @return array
     */
    public static function execute(string $name, array $args): array {
        return match ($name) {
            'get_unix_timestamp' => self::get_unix_timestamp($args),
            'sleep' => self::sleep_seconds($args),
            'fetch_current_page' => self::fetch_current_page($args),
            'search_tools' => [
                'tools' => discovery::search((string) ($args['prompt'] ?? ''), catalog::get_available()),
            ],
            'search_knowledgebase' => self::search_knowledgebase($args),
            default => throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', $name),
        };
    }

    /**
     * Convert an ISO date (or now) to a unix timestamp.
     *
     * @param array $args
     * @return array
     */
    protected static function get_unix_timestamp(array $args): array {
        $iso = $args['iso8601'] ?? $args['date'] ?? 'now';
        $tz = \core_date::get_user_timezone_object();
        try {
            $dt = new \DateTimeImmutable((string) $iso, $tz);
        } catch (\Exception $e) {
            throw new \invalid_parameter_exception('Invalid date: ' . $iso);
        }
        return [
            'timestamp' => $dt->getTimestamp(),
            'iso8601' => $dt->format('c'),
            'timezone' => $tz->getName(),
        ];
    }

    /**
     * Sleep briefly (capped).
     *
     * @param array $args
     * @return array
     */
    protected static function sleep_seconds(array $args): array {
        $seconds = min(5, max(0, (int) ($args['seconds'] ?? 1)));
        sleep($seconds);
        return ['slept' => $seconds];
    }

    /**
     * Current page URL context.
     *
     * @param array $args
     * @return array
     */
    /**
     * Search the knowledgebase for Moodle documentation.
     *
     * @param array $args
     * @return array
     */
    protected static function search_knowledgebase(array $args): array {
        $query = (string) ($args['query'] ?? '');
        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        if ($query === '') {
            return ['results' => [], 'stats' => knowledgebase::stats()];
        }
        $results = knowledgebase::search($query, $limit);
        return [
            'results' => $results,
            'result_count' => count($results),
            'stats' => knowledgebase::stats(),
        ];
    }

    protected static function fetch_current_page(array $args): array {
        global $PAGE, $ME, $FULLME;
        return [
            'url' => $args['url'] ?? ($FULLME ?? $ME ?? ''),
            'title' => $PAGE->title ?? '',
            'pagetype' => $PAGE->pagetype ?? '',
            'contextid' => $PAGE->context->id ?? 0,
        ];
    }
}
