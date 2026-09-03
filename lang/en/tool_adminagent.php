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
 * Language strings for tool_adminagent.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI agent';
$string['privacy:metadata:tool_adminagent_conversation'] = 'Chat sessions between a user and the AI agent.';
$string['privacy:metadata:tool_adminagent_conversation:userid'] = 'The user who owns the chat.';
$string['privacy:metadata:tool_adminagent_conversation:title'] = 'The chat title.';
$string['privacy:metadata:tool_adminagent_conversation:timecreated'] = 'When the chat was started.';
$string['privacy:metadata:tool_adminagent_conversation:timemodified'] = 'When the chat was last active.';
$string['privacy:metadata:tool_adminagent_message'] = 'Messages sent in AI agent chats, including content sent to the configured AI provider.';
$string['privacy:metadata:tool_adminagent_message:content'] = 'The message content.';
$string['privacy:metadata:tool_adminagent_message:role'] = 'Whether the message is from the user, the assistant, or a tool.';
$string['privacy:metadata:tool_adminagent_message:feedback'] = 'Optional thumbs-up or thumbs-down rating of an assistant reply.';
$string['privacy:metadata:tool_adminagent_message:timecreated'] = 'When the message was created.';
$string['privacy:metadata:tool_adminagent_toolcall'] = 'Function calls made during a chat, including arguments and results.';
$string['privacy:metadata:tool_adminagent_toolcall:functionname'] = 'The function that was called.';
$string['privacy:metadata:tool_adminagent_toolcall:arguments'] = 'Arguments passed to the function.';
$string['privacy:metadata:tool_adminagent_toolcall:result'] = 'The function result.';
$string['privacy:metadata:externalprovider'] = 'Message content and attached files are sent to the site\'s configured AI provider so the agent can reply.';

$string['adminagent:manage'] = 'Configure the AI agent';
$string['adminagent:use'] = 'Use the AI agent';

