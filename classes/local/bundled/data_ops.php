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
 * Database activity bundled functions.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

/**
 * x_mod_data_* helpers.
 */
class data_ops {

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
        require_once($CFG->dirroot . '/mod/data/lib.php');
        if (str_ends_with($name, '_create_modules') || str_ends_with($name, '_update_module') || str_ends_with($name, '_pre_update') && !str_contains($name, 'templates')) {
            if ($name !== 'x_mod_data_templates_pre_update' && $name !== 'x_mod_data_pre_update') {
                return module_ops::execute($name, $args);
            }
        }
        return match ($name) {
            'x_mod_data_pre_update' => self::pre_update($args),
            'x_mod_data_create_fields' => self::create_fields($args),
            'x_mod_data_update_field' => self::update_field($args),
            'x_mod_data_delete_field' => self::delete_field($args),
            'x_mod_data_update_templates' => self::update_templates($args),
            'x_mod_data_templates_pre_update' => self::templates_pre_update($args),
            default => module_ops::execute($name, $args),
        };
    }

    /**
     * Settings plus fields.
     *
     * @param array $args
     * @return array
     */
    protected static function pre_update(array $args): array {
        global $DB;
        $base = module_ops::pre_update('data', $args);
        $cmid = (int) ($args['cmid'] ?? 0);
        $cm = get_coursemodule_from_id('data', $cmid, 0, false, MUST_EXIST);
        $fields = $DB->get_records('data_fields', ['dataid' => $cm->instance], 'id ASC');
        $base['fields'] = array_values($fields);
        return $base;
    }

    /**
     * Create fields.
     *
     * @param array $args
     * @return array
     */
    protected static function create_fields(array $args): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/data/lib.php');
        $dataid = (int) ($args['dataid'] ?? 0);
        if (!$dataid && !empty($args['cmid'])) {
            $cm = get_coursemodule_from_id('data', (int) $args['cmid'], 0, false, MUST_EXIST);
            $dataid = $cm->instance;
        }
        $created = [];
        foreach ($args['fields'] ?? [] as $field) {
            $record = (object) [
                'dataid' => $dataid,
                'type' => $field['type'] ?? 'text',
                'name' => $field['name'] ?? 'Field',
                'description' => $field['description'] ?? '',
                'required' => $field['required'] ?? 0,
                'param1' => $field['param1'] ?? '',
                'param2' => $field['param2'] ?? '',
                'param3' => $field['param3'] ?? '',
                'param4' => $field['param4'] ?? '',
                'param5' => $field['param5'] ?? '',
            ];
            $record->id = $DB->insert_record('data_fields', $record);
            $created[] = $record;
        }
        return ['fields' => $created];
    }

    /**
     * Update a field.
     *
     * @param array $args
     * @return array
     */
    protected static function update_field(array $args): array {
        global $DB;
        $id = (int) ($args['id'] ?? $args['fieldid'] ?? 0);
        $record = $DB->get_record('data_fields', ['id' => $id], '*', MUST_EXIST);
        foreach (['name', 'description', 'type', 'required', 'param1', 'param2', 'param3', 'param4', 'param5'] as $key) {
            if (array_key_exists($key, $args)) {
                $record->$key = $args[$key];
            }
        }
        $DB->update_record('data_fields', $record);
        return (array) $record;
    }

    /**
     * Delete a field.
     *
     * @param array $args
     * @return array
     */
    protected static function delete_field(array $args): array {
        global $DB;
        $id = (int) ($args['id'] ?? $args['fieldid'] ?? 0);
        $DB->delete_records('data_fields', ['id' => $id]);
        return ['deleted' => true, 'id' => $id];
    }

    /**
     * Update templates.
     *
     * @param array $args
     * @return array
     */
    protected static function update_templates(array $args): array {
        global $DB;
        $cm = get_coursemodule_from_id('data', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $data = $DB->get_record('data', ['id' => $cm->instance], '*', MUST_EXIST);
        foreach (['listtemplate', 'listtemplateheader', 'listtemplatefooter', 'singletemplate', 'addtemplate',
            'asearchtemplate', 'rsstemplate', 'rsstitletemplate', 'csstemplate', 'jstemplate'] as $col) {
            if (array_key_exists($col, $args)) {
                $data->$col = $args[$col];
            }
        }
        $DB->update_record('data', $data);
        return ['cmid' => $cm->id, 'updated' => true];
    }

    /**
     * Read templates.
     *
     * @param array $args
     * @return array
     */
    protected static function templates_pre_update(array $args): array {
        global $DB;
        $cm = get_coursemodule_from_id('data', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $data = $DB->get_record('data', ['id' => $cm->instance], '*', MUST_EXIST);
        $fields = [];
        foreach (['listtemplate', 'listtemplateheader', 'listtemplatefooter', 'singletemplate', 'addtemplate',
            'asearchtemplate', 'rsstemplate', 'rsstitletemplate', 'csstemplate', 'jstemplate'] as $col) {
            $fields[$col] = $data->$col;
        }
        return $fields;
    }
}
