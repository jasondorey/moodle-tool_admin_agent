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
 * Store chat attachments in the user draft area.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_adminagent\local\conversation;

/**
 * Store chat attachments in the user draft area.
 */
class upload_files extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'conversationid' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'files' => new external_multiple_structure(new external_single_structure([
                'filename' => new external_value(PARAM_FILE, ''),
                'filecontent' => new external_value(PARAM_RAW, 'Base64 encoded file'),
            ])),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $conversationid
     * @param array $files
     * @return array
     */
    public static function execute(int $conversationid, array $files): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('conversationid', 'files'));
        $context = helper::require_use();
        self::validate_context($context);
        if ($params['conversationid']) {
            conversation::require($params['conversationid'], $USER->id);
        }

        $maxmb = (int) get_config('tool_adminagent', 'maxfilesize') ?: 20;
        $itemid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();
        $stored = [];
        foreach ($params['files'] as $file) {
            $binary = base64_decode($file['filecontent'], true);
            if ($binary === false) {
                throw new \invalid_parameter_exception('Invalid file encoding');
            }
            if (strlen($binary) > $maxmb * 1024 * 1024) {
                throw new \moodle_exception('errorfilesize', 'tool_adminagent', '', $maxmb);
            }
            $record = [
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $itemid,
                'filepath' => '/',
                'filename' => $file['filename'],
                'userid' => $USER->id,
            ];
            $storedfile = $fs->create_file_from_string($record, $binary);
            $stored[] = [
                'filename' => $storedfile->get_filename(),
                'itemid' => $itemid,
                'size' => $storedfile->get_filesize(),
            ];
        }
        return ['itemid' => $itemid, 'files' => $stored];
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns() {
        return new external_single_structure([
            'itemid' => new external_value(PARAM_INT, ''),
            'files' => new external_multiple_structure(new external_single_structure([
                'filename' => new external_value(PARAM_FILE, ''),
                'itemid' => new external_value(PARAM_INT, ''),
                'size' => new external_value(PARAM_INT, ''),
            ])),
        ]);
    }
}
