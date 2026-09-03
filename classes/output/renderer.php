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
 * Plugin renderer.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\output;

use tool_adminagent\local\catalog;
use tool_adminagent\local\placement;
use tool_adminagent\local\skill;

/**
 * Render admin pages and the chat widget.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Configuration page.
     *
     * @param string $tab
     * @return string
     */
    public function config_page(string $tab): string {
        $tabs = ['skills', 'placements', 'functions'];
        if (!in_array($tab, $tabs, true)) {
            $tab = 'skills';
        }
        $data = [
            'configurl' => (new \moodle_url('/admin/tool/adminagent/index.php'))->out(false),
            'settingsurl' => (new \moodle_url('/admin/settings.php', ['section' => 'tool_adminagent']))->out(false),
            'tabskills' => $tab === 'skills',
            'tabplacements' => $tab === 'placements',
            'tabfunctions' => $tab === 'functions',
            'skills' => array_map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'shortname' => $s->shortname,
                'description' => $s->description,
                'instructions' => $s->instructions,
                'mainfunctions' => implode("\n", skill::decode_list($s->mainfunctions)),
                'otherfunctions' => implode("\n", skill::decode_list($s->otherfunctions)),
                'enabled' => (bool) $s->enabled,
            ], skill::get_all()),
            'placements' => array_map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'urlmasks' => $p->urlmasks,
                'location' => $p->location,
                'locationlabel' => get_string('location' . $p->location, 'tool_adminagent'),
                'floating' => $p->location === 'floating',
                'initialskills' => $this->placement_skill_names($p),
                'skillids' => implode(',', placement::skill_ids($p)),
                'welcome' => $p->welcome,
                'enabled' => (bool) $p->enabled,
            ], placement::get_all()),
            'functions' => array_map(fn($f) => catalog::export_row($f), catalog::get_all()),
            'skilloptions' => array_map(fn($s) => ['id' => $s->id, 'name' => $s->name], skill::get_all()),
            'skilloptionsjson' => json_encode(array_map(fn($s) => ['id' => (int) $s->id, 'name' => $s->name], skill::get_all())),
        ];
        $this->page->requires->js_call_amd('tool_adminagent/config', 'init');
        return $this->render_from_template('tool_adminagent/config', $data);
    }

    /**
     * Skill names for a placement.
     *
     * @param \stdClass $placement
     * @return string
     */
    protected function placement_skill_names(\stdClass $placement): string {
        $names = [];
        foreach (placement::skill_ids($placement) as $id) {
            $skill = skill::get($id);
            if ($skill) {
                $names[] = $skill->name;
            }
        }
        return $names ? implode(', ', $names) : '—';
    }

    /**
     * Context for the React chat mount.
     *
     * @param string $path
     * @param int $placementid
     * @param string $location
     * @param string $startlayout
     * @return array
     */
    public function chat_mount_context(string $path, int $placementid, string $location, string $startlayout = ''): array {
        global $USER, $CFG;
        return [
            'location' => $location,
            'floating' => $location === 'floating',
            'pathjson' => json_encode($path),
            'firstnamejson' => json_encode((string) $USER->firstname),
            'placementid' => $placementid,
            'startlayout' => $startlayout,
            'wwwroot' => $CFG->wwwroot,
        ];
    }

    /**
     * Widget bootstrap markup. Chat UI is a React mount.
     *
     * @param \stdClass $placement
     * @return string
     */
    public function widget_bootstrap(\stdClass $placement): string {
        global $ME;
        return $this->render_from_template('tool_adminagent/widget', $this->chat_mount_context(
            $ME ?? '/',
            (int) $placement->id,
            (string) $placement->location
        ));
    }

    /**
     * Full screen chat page body.
     *
     * @return string
     */
    public function fullscreen_chat(): string {
        return $this->render_from_template(
            'tool_adminagent/fullscreen',
            $this->chat_mount_context('/admin/tool/adminagent/chat.php', 0, 'fullscreen', 'fullscreen')
        );
    }
}
