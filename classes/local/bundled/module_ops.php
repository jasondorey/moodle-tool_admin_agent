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
 * Generic activity module create/update/pre-update.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

/**
 * x_mod_*_create_modules / update_module / pre_update.
 */
class module_ops {

    /**
     * Schema.
     *
     * @return array
     */
    public static function schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'courseid' => ['type' => 'number'],
                'section' => ['type' => 'number'],
                'cmid' => ['type' => 'number'],
                'name' => ['type' => 'string'],
                'intro' => ['type' => 'string'],
                'visible' => ['type' => 'number'],
            ],
        ];
    }

    /**
     * Execute.
     *
     * @param string $name
     * @param array $args
     * @return array
     */
    public static function execute(string $name, array $args): array {
        if (preg_match('/^x_mod_([a-z0-9]+)_create_modules$/', $name, $m)) {
            return self::create_modules($m[1], $args);
        }
        if (preg_match('/^x_mod_([a-z0-9]+)_update_module$/', $name, $m)) {
            return self::update_module($m[1], $args);
        }
        if (preg_match('/^x_mod_([a-z0-9]+)_pre_update$/', $name, $m)) {
            return self::pre_update($m[1], $args);
        }
        throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', $name);
    }

    /**
     * Create one or more modules.
     *
     * @param string $modname
     * @param array $args
     * @return array
     */
    public static function create_modules(string $modname, array $args): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');
        $modules = $args['modules'] ?? [$args];
        $created = [];
        foreach ($modules as $mod) {
            $courseid = (int) ($mod['courseid'] ?? $args['courseid'] ?? 0);
            $course = get_course($courseid);
            $module = $DB->get_record('modules', ['name' => $modname], '*', MUST_EXIST);
            $fromform = (object) array_merge([
                'modulename' => $modname,
                'module' => $module->id,
                'name' => $mod['name'] ?? $modname,
                'intro' => $mod['intro'] ?? '',
                'introformat' => FORMAT_HTML,
                'section' => (int) ($mod['section'] ?? 0),
                'visible' => $mod['visible'] ?? 1,
                'cmidnumber' => $mod['idnumber'] ?? '',
                'groupmode' => $mod['groupmode'] ?? 0,
                'course' => $courseid,
            ], $mod);
            unset($fromform['courseid'], $fromform['modules']);
            $moduleinfo = add_moduleinfo($fromform, $course);
            $created[] = [
                'cmid' => $moduleinfo->coursemodule,
                'instanceid' => $moduleinfo->instance,
                'modname' => $modname,
                'name' => $moduleinfo->name,
                'url' => (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $moduleinfo->coursemodule]))->out(false),
            ];
        }
        return ['modules' => $created];
    }

    /**
     * Read current module state.
     *
     * @param string $modname
     * @param array $args
     * @return array
     */
    public static function pre_update(string $modname, array $args): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        $cmid = (int) ($args['cmid'] ?? $args['id'] ?? 0);
        $cm = get_coursemodule_from_id($modname, $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $module = get_moduleinfo_data($cm, $course);
        $info = $module[0] ?? (object) [];
        return json_decode(json_encode($info), true) ?: ['cmid' => $cmid];
    }

    /**
     * Update an existing module.
     *
     * @param string $modname
     * @param array $args
     * @return array
     */
    public static function update_module(string $modname, array $args): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        $cmid = (int) ($args['cmid'] ?? 0);
        $cm = get_coursemodule_from_id($modname, $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        [$moduleinfo] = get_moduleinfo_data($cm, $course);
        foreach ($args as $key => $value) {
            if (in_array($key, ['cmid', 'modname'], true)) {
                continue;
            }
            $moduleinfo->$key = $value;
        }
        $moduleinfo->coursemodule = $cm->id;
        $moduleinfo->modulename = $modname;
        $moduleinfo->course = $course->id;
        update_moduleinfo($cm, $moduleinfo, $course);
        return [
            'cmid' => $cm->id,
            'modname' => $modname,
            'name' => $moduleinfo->name ?? $cm->name,
            'url' => (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cm->id]))->out(false),
        ];
    }
}
