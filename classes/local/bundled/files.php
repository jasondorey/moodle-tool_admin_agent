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
 * File bundled functions.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

/**
 * File helpers used by skills and the system prompt.
 */
class files {

    /**
     * Names this class implements.
     *
     * @param string $name
     * @return bool
     */
    public static function class_has(string $name): bool {
        return in_array($name, [
            'read_file', 'extract_files', 'create_files', 'create_draft_file_area',
            'convert_file_to_image', 'crop_image', 'resize_image', 'generate_image',
        ], true);
    }

    /**
     * Schema fallback.
     *
     * @return array
     */
    public static function schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'file_url' => ['type' => 'string'],
                'itemid' => ['type' => 'number'],
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
        return match ($name) {
            'create_draft_file_area' => self::create_draft_file_area(),
            'read_file' => self::read_file($args),
            'extract_files' => self::extract_files($args),
            'create_files' => self::create_files($args),
            'convert_file_to_image' => self::convert_file_to_image($args),
            'crop_image' => self::crop_image($args),
            'resize_image' => self::resize_image($args),
            'generate_image' => ['error' => 'Use the site AI generate-image provider from a placement; this helper is not wired to a dedicated image model.'],
            default => throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', $name),
        };
    }

    /**
     * New draft area itemid.
     *
     * @return array
     */
    protected static function create_draft_file_area(): array {
        global $USER;
        $itemid = file_get_unused_draft_itemid();
        return [
            'itemid' => $itemid,
            'userid' => $USER->id,
        ];
    }

    /**
     * Read a stored or remote file (text inline, truncated).
     *
     * @param array $args
     * @return array
     */
    protected static function read_file(array $args): array {
        $url = $args['file_url'] ?? $args['url'] ?? '';
        $limit = ((int) get_config('tool_adminagent', 'inlinefilecharlimit') ?: 50) * 1024;
        if ($url === '') {
            throw new \invalid_parameter_exception('file_url is required');
        }
        $content = self::fetch_bytes($url);
        $truncated = strlen($content) > $limit;
        if ($truncated) {
            $content = substr($content, 0, $limit);
        }
        return [
            'content' => $content,
            'truncated' => $truncated,
            'bytes' => strlen($content),
        ];
    }

    /**
     * Extract named files from a zip (e.g. backup .mbz).
     *
     * @param array $args
     * @return array
     */
    protected static function extract_files(array $args): array {
        global $USER, $CFG;
        $url = $args['file_url'] ?? $args['downloadurl'] ?? '';
        $paths = $args['paths'] ?? $args['files'] ?? [];
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        if ($url === '' || !$paths) {
            throw new \invalid_parameter_exception('file_url and paths are required');
        }
        $bytes = self::fetch_bytes($url);
        $tmp = make_request_directory() . '/archive.zip';
        file_put_contents($tmp, $bytes);
        $zip = new \zip_packer();
        $dest = make_request_directory() . '/extracted';
        $zip->extract_to_pathname($tmp, $dest);
        $out = [];
        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        $itemid = file_get_unused_draft_itemid();
        foreach ($paths as $rel) {
            $full = $dest . '/' . ltrim($rel, '/');
            if (!is_readable($full)) {
                $out[] = ['path' => $rel, 'error' => 'not found'];
                continue;
            }
            $filename = basename($rel);
            $file = $fs->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $itemid,
                'filepath' => '/',
                'filename' => $filename,
            ], $full);
            $out[] = [
                'path' => $rel,
                'filename' => $filename,
                'file_url' => \moodle_url::make_draftfile_url($itemid, '/', $filename)->out(false),
                'size' => $file->get_filesize(),
            ];
        }
        return ['files' => $out, 'itemid' => $itemid];
    }

    /**
     * Create draft files from text content.
     *
     * @param array $args
     * @return array
     */
    protected static function create_files(array $args): array {
        global $USER;
        $files = $args['files'] ?? [];
        $itemid = $args['itemid'] ?? file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        $created = [];
        foreach ($files as $file) {
            $filename = clean_filename($file['filename'] ?? 'file.txt');
            $record = [
                'contextid' => $context->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $itemid,
                'filepath' => $file['filepath'] ?? '/',
                'filename' => $filename,
            ];
            $stored = $fs->create_file_from_string($record, $file['content'] ?? '');
            $created[] = [
                'filename' => $filename,
                'file_url' => \moodle_url::make_draftfile_url($itemid, $record['filepath'], $filename)->out(false),
                'size' => $stored->get_filesize(),
            ];
        }
        return ['itemid' => $itemid, 'files' => $created];
    }

    /**
     * Render PDF pages to PNG using Ghostscript if available.
     *
     * @param array $args
     * @return array
     */
    protected static function convert_file_to_image(array $args): array {
        $url = $args['file_url'] ?? '';
        if ($url === '') {
            throw new \invalid_parameter_exception('file_url is required');
        }
        return ['error' => 'PDF rasterisation is not available on this site. Attach images directly, or extract XML from backups with extract_files.'];
    }

    /**
     * Crop is a no-op without GD path from a stored draft image.
     *
     * @param array $args
     * @return array
     */
    protected static function crop_image(array $args): array {
        return ['error' => 'crop_image requires a stored image file_url and is not implemented for arbitrary URLs in this version.'];
    }

    /**
     * Resize stub.
     *
     * @param array $args
     * @return array
     */
    protected static function resize_image(array $args): array {
        return ['error' => 'resize_image is not implemented in this version.'];
    }

    /**
     * Fetch bytes from a Moodle pluginfile/draft URL or local path.
     *
     * @param string $url
     * @return string
     */
    protected static function fetch_bytes(string $url): string {
        global $CFG;
        $max = ((int) get_config('tool_adminagent', 'maxfilesize') ?: 20) * 1024 * 1024;
        if (str_starts_with($url, $CFG->wwwroot) || str_starts_with($url, '/')) {
            $curl = new \curl();
            $content = $curl->get($url);
            if ($content === false || $content === '') {
                throw new \moodle_exception('errorgeneric', 'tool_adminagent', '', 'Could not read file');
            }
        } else {
            throw new \invalid_parameter_exception('Only site-local file URLs can be read.');
        }
        if (strlen($content) > $max) {
            throw new \moodle_exception('errorfilesize', 'tool_adminagent', '', (int) get_config('tool_adminagent', 'maxfilesize'));
        }
        return $content;
    }
}
