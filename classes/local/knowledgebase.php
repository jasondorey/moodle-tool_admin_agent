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
 * Knowledgebase store and search.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\local;

/**
 * RAG knowledgebase: chunked article storage with keyword search.
 */
class knowledgebase {

    /** @var string Default source identifier. */
    const SOURCE_MOODLE_DOCS = 'docs.moodle.org';

    /** @var int Target chunk size in tokens (approx 4 chars/token). */
    const CHUNK_TOKEN_TARGET = 400;

    /**
     * Search the knowledgebase using keyword matching.
     *
     * Returns the most relevant chunks, ranked by keyword overlap.
     *
     * @param string $query Natural-language search query.
     * @param int $limit Max chunks to return.
     * @param string|null $source Filter by source, or null for all.
     * @return array Array of {title, url, content, score} objects.
     */
    public static function search(string $query, int $limit = 5, ?string $source = null): array {
        global $DB;

        $tokens = self::tokenise($query);
        if (empty($tokens)) {
            return [];
        }

        // Build SQL LIKE conditions for each keyword.
        $conditions = [];
        $params = [];
        $i = 0;
        foreach ($tokens as $token) {
            $paramname = 'kw' . $i;
            $conditions[] = $DB->sql_like('k.content', ':' . $paramname, false, false);
            $params[$paramname] = '%' . $DB->sql_like_escape($token) . '%';
            $i++;

            // Also search title.
            $paramname2 = 'tkw' . $i;
            $conditions[] = $DB->sql_like('k.title', ':' . $paramname2, false, false);
            $params[$paramname2] = '%' . $DB->sql_like_escape($token) . '%';
            $i++;
        }

        $where = '(' . implode(' OR ', $conditions) . ')';
        if ($source !== null) {
            $where .= ' AND k.source = :source';
            $params['source'] = $source;
        }

        $sql = "SELECT k.id, k.title, k.url, k.content, k.chunk, k.tokens
                  FROM {tool_adminagent_kb} k
                 WHERE {$where}
              ORDER BY k.id ASC";

        $rows = $DB->get_records_sql($sql, $params, 0, $limit * 3);

        // Rank by keyword hit count.
        $scored = [];
        foreach ($rows as $row) {
            $score = 0;
            $haystack = strtolower($row->title . ' ' . $row->content);
            foreach ($tokens as $token) {
                $score += substr_count($haystack, $token);
            }
            // Boost exact title matches heavily.
            $titlelower = strtolower($row->title);
            foreach ($tokens as $token) {
                if (str_contains($titlelower, $token)) {
                    $score += 5;
                }
            }
            $row->score = $score;
            $scored[] = $row;
        }

        usort($scored, fn($a, $b) => $b->score <=> $a->score);
        $scored = array_slice($scored, 0, $limit);

        return array_map(fn($row) => [
            'title' => $row->title,
            'url' => $row->url ?? '',
            'chunk' => (int) $row->chunk,
            'content' => $row->content,
            'score' => $row->score,
        ], $scored);
    }

    /**
     * Import an article, chunking it into passages.
     *
     * @param string $source Source identifier.
     * @param string $url Original URL.
     * @param string $title Article title.
     * @param string $content Full article text.
     * @param array $tags Optional tags.
     * @return int Number of chunks inserted.
     */
    public static function import_article(
        string $source,
        string $url,
        string $title,
        string $content,
        array $tags = []
    ): int {
        global $DB;

        // Remove existing chunks for this article.
        $DB->delete_records_select(
            'tool_adminagent_kb',
            'source = :source AND title = :title',
            ['source' => $source, 'title' => $title]
        );

        $chunks = self::chunk_text($content);
        $now = time();
        $tagjson = json_encode(array_values($tags));

        foreach ($chunks as $index => $chunk) {
            $record = (object) [
                'source' => $source,
                'url' => $url,
                'title' => $title,
                'chunk' => $index,
                'content' => $chunk,
                'tokens' => self::estimate_tokens($chunk),
                'tags' => $tagjson,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('tool_adminagent_kb', $record);
        }

        return count($chunks);
    }

    /**
     * Delete all chunks for a source.
     *
     * @param string $source
     * @return int Rows deleted.
     */
    public static function delete_source(string $source): int {
        global $DB;
        $count = $DB->count_records('tool_adminagent_kb', ['source' => $source]);
        $DB->delete_records('tool_adminagent_kb', ['source' => $source]);
        return $count;
    }

    /**
     * Get stats.
     *
     * @return array {sources: int, articles: int, chunks: int, tokens: int}
     */
    public static function stats(): array {
        global $DB;
        $sql = "SELECT COUNT(*) AS chunks,
                       COUNT(DISTINCT title) AS articles,
                       COUNT(DISTINCT source) AS sources,
                       COALESCE(SUM(tokens), 0) AS tokens
                  FROM {tool_adminagent_kb}";
        $row = $DB->get_record_sql($sql);
        return [
            'sources' => (int) $row->sources,
            'articles' => (int) $row->articles,
            'chunks' => (int) $row->chunks,
            'tokens' => (int) $row->tokens,
        ];
    }

    /**
     * Split text into chunks of roughly CHUNK_TOKEN_TARGET tokens.
     *
     * Splits on paragraph boundaries (double newline), then merges small
     * paragraphs and splits oversized ones on sentence boundaries.
     *
     * @param string $text
     * @return string[]
     */
    public static function chunk_text(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $paragraphs = preg_split('/\n{2,}/', $text);
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            $combined = $current !== '' ? $current . "\n\n" . $para : $para;
            if (self::estimate_tokens($combined) <= self::CHUNK_TOKEN_TARGET) {
                $current = $combined;
            } else {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                // If this single paragraph is too big, split on sentences.
                if (self::estimate_tokens($para) > self::CHUNK_TOKEN_TARGET) {
                    $sentences = preg_split('/(?<=[.!?])\s+/', $para);
                    $current = '';
                    foreach ($sentences as $sentence) {
                        $combined = $current !== '' ? $current . ' ' . $sentence : $sentence;
                        if (self::estimate_tokens($combined) <= self::CHUNK_TOKEN_TARGET) {
                            $current = $combined;
                        } else {
                            if ($current !== '') {
                                $chunks[] = $current;
                            }
                            $current = $sentence;
                        }
                    }
                } else {
                    $current = $para;
                }
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Rough token estimate (~4 chars per token).
     *
     * @param string $text
     * @return int
     */
    public static function estimate_tokens(string $text): int {
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Tokenise a query into searchable keywords.
     *
     * @param string $query
     * @return string[]
     */
    protected static function tokenise(string $query): array {
        $words = preg_split('/[\s\-_\/]+/', strtolower(trim($query)));
        // Remove very short words and common stop words.
        $stop = ['the', 'a', 'an', 'is', 'in', 'on', 'to', 'for', 'of', 'and', 'or', 'it', 'how', 'do', 'i', 'my', 'can', 'what'];
        $tokens = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/', '', $word);
            if (strlen($word) >= 2 && !in_array($word, $stop, true)) {
                $tokens[] = $word;
            }
        }
        return array_unique($tokens);
    }
}