$string['settingspage'] = 'AI agent settings';
$string['settings:general'] = 'General';
$string['settings:viewtypes'] = 'Available view types';
$string['settings:viewtypes_desc'] = 'The chat views users can open: a small overlay in the corner of the page, a sidebar next to the page content, and the full screen chat page. Uncheck all to temporarily disable the AI agent for all users.';
$string['settings:allowfeedback'] = 'Allow feedback';
$string['settings:allowfeedback_desc'] = 'When enabled, users can rate assistant responses and flag failed function calls.';
$string['settings:maxfilesize'] = 'Maximum file size for the LLM (MB)';
$string['settings:maxfilesize_desc'] = 'Prevent users from sending files that are too big to the LLM. Files above this size are rejected before any bytes leave the site. Default: 20 MB.';
$string['settings:inlinefilecharlimit'] = 'Inline file content limit (KB)';
$string['settings:inlinefilecharlimit_desc'] = 'Maximum amount of a text file that can be embedded directly into the chat when a file is read inline. Longer content is truncated. Default: 50 KB.';
$string['settings:maxfunctionresultsize'] = 'Maximum function result size (KB)';
$string['settings:maxfunctionresultsize_desc'] = 'Maximum amount of data a single function may return to the AI model. A result above this limit is discarded and the model is told to narrow the request. Set to zero for no limit. Default: 200 KB.';
$string['settings:conversationretention'] = 'Keep chats for';
$string['settings:conversationretention_desc'] = 'Chats with no activity for this long are removed from users\' chat history. Set to 0 to keep chats forever.';
$string['settings:logretention'] = 'Keep chat logs for';
$string['settings:logretention_desc'] = 'Messages and token usage records of chats already deleted from history are permanently deleted after this period. Set to 0 to keep logs forever.';
$string['settings:systeminstruction'] = 'Custom system instructions';
$string['settings:systeminstruction_desc'] = 'Custom instructions sent to the AI model with every request. Leave empty to use the built-in default. Placeholders: {siteurl}, {userid}, {date}. Always appended: site timezone and language.';
$string['settings:dynamicdiscovery'] = 'Dynamic function discovery';
$string['settings:dynamicdiscovery_desc'] = 'When on, the first message in a new chat runs search_tools so the model only sees matching functions. Later messages that look like a new task quietly refresh that set (no extra card). Short follow-ups keep the current functions. The model can still call search_tools. In automatic mode this is turned on if more than 15 functions are available. Some AI providers have a limit on the number of functions (for example, OpenAI has a limit of 128).';
$string['settings:dynamicdiscovery_automatic'] = 'Automatic';
$string['settings:dynamicdiscovery_on'] = 'Always on';
$string['settings:dynamicdiscovery_off'] = 'Off';
$string['settings:openaiheading'] = 'OpenAI (or compatible)';
$string['settings:openai_api_type'] = 'API type';
$string['settings:openai_api_type_desc'] = 'Which OpenAI API to use for multi-turn chats. The Responses API maintains conversation state server-side. Chat Completions rebuilds the full message array on every request. Other providers silently fall back to Chat Completions.';
$string['settings:openai_api_type_responses'] = 'Responses API';
$string['settings:openai_api_type_completions'] = 'Chat Completions';
$string['settings:responses_endpoints'] = 'Custom Responses API endpoints';
$string['settings:responses_endpoints_desc'] = 'Overrides the Responses API endpoint when a hosted or proxied OpenAI or Azure OpenAI deployment serves it at a non-standard address. Leave empty to use the built-in defaults. One URL per line.';
$string['settings:geminiheading'] = 'Google Gemini';
$string['settings:gemini_api_type'] = 'API type';
$string['settings:gemini_api_type_desc'] = 'Which Gemini API to use for multi-turn chats. generateContent is the standard stateless endpoint. The Interactions API stores conversation state on Google\'s side.';
$string['settings:gemini_api_type_generatecontent'] = 'generateContent (stable)';
$string['settings:gemini_api_type_interactions'] = 'Interactions API (beta)';
$string['settings:gemini_builtin_google_search'] = 'Enable Google Search grounding';
$string['settings:gemini_builtin_google_search_desc'] = 'Lets Gemini ground responses on current web search results. Gemini 3+ models only.';
$string['settings:gemini_builtin_url_context'] = 'Enable URL Context tool';
$string['settings:gemini_builtin_url_context_desc'] = 'Lets Gemini read and analyse content from public URLs the user mentions. Gemini 3+ models only.';
$string['settings:gemini_builtin_code_execution'] = 'Enable Code Execution tool';
$string['settings:gemini_builtin_code_execution_desc'] = 'Lets Gemini write and run Python code in a sandbox. Gemini 3+ models only.';
$string['settings:anthropicheading'] = 'Anthropic (Claude)';
$string['settings:anthropic_web_fetch'] = 'Enable web fetch tool';
$string['settings:anthropic_web_fetch_desc'] = 'Lets Claude fetch external URLs server-side via Anthropic\'s web_fetch tool. Files on this Moodle site are handled separately via the read_file function.';

$string['configtitle'] = 'AI agent configuration';
$string['backtoconfig'] = 'Back to AI agent configuration';
$string['tabskills'] = 'Skills';
$string['tabplacements'] = 'Placements';
$string['tabfunctions'] = 'Functions';
$string['settingsgear'] = 'Settings';

$string['skillsinfo'] = 'A skill bundles specialised instructions for a particular job with a list of functions recommended for it. Use skills for common multi-step tasks where the AI agent tends to get confused or can\'t find the right functions, or for tasks specific to your organisation. The list of skills and their descriptions is always available to the AI agent, which loads a skill on its own whenever it decides one fits the user\'s request. A skill is only available to users who can access at least one of its main functions.';
$string['importskills'] = 'Import skills';
$string['importskills_help'] = 'Drag skill files here, or use the button.';
$string['choosefiles'] = 'Choose files...';
$string['addskill'] = 'Add skill';
$string['editskill'] = 'Edit skill';
$string['filters'] = 'Filters';
$string['skillname'] = 'Name';
$string['skillshortname'] = 'Short name';
$string['skilldescription'] = 'Description';
$string['skillinstructions'] = 'Instructions';
$string['skillmainfunctions'] = 'Main functions';
$string['skillotherfunctions'] = 'Other functions';
$string['enabled'] = 'Enabled';
$string['saveandpreview'] = 'Save and preview';
$string['saveandclose'] = 'Save and close';
$string['saveskill'] = 'Save skill';
$string['deleteskill'] = 'Delete skill';
$string['confirmdelskill'] = 'Delete this skill?';
$string['skillaihint'] = 'Instead of filling this in by hand, you can ask the AI agent to build the skill for you: it can find the right functions and draft the instructions. <strong>Open the AI agent.</strong>';

