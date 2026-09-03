/**
 * Web service wrappers for the admin agent chat.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {callAjax} from './ajax';

export type AutoApprove = 'none' | 'readonly' | 'safe' | 'all';

export type WidgetConfig = {
    firstname: string;
    userid: number;
    placementid: number;
    location: string;
    welcome: string;
    allowfeedback: boolean;
    maxfilesize: number;
    wwwroot: string;
    fullscreenurl: string;
    hasrecent: boolean;
    recent: {id: number; title: string; timemodified: number};
    viewoverlay: boolean;
    viewsidebar: boolean;
    viewfullscreen: boolean;
    dynamicdiscovery: boolean;
};

export type ChatMessage = {
    id: number;
    role: string;
    content: string;
    timecreated: number;
    feedback: string;
};

export type ToolCall = {
    id: number;
    messageid: number;
    callid: string;
    functionname: string;
    arguments: string;
    result: string;
    status: string;
    readwrite: string;
    pending: boolean;
    done: boolean;
    running: boolean;
};

export type Conversation = {
    id: number;
    title: string;
    layout: string;
    autoapprove: string;
    timemodified: number;
    status: string;
    messages: ChatMessage[];
    toolcalls: ToolCall[];
};

/**
 * Bootstrap payload for the floating widget.
 */
export function getWidgetConfig(path: string): Promise<WidgetConfig> {
    return callAjax<WidgetConfig>('tool_adminagent_get_widget_config', {path});
}

/**
 * Start a conversation.
 */
export function createConversation(placementid: number, layout: string): Promise<Conversation> {
    return callAjax<Conversation>('tool_adminagent_create_conversation', {placementid, layout});
}

/**
 * Load a conversation the current user owns.
 */
export function getConversation(conversationid: number): Promise<Conversation> {
    return callAjax<Conversation>('tool_adminagent_get_conversation', {conversationid});
}

/**
 * Persist overlay/sidebar layout.
 */
export function setLayout(conversationid: number, layout: string): Promise<{layout: string}> {
    return callAjax<{layout: string}>('tool_adminagent_set_layout', {conversationid, layout});
}

/**
 * Send a user message and run the agent loop.
 */
export function sendMessage(
    conversationid: number,
    message: string,
    pageurl: string,
    autoapprove: AutoApprove,
    attachments: string,
): Promise<Conversation> {
    return callAjax<Conversation>('tool_adminagent_send_message', {
        conversationid,
        message,
        pageurl,
        autoapprove,
        attachments,
    });
}

/**
 * Approve or deny a pending function call.
 */
export function resolveCall(conversationid: number, callid: number, approve: boolean): Promise<Conversation> {
    return callAjax<Conversation>('tool_adminagent_resolve_call', {conversationid, callid, approve});
}

/**
 * Store thumbs up/down on an assistant message.
 */
export function submitFeedback(messageid: number, rating: string): Promise<{id: number; rating: string}> {
    return callAjax<{id: number; rating: string}>('tool_adminagent_submit_feedback', {messageid, rating});
}

/**
 * Upload composer attachments to a user draft area.
 */
export function uploadFiles(
    conversationid: number,
    files: Array<{filename: string; filecontent: string}>,
): Promise<{itemid: number; files: Array<{filename: string; itemid: number; size: number}>}> {
    return callAjax('tool_adminagent_upload_files', {conversationid, files});
}

export type ConversationSummary = {
    id: number;
    title: string;
    timemodified: number;
    datedisplay: string;
};

/**
 * List chats the current user owns.
 */
export function listConversations(): Promise<{conversations: ConversationSummary[]}> {
    return callAjax('tool_adminagent_list_conversations', {});
}

/**
 * Delete a chat the current user owns.
 */
export function deleteConversation(conversationid: number): Promise<{id: number; deleted: boolean}> {
    return callAjax('tool_adminagent_delete_conversation', {conversationid});
}
