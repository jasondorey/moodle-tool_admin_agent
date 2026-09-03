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
 * Remaining bundled helpers (availability, backups, grading, etc).
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

/**
 * Extra bundled functions.
 */
class extras {

    /**
     * Names this class can run.
     *
     * @param string $name
     * @return bool
     */
    public static function class_has(string $name): bool {
        return str_starts_with($name, 'x_core_') || str_starts_with($name, 'x_mod_') || str_starts_with($name, 'x_');
    }

    /**
     * Schema.
     *
     * @return array
     */
    public static function schema(): array {
        return ['type' => 'object', 'properties' => new \stdClass()];
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
            'x_core_availability_pre_update' => self::availability_pre($args),
            'x_core_availability_update' => self::availability_update($args),
            'x_core_backup_get_course_backups' => self::course_backups($args),
            'x_core_grading_definitions_pre_update' => self::grading_pre($args),
            'x_core_role_get_assignable_roles' => self::assignable_roles($args),
            'x_core_course_course_pre_update' => self::course_pre($args),
            'x_core_course_category_pre_update' => self::category_pre($args),
            default => self::generic_pre($name, $args),
        };
    }

    /**
     * Read availability JSON.
     *
     * @param array $args
     * @return array
     */
    protected static function availability_pre(array $args): array {
        global $DB;
        $cmid = (int) ($args['cmid'] ?? 0);
        $sectionid = (int) ($args['sectionid'] ?? 0);
        if ($cmid) {
            $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
            return ['cmid' => $cmid, 'availability' => $cm->availability, 'visible' => $cm->visible];
        }
        if ($sectionid) {
            $section = $DB->get_record('course_sections', ['id' => $sectionid], '*', MUST_EXIST);
            return ['sectionid' => $sectionid, 'availability' => $section->availability, 'visible' => $section->visible];
        }
        throw new \invalid_parameter_exception('cmid or sectionid required');
    }

    /**
     * Write availability JSON.
     *
     * @param array $args
     * @return array
     */
    protected static function availability_update(array $args): array {
        global $DB;
        $json = is_string($args['availability'] ?? null) ? $args['availability'] : json_encode($args['availability'] ?? null);
        if (!empty($args['cmid'])) {
            $DB->set_field('course_modules', 'availability', $json, ['id' => (int) $args['cmid']]);
            rebuild_course_cache((int) $DB->get_field('course_modules', 'course', ['id' => $args['cmid']]));
            return ['cmid' => (int) $args['cmid'], 'availability' => $json];
        }
        if (!empty($args['sectionid'])) {
            $DB->set_field('course_sections', 'availability', $json, ['id' => (int) $args['sectionid']]);
            $courseid = (int) $DB->get_field('course_sections', 'course', ['id' => $args['sectionid']]);
            rebuild_course_cache($courseid);
            return ['sectionid' => (int) $args['sectionid'], 'availability' => $json];
        }
        throw new \invalid_parameter_exception('cmid or sectionid required');
    }

    /**
     * List course backups.
     *
     * @param array $args
     * @return array
     */
    protected static function course_backups(array $args): array {
        $courseid = (int) ($args['courseid'] ?? 0);
        $context = \context_course::instance($courseid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'backup', 'course', 0, 'timemodified DESC', false);
        $out = [];
        foreach ($files as $file) {
            $out[] = [
                'filename' => $file->get_filename(),
                'size' => $file->get_filesize(),
                'timemodified' => $file->get_timemodified(),
                'downloadurl' => \moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                    $file->get_itemid(), $file->get_filepath(), $file->get_filename()
                )->out(false),
            ];
        }
        return ['backups' => $out];
    }

    /**
     * Grading definitions.
     *
     * @param array $args
     * @return array
     */
    protected static function grading_pre(array $args): array {
        global $CFG;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        $cmid = (int) ($args['cmid'] ?? 0);
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $manager = get_grading_manager($context);
        $methods = $manager->get_available_methods();
        $active = $manager->get_active_method();
        return [
            'cmid' => $cmid,
            'methods' => array_keys($methods),
            'activemethod' => $active,
        ];
    }

    /**
     * Assignable roles in a context.
     *
     * @param array $args
     * @return array
     */
    protected static function assignable_roles(array $args): array {
        $contextid = (int) ($args['contextid'] ?? \context_system::instance()->id);
        $context = \context::instance_by_id($contextid);
        $roles = get_assignable_roles($context);
        $out = [];
        foreach ($roles as $id => $name) {
            $out[] = ['id' => $id, 'name' => $name];
        }
        return ['roles' => $out];
    }

    /**
     * Course record.
     *
     * @param array $args
     * @return array
     */
    protected static function course_pre(array $args): array {
        $course = get_course((int) ($args['id'] ?? $args['courseid'] ?? 0));
        return (array) $course;
    }

    /**
     * Category record.
     *
     * @param array $args
     * @return array
     */
    protected static function category_pre(array $args): array {
        global $DB;
        $cat = $DB->get_record('course_categories', ['id' => (int) ($args['id'] ?? 0)], '*', MUST_EXIST);
        return (array) $cat;
    }

    /**
     * Fallback for other x_core_* names.
     *
     * @param string $name
     * @param array $args
     * @return array
     */
    protected static function generic_pre(string $name, array $args): array {
        if (str_contains($name, 'pre_update')) {
            return ['name' => $name, 'args' => $args, 'note' => 'Read current state using core web-service functions when this helper has no specialised reader.'];
        }
        throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', 'Unsupported bundled function: ' . $name);
    }
}