$string['placementsinfo'] = 'Placements let you offer agents tailored to a particular job. Each placement adds the AI agent button to pages whose URL matches one of its masks, and can start the chat with predefined initial skills and welcome text. If initial skills are set, users who have access to none of them won\'t see the button. The initial skills only set the starting point of the chat; the agent can still access all enabled functions, not just those of the skills.';
$string['addplacement'] = 'Add placement';
$string['editplacement'] = 'Edit placement';
$string['saveplacement'] = 'Save placement';
$string['deleteplacement'] = 'Delete placement';
$string['confirmdelplacement'] = 'Delete this placement?';
$string['placementname'] = 'Name';
$string['urlmasks'] = 'URL masks';
$string['buttonlocation'] = 'Button location';
$string['locationh1'] = 'Page heading (H1)';
$string['locationh2'] = 'Secondary heading (H2)';
$string['locationusermenu'] = 'User menu';
$string['locationfloating'] = 'Floating button';
$string['initialskills'] = 'Initial skills';
$string['noselection'] = 'No selection';
$string['customwelcome'] = 'Custom welcome text (optional)';

$string['functionsinfo'] = 'Not every web service function is a good fit for the AI agent (for example those built for AJAX or unauthenticated users). Core and standard functions are assigned a relevance score (1–10); functions scoring 5 or above are enabled by default. Functions from add-on plugins are not scored; their non-AJAX functions are enabled automatically. Functions may require specific capabilities; if a user lacks a required capability the function is unavailable to them.';
$string['functionname'] = 'Function name';
$string['functiontype'] = 'Type';
$string['relevancescore'] = 'Relevance score';
$string['capabilities'] = 'Capabilities';
$string['tags'] = 'Tags';
$string['typewrite'] = 'Write';
$string['typeread'] = 'Read';
$string['typeajax'] = 'AJAX';
$string['typenonauth'] = 'Non-auth';
$string['typebundled'] = 'Bundled';

$string['view:overlay'] = 'Overlay';
$string['view:sidebar'] = 'Sidebar';
$string['view:fullscreen'] = 'Full screen';

