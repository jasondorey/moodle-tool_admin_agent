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
 * Auto-approve policy.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * Decide whether a function call needs user approval.
 */
class approval {

    public const NONE = 'none';
    public const READONLY = 'readonly';
    public const SAFE = 'safe';
    public const ALL = 'all';

    /**
     * Allowed modes.
     *
     * @return array
     */
    public static function modes(): array {
        return [self::NONE, self::READONLY, self::SAFE, self::ALL];
    }

    /**
     * Normalise a stored mode.
     *
     * @param string $mode
     * @return string
     */
    public static function normalise(string $mode): string {
        $mode = strtolower($mode);
        return in_array($mode, self::modes(), true) ? $mode : self::NONE;
    }

    /**
     * Whether this call can run without prompting.
     *
     * @param string $mode
     * @param \stdClass $function Catalog row.
     * @return bool
     */
    public static function auto_allows(string $mode, \stdClass $function): bool {
        $mode = self::normalise($mode);
        // Discovery only returns function names; it never changes the site.
        if (($function->functionname ?? '') === 'search_tools') {
            return true;
        }
        if ($mode === self::ALL) {
            return true;
        }
        if ($mode === self::NONE) {
            return false;
        }
        $write = catalog::is_write($function->type);
        if ($mode === self::READONLY) {
            return !$write;
        }
        // Safe: auto-approve anything that does not modify the site.
        if (!$write) {
            return true;
        }
        $tags = catalog::decode_tags($function->tags);
        $destructive = array_intersect($tags, ['create', 'update', 'delete', 'destructive', 'bulk', 'toggle']);
        return empty($destructive);
    }
}
