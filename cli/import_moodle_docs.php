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
 * Import Moodle documentation into the knowledgebase.
 *
 * Usage:
 *   php admin/tool/adminagent/cli/import_moodle_docs.php
 *   php admin/tool/adminagent/cli/import_moodle_docs.php --purge
 *   php admin/tool/adminagent/cli/import_moodle_docs.php --source=docs.moodle.org --file=/path/to/export.json
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use tool_adminagent\local\knowledgebase;

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'purge' => false,
    'file' => '',
    'source' => knowledgebase::SOURCE_MOODLE_DOCS,
    'crawl' => false,
    'pages' => '',
], [
    'h' => 'help',
    'f' => 'file',
    's' => 'source',
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error("Unrecognised options:\n  {$unrecognised}\nUse --help for usage.");
}

if ($options['help']) {
    echo "Import Moodle documentation into the Admin Agent knowledgebase.

Options:
  --crawl           Fetch pages from docs.moodle.org (default admin pages).
  --pages=URL,...    Comma-separated list of specific docs.moodle.org URLs to fetch.
  --file=PATH       Import from a JSON file instead. Format: [{\"title\":\"...\",\"url\":\"...\",\"content\":\"...\",\"tags\":[]},...].
  --source=NAME     Source identifier (default: docs.moodle.org).
  --purge           Delete all entries for the source before importing.
  -h, --help        Show this help.

Examples:
  php cli/import_moodle_docs.php --crawl
  php cli/import_moodle_docs.php --file=moodle_docs_export.json
  php cli/import_moodle_docs.php --purge --source=docs.moodle.org
  php cli/import_moodle_docs.php --pages=https://docs.moodle.org/en/Backup,https://docs.moodle.org/en/Restore
";
    exit(0);
}

$source = $options['source'];

if ($options['purge']) {
    $deleted = knowledgebase::delete_source($source);
    cli_writeln("Purged {$deleted} chunks from source '{$source}'.");
    if (!$options['file'] && !$options['crawl'] && !$options['pages']) {
        $stats = knowledgebase::stats();
        cli_writeln("KB now has {$stats['articles']} articles, {$stats['chunks']} chunks, ~{$stats['tokens']} tokens.");
        exit(0);
    }
}

// Import from JSON file.
if ($options['file']) {
    $path = $options['file'];
    if (!file_exists($path)) {
        cli_error("File not found: {$path}");
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        cli_error("Invalid JSON in {$path}");
    }
    $total = 0;
    foreach ($data as $i => $article) {
        $title = $article['title'] ?? 'Untitled ' . ($i + 1);
        $url = $article['url'] ?? '';
        $content = $article['content'] ?? '';
        $tags = $article['tags'] ?? [];
        if (trim($content) === '') {
            cli_writeln("  Skipping '{$title}' — empty content.");
            continue;
        }
        $chunks = knowledgebase::import_article($source, $url, $title, $content, $tags);
        $total += $chunks;
        cli_writeln("  Imported '{$title}' — {$chunks} chunks.");
    }
    cli_writeln("Imported {$total} chunks from " . count($data) . " articles.");
    $stats = knowledgebase::stats();
    cli_writeln("KB now has {$stats['articles']} articles, {$stats['chunks']} chunks, ~{$stats['tokens']} tokens.");
    exit(0);
}

// Crawl docs.moodle.org.
if ($options['crawl'] || $options['pages']) {
    if ($options['pages']) {
        $urls = array_filter(array_map('trim', explode(',', $options['pages'])));
    } else {
        $urls = default_doc_urls();
    }

    cli_writeln("Fetching " . count($urls) . " pages from docs.moodle.org...");
    $total = 0;
    $imported = 0;

    foreach ($urls as $url) {
        cli_write("  Fetching {$url} ... ");
        $result = fetch_doc_page($url);
        if ($result === null) {
            cli_writeln("FAILED");
            continue;
        }
        $chunks = knowledgebase::import_article($source, $url, $result['title'], $result['content'], $result['tags']);
        $total += $chunks;
        $imported++;
        cli_writeln("OK — {$chunks} chunks");
    }

    cli_writeln("Imported {$imported} pages ({$total} chunks).");
    $stats = knowledgebase::stats();
    cli_writeln("KB now has {$stats['articles']} articles, {$stats['chunks']} chunks, ~{$stats['tokens']} tokens.");
    exit(0);
}

cli_error("Nothing to do. Use --crawl, --pages, or --file. See --help.");

/**
 * Fetch and extract text content from a docs.moodle.org page.
 *
 * @param string $url
 * @return array|null {title, content, tags}
 */
function fetch_doc_page(string $url): ?array {
    $html = @file_get_contents($url);
    if ($html === false) {
        return null;
    }

    // Extract title.
    $title = '';
    if (preg_match('/<h1[^>]*id="firstHeading"[^>]*>(.*?)<\/h1>/si', $html, $m)) {
        $title = strip_tags($m[1]);
    } elseif (preg_match('/<title>(.*?)(?:\s*-\s*Moodle\s*Docs)?<\/title>/si', $html, $m)) {
        $title = strip_tags($m[1]);
    }
    $title = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    // Extract main content div.
    $content = '';
    if (preg_match('/<div[^>]*id="mw-content-text"[^>]*>(.*?)<\/div>\s*(?:<div[^>]*class="printfooter"|<noscript>|<!--)/si', $html, $m)) {
        $content = $m[1];
    } elseif (preg_match('/<div[^>]*class="mw-parser-output"[^>]*>(.*?)<\/div>/si', $html, $m)) {
        $content = $m[1];
    }

    if ($content === '') {
        return null;
    }

    // Strip navigation, TOC, edit links, etc.
    $content = preg_replace('/<div[^>]*id="toc"[^>]*>.*?<\/div>/si', '', $content);
    $content = preg_replace('/<span[^>]*class="mw-editsection"[^>]*>.*?<\/span>/si', '', $content);
    $content = preg_replace('/<table[^>]*class="[^"]*navbox[^"]*"[^>]*>.*?<\/table>/si', '', $content);

    // Convert headers to markdown-like format for readability.
    $content = preg_replace('/<h([2-6])[^>]*>(.*?)<\/h[2-6]>/si', "\n\n" . '## $2' . "\n", $content);

    // Convert lists.
    $content = preg_replace('/<li[^>]*>/i', "\n- ", $content);

    // Strip remaining HTML tags.
    $content = strip_tags($content);

    // Clean up whitespace.
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = preg_replace('/[ \t]+/', ' ', $content);
    $content = preg_replace('/\n{3,}/', "\n\n", $content);
    $content = trim($content);

    if ($content === '' || strlen($content) < 100) {
        return null;
    }

    // Extract tags from URL path.
    $tags = [];
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $parts = explode('/', trim($path, '/'));
    foreach ($parts as $part) {
        if ($part !== '' && $part !== 'en' && !is_numeric($part)) {
            $tags[] = str_replace('_', ' ', $part);
        }
    }

    return ['title' => $title, 'content' => $content, 'tags' => $tags];
}

/**
 * Default list of key Moodle admin documentation pages.
 *
 * @return string[]
 */
function default_doc_urls(): array {
    // Moodle 5.2 docs (matches current plugin requires).
    $base = 'https://docs.moodle.org/502/en/';
    $pages = [
        // Site administration.
        'Site_administration',
        'Managing_accounts',
        'Authentication',
        'Manual_accounts',
        'Email-based_self-registration',
        'Browse_list_of_users',
        'Bulk_user_actions',
        'User_profile_fields',
        'Cohorts',

        // Courses.
        'Courses',
        'Creating_a_course',
        'Course_settings',
        'Course_categories',
        'Course_enrolment',
        'Manual_enrolment',
        'Self_enrolment',
        'Guest_access',
        'Cohort_sync',
        'Course_backup',
        'Course_restore',
        'Course_reset',
        'Import_course_data',
        'Groups',
        'Groupings',

        // Grades.
        'Grades',
        'Grade_categories',
        'Grade_items',
        'Grader_report',
        'Grade_letters',
        'Grade_scales_and_calculations',
        'Outcomes',

        // Activities and resources.
        'Activities',
        'Assignment',
        'Forum',
        'Quiz',
        'Workshop',
        'Lesson',
        'Glossary',
        'Database_activity',
        'Wiki',
        'Choice',
        'Feedback',
        'H5P',
        'SCORM',
        'Book',
        'Page',
        'URL_resource',
        'File_resource',
        'Folder_resource',
        'Label',
        'LTI_and_Moodle',

        // Question bank.
        'Question_bank',
        'Question_categories',
        'Multiple_choice_question_type',
        'Short_answer_question_type',
        'Essay_question_type',
        'Matching_question_type',
        'True/False_question_type',

        // Competencies and learning plans.
        'Competencies',

        // Reports.
        'Reports',
        'Logs',
        'Activity_report',
        'Course_participation_report',

        // Plugins.
        'Installing_plugins',
        'Plugins',

        // Appearance and themes.
        'Themes',
        'Theme_settings',

        // Security.
        'Security_overview',
        'Site_policies',
        'IP_blocker',

        // Server.
        'Cron',
        'Server_settings',
        'Performance',
        'Caching',
        'Outgoing_mail_configuration',

        // Backup.
        'Site_backup',
        'Automated_course_backup',

        // Roles and permissions.
        'Roles_and_permissions',
        'Assign_roles',
        'Managing_roles',
        'Creating_custom_roles',

        // Calendar and messaging.
        'Calendar',
        'Messaging',

        // Badges.
        'Badges',
        'Using_badges',
        'Managing_badges',

        // Content bank.
        'Content_bank',

        // Accessibility.
        'Accessibility',
    ];

    return array_map(fn($p) => $base . $p, $pages);
}