$string['welcome'] = 'Welcome, {$a}';
$string['welcomeback'] = 'Welcome back';
$string['welcometext'] = 'Ask the AI agent about your site, courses, users or content. It can read and, with your approval, make changes.';
$string['welcomebacktext'] = 'You have a recent chat. Pick up where you left off, or start a new one.';
$string['resumelast'] = 'Resume last chat';
$string['newchat'] = 'New chat';
$string['previouschats'] = 'Previous chats';
$string['deletechat'] = 'Delete chat';
$string['confirmdelchat'] = 'Delete this chat? This cannot be undone.';
$string['untitledchat'] = 'Untitled chat';
$string['nopreviouschats'] = 'No previous chats.';
$string['lastmessageago'] = 'Last message {$a} ago';
$string['typemessage'] = 'Type a message...';
$string['send'] = 'Send';
$string['help'] = 'Help';
$string['helpbody'] = '<p>The AI agent can read and modify content on this site on your behalf. Most actions ask for your approval before they run. You can switch the auto-approve mode in the composer to relax or tighten that.</p><p>Attach files by dragging and dropping, pasting, or clicking the paperclip icon. Then type your request and press <strong>Send</strong>. Press <strong>Enter</strong> to send, or <strong>Shift</strong> and <strong>Enter</strong> for a new line.</p><p>AI can make mistakes. Verify results.</p>';
$string['autoapprove'] = 'Auto-approve';
$string['autoapprove_heading'] = 'Auto-approve function calls';
$string['autoapprove_none'] = 'None';
$string['autoapprove_none_desc'] = 'Ask before every function call';
$string['autoapprove_readonly'] = 'Read-only';
$string['autoapprove_readonly_desc'] = 'Auto-approve reads, ask for writes';
$string['autoapprove_safe'] = 'Safe';
$string['autoapprove_safe_desc'] = 'Auto-approve actions that don\'t modify the site';
$string['autoapprove_all'] = 'All (use with caution)';
$string['autoapprove_all_desc'] = 'Skip every confirmation';
$string['approve'] = 'Approve';
$string['deny'] = 'Deny';
$string['functioncalldetails'] = 'Function call details';
$string['parameters'] = 'Parameters';
$string['result'] = 'Result';
$string['noprovider'] = 'No enabled AI provider is configured. A site administrator must enable an AI provider under Site administration → AI.';
$string['splitviewtip'] = 'Want to use this chat side by side with your site? Drag {$a} to a new window, or right-click it and use your browser\'s split view.';
$string['errorfilesize'] = 'That file is larger than the maximum allowed size ({$a} MB).';
$string['errornojs'] = 'The AI agent requires JavaScript.';
$string['required'] = 'Required';
$string['actions'] = 'Actions';
$string['search'] = 'Search';
$string['cancel'] = 'Cancel';
$string['close'] = 'Close';
$string['copy'] = 'Copy';
$string['thumbs_up'] = 'Good response';
$string['thumbs_down'] = 'Bad response';
$string['attach'] = 'Attach files';
$string['history'] = 'History';
$string['changelayout'] = 'Change layout';
$string['moreactions'] = 'More';
$string['more'] = 'More';
$string['remove'] = 'Remove';
$string['noconversation'] = 'No recent chat.';
$string['errorgeneric'] = 'Something went wrong. {$a}';
$string['errorfeedbackdisabled'] = 'Feedback is disabled';
$string['requestfailed'] = 'The request failed.';
$string['waiting'] = 'Working…';
$string['discoveryprompt'] = 'Function discovery is active. The tools you can call are a matched subset of the site catalog (plus search_tools). Use those functions for site actions. Do not invent Moodle UI workflows such as backup and restore when a listed function can do the job. Do not call search_tools again unless the listed functions cannot fulfill the request. If none of the listed functions fit, call search_tools with a clearer prompt, or tell the user that no matching function is available. Do not claim Moodle cannot do something that a listed function does.';
$string['matchedfunctions'] = 'Matched functions: {$a}';
$string['autoapprovelabel'] = 'Auto-approve: {$a}';
$string['ago_secs'] = '{$a} secs';
$string['ago_mins'] = '{$a->mins} mins {$a->secs} secs';
$string['ago_hours'] = '{$a} hours';
$string['testdriverreply'] = 'The test driver finished that request.';
$string['functiondenied'] = 'The user denied the function call.';
$string['functiontruncated'] = 'Function result exceeded the size limit and was discarded. Narrow the request.';
$string['taskcleanup'] = 'Clean up old AI agent chats and logs';

$string['eventconversationcreated'] = 'AI agent conversation created';
$string['eventfunctionexecuted'] = 'AI agent function executed';

$string['defaultsysteminstruction'] = 'You are a helpful assistant for a Moodle learning management system. Help the user manage their site by answering questions and performing administration tasks with the available tools.

Guidelines:
- Use Markdown formatting for readability.
- When you mention courses, users, or other Moodle entities, include direct links to them using the site URL provided below.
- Use the available tools proactively to look up information, search for entities, and perform requested actions. Chain multiple tools when needed.
- Site actions must use the functions listed as tools. Do not invent Moodle UI workflows (for example backup and restore) when a listed function can do the job.
- If no listed function matches, call search_tools with a clearer prompt, or say that no matching function is available. Do not claim Moodle cannot do something that a listed function does.
- Do not change your own roles, enrolments, or permissions, or make incidental account or permission changes.
- URLs in tool results that start with {siteurl}/webservice/pluginfile.php must be rewritten: replace {siteurl}/webservice/pluginfile.php with {siteurl}/pluginfile.php.
- For image URLs, also remove the "forcedownload=1" query parameter so images display inline.
- Be concise and helpful.
- Do not claim an action is done, or present a confirmation or summary of changes, until you have actually called the relevant tool.
- You cannot create, modify, or access files directly. You MUST use the provided tools for all file operations.
- When working with images from PDFs: use convert_file_to_image to render pages, then crop_image to extract specific regions.
- Do not hedge or second-guess yourself in the response (no "wait, ...").';
