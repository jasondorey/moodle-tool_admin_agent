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
 * Feedback, lesson, workshop, extras.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

/**
 * x_mod_feedback_* .
 */
class feedback_ops {

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
        require_once($CFG->dirroot . '/mod/feedback/lib.php');
        return match ($name) {
            'x_mod_feedback_create_modules', 'x_mod_feedback_update_module' => module_ops::execute($name, $args),
            'x_mod_feedback_pre_update' => self::pre_update($args),
            'x_mod_feedback_create_items' => self::create_items($args),
            'x_mod_feedback_update_item' => self::update_item($args),
            'x_mod_feedback_delete_item' => self::delete_item($args),
            default => module_ops::execute($name, $args),
        };
    }

    /**
     * Settings and items.
     *
     * @param array $args
     * @return array
     */
    protected static function pre_update(array $args): array {
        global $DB;
        $base = module_ops::pre_update('feedback', $args);
        $cm = get_coursemodule_from_id('feedback', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $items = $DB->get_records('feedback_item', ['feedback' => $cm->instance], 'position ASC');
        $base['items'] = array_values($items);
        $base['has_responses'] = $DB->record_exists('feedback_completed', ['feedback' => $cm->instance]);
        return $base;
    }

    /**
     * Create items.
     *
     * @param array $args
     * @return array
     */
    protected static function create_items(array $args): array {
        global $DB;
        $cm = get_coursemodule_from_id('feedback', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $position = (int) $DB->get_field_sql('SELECT MAX(position) FROM {feedback_item} WHERE feedback = ?', [$cm->instance]);
        $created = [];
        foreach ($args['items'] ?? [] as $item) {
            $position++;
            $record = (object) [
                'feedback' => $cm->instance,
                'template' => 0,
                'name' => $item['name'] ?? '',
                'label' => $item['label'] ?? '',
                'presentation' => $item['presentation'] ?? $item['options'] ?? '',
                'typ' => $item['typ'] ?? $item['type'] ?? 'textfield',
                'hasvalue' => $item['hasvalue'] ?? 1,
                'position' => $item['position'] ?? $position,
                'required' => $item['required'] ?? 0,
                'dependitem' => $item['dependitem'] ?? 0,
                'dependvalue' => $item['dependvalue'] ?? '',
                'options' => $item['options'] ?? '',
            ];
            $record->id = $DB->insert_record('feedback_item', $record);
            $created[] = $record;
        }
        return ['items' => $created];
    }

    /**
     * Update one item.
     *
     * @param array $args
     * @return array
     */
    protected static function update_item(array $args): array {
        global $DB;
        $id = (int) ($args['id'] ?? $args['itemid'] ?? 0);
        $record = $DB->get_record('feedback_item', ['id' => $id], '*', MUST_EXIST);
        foreach (['name', 'label', 'presentation', 'typ', 'required', 'position', 'dependitem', 'dependvalue', 'options'] as $key) {
            if (array_key_exists($key, $args)) {
                $record->$key = $args[$key];
            }
        }
        $DB->update_record('feedback_item', $record);
        return (array) $record;
    }

    /**
     * Delete an item.
     *
     * @param array $args
     * @return array
     */
    protected static function delete_item(array $args): array {
        global $DB;
        $id = (int) ($args['id'] ?? $args['itemid'] ?? 0);
        $DB->delete_records('feedback_item', ['id' => $id]);
        return ['deleted' => true];
    }
}
