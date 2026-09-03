/**
 * Lang string loading for the chat widget.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {loadAmdModule} from './ajax';

export const STRING_KEYS = [
    'pluginname',
    'welcome',
    'welcometext',
    'welcomeback',
    'welcomebacktext',
    'resumelast',
    'newchat',
    'previouschats',
    'deletechat',
    'confirmdelchat',
    'untitledchat',
    'nopreviouschats',
    'lastmessageago',
    'typemessage',
    'send',
    'help',
    'helpbody',
    'autoapprovelabel',
    'autoapprove_heading',
    'autoapprove_none',
    'autoapprove_none_desc',
    'autoapprove_readonly',
    'autoapprove_readonly_desc',
    'autoapprove_safe',
    'autoapprove_safe_desc',
    'autoapprove_all',
    'autoapprove_all_desc',
    'approve',
    'deny',
    'functioncalldetails',
    'parameters',
    'result',
    'copy',
    'thumbs_up',
    'thumbs_down',
    'attach',
    'history',
    'changelayout',
    'moreactions',
    'close',
    'remove',
    'errorfilesize',
    'requestfailed',
    'waiting',
    'view:overlay',
    'view:sidebar',
    'view:fullscreen',
    'ago_secs',
    'ago_mins',
    'ago_hours',
] as const;

export type StringKey = (typeof STRING_KEYS)[number];
export type StringMap = Record<StringKey, string>;

type StrModule = {
    get_strings: (reqs: {key: string; component: string}[]) => Promise<string[]>;
    get_string: (key: string, component: string, param?: unknown) => Promise<string>;
};

let strModule: StrModule | null = null;

/**
 * core/str AMD module.
 */
export async function getStrModule(): Promise<StrModule> {
    if (!strModule) {
        strModule = await loadAmdModule<StrModule>('core/str');
    }
    return strModule;
}

/**
 * Load the chat string pack.
 */
export async function loadChatStrings(): Promise<StringMap> {
    const str = await getStrModule();
    const values = await str.get_strings(
        STRING_KEYS.map((key) => ({key, component: 'tool_adminagent'})),
    );
    const map = {} as StringMap;
    STRING_KEYS.forEach((key, index) => {
        map[key] = values[index];
    });
    return map;
}

/**
 * One Moodle lang string, with optional {$a}.
 */
export async function getString(key: string, param?: unknown): Promise<string> {
    const str = await getStrModule();
    return str.get_string(key, 'tool_adminagent', param);
}

/**
 * Human-readable elapsed time.
 */
export async function formatAgo(ts: number): Promise<string> {
    const secs = Math.max(1, Math.floor(Date.now() / 1000 - ts));
    if (secs < 60) {
        return getString('ago_secs', secs);
    }
    if (secs < 3600) {
        return getString('ago_mins', {
            mins: Math.floor(secs / 60),
            secs: secs % 60,
        });
    }
    return getString('ago_hours', Math.floor(secs / 3600));
}
