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
 * Admin settings for tool_adminagent.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_externalpage(
        'tool_adminagent_config',
        get_string('pluginname', 'tool_adminagent'),
        new moodle_url('/admin/tool/adminagent/index.php'),
        'tool/adminagent:manage'
    ));

    $settings = new admin_settingpage(
        'tool_adminagent',
        get_string('settingspage', 'tool_adminagent'),
        'tool/adminagent:manage'
    );

    $settings->add(new admin_setting_heading(
        'tool_adminagent/generalheading',
        get_string('settings:general', 'tool_adminagent'),
        html_writer::link(
            new moodle_url('/admin/tool/adminagent/index.php'),
            '← ' . get_string('backtoconfig', 'tool_adminagent')
        )
    ));

    $settings->add(new admin_setting_configmulticheckbox(
        'tool_adminagent/viewtypes',
        get_string('settings:viewtypes', 'tool_adminagent'),
        get_string('settings:viewtypes_desc', 'tool_adminagent'),
        ['overlay' => 1, 'sidebar' => 1, 'fullscreen' => 1],
        [
            'overlay' => get_string('view:overlay', 'tool_adminagent'),
            'sidebar' => get_string('view:sidebar', 'tool_adminagent'),
            'fullscreen' => get_string('view:fullscreen', 'tool_adminagent'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_adminagent/allowfeedback',
        get_string('settings:allowfeedback', 'tool_adminagent'),
        get_string('settings:allowfeedback_desc', 'tool_adminagent'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'tool_adminagent/maxfilesize',
        get_string('settings:maxfilesize', 'tool_adminagent'),
        get_string('settings:maxfilesize_desc', 'tool_adminagent'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'tool_adminagent/inlinefilecharlimit',
        get_string('settings:inlinefilecharlimit', 'tool_adminagent'),
        get_string('settings:inlinefilecharlimit_desc', 'tool_adminagent'),
        50,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'tool_adminagent/maxfunctionresultsize',
        get_string('settings:maxfunctionresultsize', 'tool_adminagent'),
        get_string('settings:maxfunctionresultsize_desc', 'tool_adminagent'),
        200,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configduration(
        'tool_adminagent/conversationretention',
        get_string('settings:conversationretention', 'tool_adminagent'),
        get_string('settings:conversationretention_desc', 'tool_adminagent'),
        30 * DAYSECS,
        DAYSECS
    ));

    $settings->add(new admin_setting_configduration(
        'tool_adminagent/logretention',
        get_string('settings:logretention', 'tool_adminagent'),
        get_string('settings:logretention_desc', 'tool_adminagent'),
        0,
        DAYSECS
    ));

    $settings->add(new admin_setting_configtextarea(
        'tool_adminagent/systeminstruction',
        get_string('settings:systeminstruction', 'tool_adminagent'),
        get_string('settings:systeminstruction_desc', 'tool_adminagent'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'tool_adminagent/dynamicdiscovery',
        get_string('settings:dynamicdiscovery', 'tool_adminagent'),
        get_string('settings:dynamicdiscovery_desc', 'tool_adminagent'),
        'automatic',
        [
            'automatic' => get_string('settings:dynamicdiscovery_automatic', 'tool_adminagent'),
            'on' => get_string('settings:dynamicdiscovery_on', 'tool_adminagent'),
            'off' => get_string('settings:dynamicdiscovery_off', 'tool_adminagent'),
        ]
    ));

    $settings->add(new admin_setting_heading(
        'tool_adminagent/openaiheading',
        get_string('settings:openaiheading', 'tool_adminagent'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'tool_adminagent/openai_api_type',
        get_string('settings:openai_api_type', 'tool_adminagent'),
        get_string('settings:openai_api_type_desc', 'tool_adminagent'),
        'responses',
        [
            'responses' => get_string('settings:openai_api_type_responses', 'tool_adminagent'),
            'completions' => get_string('settings:openai_api_type_completions', 'tool_adminagent'),
        ]
    ));

    $settings->add(new admin_setting_configtextarea(
        'tool_adminagent/responses_endpoints',
        get_string('settings:responses_endpoints', 'tool_adminagent'),
        get_string('settings:responses_endpoints_desc', 'tool_adminagent'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'tool_adminagent/geminiheading',
        get_string('settings:geminiheading', 'tool_adminagent'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'tool_adminagent/gemini_api_type',
        get_string('settings:gemini_api_type', 'tool_adminagent'),
        get_string('settings:gemini_api_type_desc', 'tool_adminagent'),
        'generatecontent',
        [
            'generatecontent' => get_string('settings:gemini_api_type_generatecontent', 'tool_adminagent'),
            'interactions' => get_string('settings:gemini_api_type_interactions', 'tool_adminagent'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_adminagent/gemini_builtin_google_search',
        get_string('settings:gemini_builtin_google_search', 'tool_adminagent'),
        get_string('settings:gemini_builtin_google_search_desc', 'tool_adminagent'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_adminagent/gemini_builtin_url_context',
        get_string('settings:gemini_builtin_url_context', 'tool_adminagent'),
        get_string('settings:gemini_builtin_url_context_desc', 'tool_adminagent'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_adminagent/gemini_builtin_code_execution',
        get_string('settings:gemini_builtin_code_execution', 'tool_adminagent'),
        get_string('settings:gemini_builtin_code_execution_desc', 'tool_adminagent'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'tool_adminagent/anthropicheading',
        get_string('settings:anthropicheading', 'tool_adminagent'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_adminagent/anthropic_web_fetch',
        get_string('settings:anthropic_web_fetch', 'tool_adminagent'),
        get_string('settings:anthropic_web_fetch_desc', 'tool_adminagent'),
        1
    ));

    // Moodle 5.2 tool plugins must add the page themselves; load_settings() only includes this file.
    $ADMIN->add('tools', $settings);
}
