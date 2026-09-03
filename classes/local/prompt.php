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
 * System prompt builder.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * Build system instructions for the model.
 */
class prompt {

    /**
     * Full system prompt for a conversation.
     *
     * @param array $skills
     * @return string
     */
    public static function build(array $skills = []): string {
        global $CFG, $USER;
        $custom = trim((string) get_config('tool_adminagent', 'systeminstruction'));
        $base = $custom !== '' ? $custom : get_string('defaultsysteminstruction', 'tool_adminagent');
        $replacements = [
            '{siteurl}' => rtrim($CFG->wwwroot, '/'),
            '{userid}' => (string) $USER->id,
            '{date}' => date('Y-m-d'),
            '{sitetimezone}' => \core_date::get_server_timezone(),
            '{sitelang}' => current_language(),
        ];
        $base = strtr($base, $replacements);

        $appended = "\n\nAlways appended to the system instructions:\n"
            . "The Moodle site URL is: {$replacements['{siteurl}']}\n"
            . "The current user ID is: {$replacements['{userid}']}\n"
            . "Today's date is: {$replacements['{date}']}\n"
            . "The site default timezone is: {$replacements['{sitetimezone}']}\n"
            . "The site default language is: {$replacements['{sitelang}']}\n";
        if (discovery::enabled(catalog::get_available())) {
            $appended .= "\n" . get_string('discoveryprompt', 'tool_adminagent') . "\n";
        }

        $skilltext = '';
        foreach ($skills as $skill) {
            $skilltext .= "\n\n# Skill: {$skill->name}\n{$skill->instructions}\n";
        }

        $cataloghint = '';
        $available = skill::get_available();
        if ($available) {
            $cataloghint = "\n\nAvailable skills (load by following their instructions when they fit the request):\n";
            foreach ($available as $s) {
                $cataloghint .= "- {$s->shortname}: {$s->name} — {$s->description}\n";
            }
        }

        return $base . $appended . $cataloghint . $skilltext;
    }
}
