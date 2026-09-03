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
 * Lesson bundled functions.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local\bundled;

/**
 * x_mod_lesson_* .
 */
class lesson_ops {

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
        require_once($CFG->dirroot . '/mod/lesson/locallib.php');
        if (str_ends_with($name, '_create_modules') || str_ends_with($name, '_update_module')) {
            return module_ops::execute($name, $args);
        }
        if ($name === 'x_mod_lesson_pre_update') {
            $base = module_ops::pre_update('lesson', $args);
            $cm = get_coursemodule_from_id('lesson', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
            $pages = $DB->get_records('lesson_pages', ['lessonid' => $cm->instance], 'id ASC');
            $base['pages'] = array_values($pages);
            return $base;
        }
        if ($name === 'x_mod_lesson_delete_pages') {
            $ids = $args['pageids'] ?? $args['ids'] ?? [];
            foreach ((array) $ids as $id) {
                $DB->delete_records('lesson_answers', ['pageid' => $id]);
                $DB->delete_records('lesson_pages', ['id' => $id]);
            }
            return ['deleted' => array_values((array) $ids)];
        }
        if (preg_match('/^x_mod_lesson_create_([a-z]+)_pages$/', $name, $m)) {
            return self::create_pages($m[1], $args);
        }
        if (preg_match('/^x_mod_lesson_update_([a-z_]+)_page$/', $name, $m)) {
            return self::update_page($args);
        }
        return module_ops::execute($name, $args);
    }

    /**
     * Create lesson pages.
     *
     * @param string $qtype
     * @param array $args
     * @return array
     */
    protected static function create_pages(string $qtype, array $args): array {
        global $DB;
        $cm = get_coursemodule_from_id('lesson', (int) ($args['cmid'] ?? 0), 0, false, MUST_EXIST);
        $type = match ($qtype) {
            'content' => LESSON_PAGE_BRANCHTABLE,
            'multichoice' => LESSON_PAGE_MULTICHOICE,
            'truefalse' => LESSON_PAGE_TRUEFALSE,
            'shortanswer' => LESSON_PAGE_SHORTANSWER,
            'numerical' => LESSON_PAGE_NUMERICAL,
            'matching' => LESSON_PAGE_MATCHING,
            'essay' => LESSON_PAGE_ESSAY,
            'cluster' => LESSON_PAGE_CLUSTER,
            'endofcluster' => LESSON_PAGE_ENDOFCLUSTER,
            default => LESSON_PAGE_BRANCHTABLE,
        };
        $created = [];
        foreach ($args['pages'] ?? [$args] as $page) {
            $record = (object) [
                'lessonid' => $cm->instance,
                'qtype' => $type,
                'qoption' => $page['qoption'] ?? 0,
                'layout' => $page['layout'] ?? 1,
                'display' => $page['display'] ?? 1,
                'timecreated' => time(),
                'timemodified' => time(),
                'title' => $page['title'] ?? $page['name'] ?? 'Page',
                'contents' => $page['contents'] ?? $page['content'] ?? '',
                'contentsformat' => FORMAT_HTML,
            ];
            $record->id = $DB->insert_record('lesson_pages', $record);
            foreach ($page['answers'] ?? [] as $answer) {
                $DB->insert_record('lesson_answers', (object) [
                    'lessonid' => $cm->instance,
                    'pageid' => $record->id,
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'answer' => $answer['answer'] ?? '',
                    'answerformat' => FORMAT_HTML,
                    'response' => $answer['response'] ?? '',
                    'responseformat' => FORMAT_HTML,
                    'jumpto' => $answer['jumpto'] ?? LESSON_NEXTPAGE,
                    'score' => $answer['score'] ?? 0,
                ]);
            }
            $created[] = $record;
        }
        return ['pages' => $created];
    }

    /**
     * Update a lesson page.
     *
     * @param array $args
     * @return array
     */
    protected static function update_page(array $args): array {
        global $DB;
        $id = (int) ($args['id'] ?? $args['pageid'] ?? 0);
        $record = $DB->get_record('lesson_pages', ['id' => $id], '*', MUST_EXIST);
        foreach (['title', 'contents', 'qoption', 'layout', 'display', 'prevpageid', 'nextpageid'] as $key) {
            if (array_key_exists($key, $args)) {
                $record->$key = $args[$key];
            }
        }
        $record->timemodified = time();
        $DB->update_record('lesson_pages', $record);
        return (array) $record;
    }
}
