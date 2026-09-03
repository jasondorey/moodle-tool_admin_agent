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
 * Output hooks.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * Inject the chat widget.
 */
class hook_callbacks {

    /**
     * Add widget HTML before footer.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $USER, $ME;

        if (!isloggedin() || isguestuser()) {
            return;
        }
        $context = \context_system::instance();
        if (!has_capability('tool/adminagent:use', $context)) {
            return;
        }
        $viewtypes = \tool_adminagent\external\helper::viewtypes();
        if (empty($viewtypes['overlay']) && empty($viewtypes['sidebar']) && empty($viewtypes['fullscreen'])) {
            return;
        }
        // Fullscreen chat page renders its own chrome.
        if ($PAGE->pagetype === 'admin-tool-adminagent-chat') {
            return;
        }

        $path = $ME ?? '/';
        $placement = placement::match_for_user($path, $USER);
        if (!$placement) {
            return;
        }

        $renderer = $PAGE->get_renderer('tool_adminagent');
        $hook->add_html($renderer->widget_bootstrap($placement));
    }
}
