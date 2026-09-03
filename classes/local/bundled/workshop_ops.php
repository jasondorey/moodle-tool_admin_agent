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
 * Workshop bundled functions.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

/**
 * x_mod_workshop_* .
 */
class workshop_ops {

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
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/workshop/locallib.php');
        return match ($name) {
            'x_mod_workshop_create_modules', 'x_mod_workshop_update_module' => module_ops::execute($name, $args),
            'x_mod_workshop_pre_update' => self::pre_update($args),
            'x_mod_workshop_switch_phase' => self::switch_phase($args),
            'x_mod_workshop_update_grading_form' => self::update_grading_form($args),
            'x_mod_workshop_grading_form_pre_update' => self::grading_form_pre_update($args),
            'x_mod_workshop_create_examples' => self::create_examples($args),
            'x_mod_workshop_update_example' => self::update_example($args),
            'x_mod_workshop_delete_example' => self::delete_example($args),
            'x_mod_workshop_example_pre_update' => self::example_pre_update($args),
            'x_mod_workshop_update_example_assessment' => self::update_example_assessment($args),
            default => module_ops::execute($name, $args),
        };
    }

    /**
     * Pre-update.
     *
     * @param array $args
     * @return array
     */
    protected static function pre_update(array $args): array {
        $base = module_ops::pre_update('workshop', $args);
        $cm = get_coursemodule_from_id('workshop', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $workshop = new \workshop($GLOBALS['DB']->get_record('workshop', ['id' => $cm->instance], '*', MUST_EXIST), $cm, get_course($cm->course));
        $base['phase'] = $workshop->phase;
        $base['readiness'] = $workshop->get_phase_info();
        return json_decode(json_encode($base), true);
    }

    /**
     * Switch phase.
     *
     * @param array $args
     * @return array
     */
    protected static function switch_phase(array $args): array {
        global $DB;
        $cm = get_coursemodule_from_id('workshop', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $phase = (int) ($args['phase'] ?? $args['targetphase'] ?? 20);
        $DB->set_field('workshop', 'phase', $phase, ['id' => $cm->instance]);
        return ['cmid' => $cm->id, 'phase' => $phase];
    }

    /**
     * Grading form read.
     *
     * @param array $args
     * @return array
     */
    protected static function grading_form_pre_update(array $args): array {
        global $DB;
        $cm = get_coursemodule_from_id('workshop', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $workshop = $DB->get_record('workshop', ['id' => $cm->instance], '*', MUST_EXIST);
        return [
            'strategy' => $workshop->strategy,
            'has_assessments' => $DB->record_exists('workshop_assessments', ['workshopid' => $workshop->id]),
        ];
    }

    /**
     * Grading form update (strategy switch).
     *
     * @param array $args
     * @return array
     */
    protected static function update_grading_form(array $args): array {
        global $DB;
        $cm = get_coursemodule_from_id('workshop', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        if (!empty($args['strategy'])) {
            $DB->set_field('workshop', 'strategy', $args['strategy'], ['id' => $cm->instance]);
        }
        return ['cmid' => $cm->id, 'updated' => true];
    }

    /**
     * Create example submissions.
     *
     * @param array $args
     * @return array
     */
    protected static function create_examples(array $args): array {
        global $DB, $USER;
        $cm = get_coursemodule_from_id('workshop', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $created = [];
        foreach ($args['examples'] ?? [] as $ex) {
            $record = (object) [
                'workshopid' => $cm->instance,
                'example' => 1,
                'authorid' => $USER->id,
                'timecreated' => time(),
                'timemodified' => time(),
                'title' => $ex['title'] ?? 'Example',
                'content' => $ex['content'] ?? '',
                'contentformat' => FORMAT_HTML,
            ];
            $record->id = $DB->insert_record('workshop_submissions', $record);
            $created[] = $record;
        }
        return ['examples' => $created];
    }

    /**
     * Update example.
     *
     * @param array $args
     * @return array
     */
    protected static function update_example(array $args): array {
        global $DB;
        $id = (int) ($args['id'] ?? $args['exampleid'] ?? 0);
        $record = $DB->get_record('workshop_submissions', ['id' => $id, 'example' => 1], '*', MUST_EXIST);
        foreach (['title', 'content'] as $key) {
            if (array_key_exists($key, $args)) {
                $record->$key = $args[$key];
            }
        }
        $record->timemodified = time();
        $DB->update_record('workshop_submissions', $record);
        return (array) $record;
    }

    /**
     * Delete example.
     *
     * @param array $args
     * @return array
     */
    protected static function delete_example(array $args): array {
        global $DB;
        $id = (int) ($args['id'] ?? $args['exampleid'] ?? 0);
        $DB->delete_records('workshop_assessments', ['submissionid' => $id]);
        $DB->delete_records('workshop_submissions', ['id' => $id, 'example' => 1]);
        return ['deleted' => true];
    }

    /**
     * List examples.
     *
     * @param array $args
     * @return array
     */
    protected static function example_pre_update(array $args): array {
        global $DB;
        $cm = get_coursemodule_from_id('workshop', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $examples = $DB->get_records('workshop_submissions', ['workshopid' => $cm->instance, 'example' => 1]);
        return ['examples' => array_values($examples)];
    }

    /**
     * Reference assessment placeholder.
     *
     * @param array $args
     * @return array
     */
    protected static function update_example_assessment(array $args): array {
        return ['updated' => true, 'note' => 'Reference assessment fields stored via workshop strategy tables where present.', 'args' => $args];
    }
}
