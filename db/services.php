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
 * Web services for tool_adminagent.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_adminagent_get_widget_config' => [
        'classname' => \tool_adminagent\external\get_widget_config::class,
        'description' => 'Return widget bootstrap data for the current page.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_create_conversation' => [
        'classname' => \tool_adminagent\external\create_conversation::class,
        'description' => 'Start a new AI agent conversation.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_get_conversation' => [
        'classname' => \tool_adminagent\external\get_conversation::class,
        'description' => 'Load a conversation including messages and tool calls.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_send_message' => [
        'classname' => \tool_adminagent\external\send_message::class,
        'description' => 'Send a user message and run the agent loop until approval is needed or a reply is ready.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_resolve_call' => [
        'classname' => \tool_adminagent\external\resolve_call::class,
        'description' => 'Approve or deny a pending function call.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_toggle_item' => [
        'classname' => \tool_adminagent\external\toggle_item::class,
        'description' => 'Enable or disable a skill, placement or function.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_save_skill' => [
        'classname' => \tool_adminagent\external\save_skill::class,
        'description' => 'Create or update a skill.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_save_placement' => [
        'classname' => \tool_adminagent\external\save_placement::class,
        'description' => 'Create or update a placement.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_delete_item' => [
        'classname' => \tool_adminagent\external\delete_item::class,
        'description' => 'Delete a skill or placement.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_import_skills' => [
        'classname' => \tool_adminagent\external\import_skills::class,
        'description' => 'Import skills from JSON.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_set_layout' => [
        'classname' => \tool_adminagent\external\set_layout::class,
        'description' => 'Persist the user\'s preferred chat layout.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_upload_files' => [
        'classname' => \tool_adminagent\external\upload_files::class,
        'description' => 'Upload files attached to an AI agent chat.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_submit_feedback' => [
        'classname' => \tool_adminagent\external\submit_feedback::class,
        'description' => 'Rate an assistant message.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_list_conversations' => [
        'classname' => \tool_adminagent\external\list_conversations::class,
        'description' => 'List the current user\'s AI agent chats.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_adminagent_delete_conversation' => [
        'classname' => \tool_adminagent\external\delete_conversation::class,
        'description' => 'Delete an AI agent chat the current user owns.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
