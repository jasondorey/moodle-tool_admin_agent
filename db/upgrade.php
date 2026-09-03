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
 * Upgrade steps for tool_adminagent.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_adminagent_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090201) {
        $table = new xmldb_table('tool_adminagent_message');
        $field = new xmldb_field('feedback', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '', 'outputtokens');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        \tool_adminagent\local\catalog::sync();
        upgrade_plugin_savepoint(true, 2026090201, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090202) {
        upgrade_plugin_savepoint(true, 2026090202, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090203) {
        upgrade_plugin_savepoint(true, 2026090203, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090204) {
        upgrade_plugin_savepoint(true, 2026090204, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090205) {
        upgrade_plugin_savepoint(true, 2026090205, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090206) {
        upgrade_plugin_savepoint(true, 2026090206, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090207) {
        upgrade_plugin_savepoint(true, 2026090207, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090208) {
        upgrade_plugin_savepoint(true, 2026090208, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090209) {
        upgrade_plugin_savepoint(true, 2026090209, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090210) {
        upgrade_plugin_savepoint(true, 2026090210, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090211) {
        upgrade_plugin_savepoint(true, 2026090211, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090212) {
        upgrade_plugin_savepoint(true, 2026090212, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090213) {
        upgrade_plugin_savepoint(true, 2026090213, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090214) {
        upgrade_plugin_savepoint(true, 2026090214, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090215) {
        upgrade_plugin_savepoint(true, 2026090215, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090216) {
        upgrade_plugin_savepoint(true, 2026090216, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090217) {
        upgrade_plugin_savepoint(true, 2026090217, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090218) {
        upgrade_plugin_savepoint(true, 2026090218, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090219) {
        upgrade_plugin_savepoint(true, 2026090219, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090220) {
        upgrade_plugin_savepoint(true, 2026090220, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090221) {
        upgrade_plugin_savepoint(true, 2026090221, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090301) {
        upgrade_plugin_savepoint(true, 2026090301, 'tool', 'adminagent');
    }

    if ($oldversion < 2026090302) {
        $table = new xmldb_table('tool_adminagent_kb');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('source', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('url', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('title', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, '');
            $table->add_field('chunk', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('tokens', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('tags', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('sourcetitlechunkix', XMLDB_INDEX_UNIQUE, ['source', 'title', 'chunk']);
            $table->add_index('titleix', XMLDB_INDEX_NOTUNIQUE, ['title']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026090302, 'tool', 'adminagent');
    }

    return true;
}
