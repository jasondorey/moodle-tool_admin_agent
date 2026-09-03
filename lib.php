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
 * Library callbacks for tool_adminagent.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve plugin files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function tool_adminagent_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $USER;

    require_login();

    if ($context->contextlevel != CONTEXT_USER && $context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    $allowed = ['conversation', 'draft'];
    if (!in_array($filearea, $allowed, true)) {
        return false;
    }

    if (!has_capability('tool/adminagent:use', context_system::instance())) {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'tool_adminagent', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * File area options for skill import.
 *
 * @return array
 */
function tool_adminagent_file_options(): array {
    return [
        'subdirs' => 0,
        'maxfiles' => 20,
        'accepted_types' => ['.json'],
        'return_types' => FILE_INTERNAL,
    ];
}

/**
 * User preferences.
 *
 * @return array
 */
function tool_adminagent_user_preferences(): array {
    return [
        'tool_adminagent_layout' => [
            'type' => PARAM_ALPHANUMEXT,
            'null' => NULL_NOT_ALLOWED,
            'default' => 'overlay',
            'choices' => ['overlay', 'sidebar', 'fullscreen'],
        ],
    ];
}

/**
 * Map plugin pix names to Font Awesome classes.
 *
 * @return array
 */
function tool_adminagent_get_fontawesome_icon_map(): array {
    return [
        'tool_adminagent:attach' => 'fa-paperclip',
        'tool_adminagent:layout' => 'fa-table-columns',
        'tool_adminagent:safe' => 'fa-shield-halved',
        'tool_adminagent:all' => 'fa-bolt',
        'tool_adminagent:thumbsdown' => 'fa-thumbs-down',
        'tool_adminagent:comment-nodes' => 'fa-comment-nodes',
    ];
}
