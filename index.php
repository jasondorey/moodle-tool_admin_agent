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
 * AI agent configuration.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/adminagent:manage', $context);

$tab = optional_param('tab', 'skills', PARAM_ALPHA);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/adminagent/index.php', ['tab' => $tab]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('configtitle', 'tool_adminagent'));
$PAGE->set_heading(get_string('configtitle', 'tool_adminagent'));
$PAGE->navbar->add(get_string('pluginname', 'tool_adminagent'), $PAGE->url);

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('tool_adminagent');
echo $renderer->config_page($tab);
echo $OUTPUT->footer();
