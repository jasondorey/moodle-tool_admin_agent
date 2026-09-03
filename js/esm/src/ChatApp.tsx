/**
 * @moodle/lms/tool_adminagent/ChatApp
 *
 * React chat shell for the site-admin AI agent.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {DsButton} from './components/Ds';
import {Composer} from './components/Composer';
import {HeaderMenu} from './components/HeaderMenu';
import {PixIcon} from './components/PixIcon';
import {Transcript} from './components/Transcript';
import {loadAmdModule} from './services/ajax';
import {
    createConversation,
    deleteConversation,
    getConversation,
    getWidgetConfig,
    listConversations,
    resolveCall,
    sendMessage,
    setLayout,
    submitFeedback,
    uploadFiles,
    type AutoApprove,
    type Conversation,
    type ConversationSummary,
    type ToolCall,
    type WidgetConfig,
} from './services/api';
import {readAsBase64} from './services/markdown';
import {withPending} from './services/pending';
import {formatAgo, getString, loadChatStrings, type StringMap} from './services/strings';

export type ChatAppProps = {
    path?: string;
    placementid?: number;
    location?: string;
    firstname?: string;
    startlayout?: string;
};

type Screen = 'welcome' | 'resume' | 'chat';

function showError(error: unknown, fallback: string): void {
    const err = (error && typeof error === 'object') ? error as Record<string, unknown> : {message: String(error || '')};
    const exception = err.exception as Record<string, unknown> | undefined;
    if (!err.message) {
        err.message = err.error || err.errorcode || exception?.message || exception?.error || fallback;
    }
    void loadAmdModule<{exception: (e: unknown) => void}>('core/notification').then((Notification) => {
        Notification.exception(err);
    }).catch(() => {
        window.console.error(err);
    });
}

function allowedLayouts(config: WidgetConfig): string[] {
    const all: string[] = [];
    if (config.viewoverlay) {
        all.push('overlay');
    }
    if (config.viewsidebar) {
        all.push('sidebar');
    }
    if (config.viewfullscreen) {
        all.push('fullscreen');
    }
    return all.length ? all : ['overlay'];
}

function parseCssColorToRgb(color: string): {r: number; g: number; b: number} | null {
    const c = color.trim();
    if (!c) {
        return null;
    }
    if (c.startsWith('#')) {
        const hex = c.slice(1);
        if (hex.length === 3) {
            return {
                r: parseInt(hex[0] + hex[0], 16),
                g: parseInt(hex[1] + hex[1], 16),
                b: parseInt(hex[2] + hex[2], 16),
            };
        }
        if (hex.length === 6) {
            return {
                r: parseInt(hex.slice(0, 2), 16),
                g: parseInt(hex.slice(2, 4), 16),
                b: parseInt(hex.slice(4, 6), 16),
            };
        }
        return null;
    }
    const m = c.match(/^rgba?\(\s*([0-9.]+)\s*,\s*([0-9.]+)\s*,\s*([0-9.]+)(?:\s*,\s*[0-9.]+\s*)?\)$/i);
    if (!m) {
        return null;
    }
    return {
        r: Math.round(Number(m[1])),
        g: Math.round(Number(m[2])),
        b: Math.round(Number(m[3])),
    };
}

function isDarkColor(color: string): boolean {
    const rgb = parseCssColorToRgb(color);
    if (!rgb) {
        return false;
    }
    // Relative luminance (WCAG) for dark vs light decision.
    const srgb = [rgb.r / 255, rgb.g / 255, rgb.b / 255].map((v) => {
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    const luminance = 0.2126 * srgb[0] + 0.7152 * srgb[1] + 0.0722 * srgb[2];
    return luminance < 0.5;
}

function emptyConversation(layout: string, autoapprove: string): Conversation {
    return {
        id: 0,
        title: '',
        layout,
        autoapprove,
        timemodified: Math.floor(Date.now() / 1000),
        status: 'open',
        messages: [],
        toolcalls: [],
    };
}

function withOptimisticUser(
    conv: Conversation,
    outgoing: string,
    titleHint: string,
    discover: boolean,
): Conversation {
    const msgid = -Date.now();
    const toolcalls = [...(conv.toolcalls || [])];
    if (discover) {
        toolcalls.push({
            id: msgid - 1,
            messageid: msgid,
            callid: 'optimistic_search',
            functionname: 'search_tools',
            arguments: '{}',
            result: '',
            status: 'running',
            readwrite: 'R',
            pending: false,
            done: false,
            running: true,
        });
    }
    return {
        ...conv,
        title: conv.title || titleHint || outgoing,
        messages: [
            ...(conv.messages || []),
            {
                id: msgid,
                role: 'user',
                content: outgoing,
                timecreated: Math.floor(Date.now() / 1000),
                feedback: '',
            },
        ],
        toolcalls,
    };
}

/**
 * Default export mounted by Moodle's {{#react}} helper.
 */
export default function ChatApp(rawProps: ChatAppProps) {
    const props = {
        path: rawProps.path || '',
        placementid: Number(rawProps.placementid || 0),
        location: rawProps.location || 'floating',
        firstname: rawProps.firstname || '',
        startlayout: rawProps.startlayout || '',
    };
    const [strings, setStrings] = useState<StringMap | null>(null);
    const [config, setConfig] = useState<WidgetConfig | null>(null);
    const [open, setOpen] = useState(props.startlayout === 'fullscreen');
    const [layout, setLayoutState] = useState(props.startlayout === 'fullscreen' ? 'fullscreen' : 'overlay');
    const [screen, setScreen] = useState<Screen>('welcome');
    const [conversation, setConversation] = useState<Conversation | null>(null);
    const [draft, setDraft] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [autoapprove, setAutoapprove] = useState<AutoApprove>('none');
    const [showAutoMenu, setShowAutoMenu] = useState(false);
    const [showLayouts, setShowLayouts] = useState(false);
    const [showHeaderMenu, setShowHeaderMenu] = useState(false);
    const [headerMenuView, setHeaderMenuView] = useState<'actions' | 'previous'>('actions');
    const [chats, setChats] = useState<ConversationSummary[]>([]);
    const [helpOpen, setHelpOpen] = useState(false);
    const [details, setDetails] = useState<ToolCall | null>(null);
    const [busy, setBusy] = useState(false);
    const [ago, setAgo] = useState('');
    const [welcomeTitle, setWelcomeTitle] = useState('');
    const bodyRef = useRef<HTMLDivElement | null>(null);
    const opening = useRef(false);

    const [primaryIsDark, setPrimaryIsDark] = useState(true);

    useEffect(() => {
        let cancelled = false;
        Promise.all([loadChatStrings(), getWidgetConfig(props.path)])
            .then(([loaded, widget]) => {
                if (cancelled) {
                    return;
                }
                setStrings(loaded);
                setConfig(widget);
                const layouts = allowedLayouts(widget);
                if (props.startlayout !== 'fullscreen' && layouts.includes('overlay')) {
                    setLayoutState('overlay');
                } else if (props.startlayout !== 'fullscreen') {
                    setLayoutState(layouts[0]);
                }
                const name = widget.firstname || props.firstname;
                void getString('welcome', name).then(setWelcomeTitle);
            })
            .catch((error) => showError(error, ''));
        return () => {
            cancelled = true;
        };
    }, [props.path, props.firstname, props.startlayout]);

    useEffect(() => {
        if (!config) {
            return;
        }
        const loc = props.location || config.location;
        const btn = document.querySelector('.tool-adminagent-heading-btn') as HTMLElement | null;
        if (!btn) {
            return;
        }
        if (loc === 'h1' || loc === 'h2') {
            const heading = document.querySelector(loc);
            if (heading) {
                heading.appendChild(btn);
                btn.classList.remove('d-none');
            }
        } else if (loc === 'usermenu') {
            const menu = document.querySelector('.usermenu, .user-menu');
            if (menu) {
                menu.prepend(btn);
                btn.classList.remove('d-none');
            } else {
                btn.classList.remove('d-none');
                btn.classList.add('tool-adminagent-fab');
            }
        }
    }, [config, props.location]);

    useEffect(() => {
        const compute = () => {
            const style = window.getComputedStyle(document.documentElement);
            const primary = style.getPropertyValue('--bs-primary').trim() || style.getPropertyValue('--primary').trim();
            if (!primary) {
                setPrimaryIsDark(true);
                return;
            }
            setPrimaryIsDark(isDarkColor(primary));
        };
        compute();
        window.addEventListener('resize', compute);
        return () => window.removeEventListener('resize', compute);
    }, []);

    const openPanel = useCallback(async () => {
        if (opening.current) {
            return;
        }
        opening.current = true;
        try {
            await withPending('tool_adminagent/chat:open', async () => {
                let widget = config;
                if (!widget) {
                    widget = await getWidgetConfig(props.path);
                    setConfig(widget);
                }
                setOpen(true);
                if (conversation) {
                    setScreen('chat');
                    return;
                }
                if (widget.hasrecent) {
                    const elapsed = await formatAgo(widget.recent.timemodified);
                    setAgo(elapsed);
                    setScreen('resume');
                } else {
                    setScreen('welcome');
                }
            });
        } catch (error) {
            showError(error, strings?.requestfailed || '');
        } finally {
            opening.current = false;
        }
    }, [config, conversation, props.path, strings]);

    useEffect(() => {
        const onClick = (event: MouseEvent) => {
            const target = event.target as HTMLElement | null;
            if (!target?.closest('[data-action="open-chat"]')) {
                return;
            }
            event.preventDefault();
            void openPanel();
        };
        document.addEventListener('click', onClick);
        return () => document.removeEventListener('click', onClick);
    }, [openPanel]);

    const didAutoOpen = useRef(false);

    useEffect(() => {
        if (props.startlayout === 'fullscreen' && config && strings && !didAutoOpen.current) {
            didAutoOpen.current = true;
            void openPanel();
        }
    }, [props.startlayout, config, strings, openPanel]);

    useEffect(() => {
        if (layout === 'sidebar' && open) {
            document.body.classList.add('tool-adminagent-sidebar-open');
        } else {
            document.body.classList.remove('tool-adminagent-sidebar-open');
        }
        return () => document.body.classList.remove('tool-adminagent-sidebar-open');
    }, [layout, open]);

    useEffect(() => {
        if (bodyRef.current) {
            bodyRef.current.scrollTop = bodyRef.current.scrollHeight;
        }
    }, [conversation, screen, busy]);

    const autoLabel = useMemo(() => {
        if (!strings) {
            return '';
        }
        const labels: Record<AutoApprove, string> = {
            none: strings.autoapprove_none,
            readonly: strings.autoapprove_readonly,
            safe: strings.autoapprove_safe,
            all: strings.autoapprove_all,
        };
        return strings.autoapprovelabel.replace('{$a}', labels[autoapprove]);
    }, [strings, autoapprove]);

    const closeHeaderMenu = () => {
        setShowHeaderMenu(false);
        setHeaderMenuView('actions');
    };

    const close = () => {
        setOpen(false);
        setShowAutoMenu(false);
        setShowLayouts(false);
        closeHeaderMenu();
        setHelpOpen(false);
        setDetails(null);
    };

    const changeLayout = async (next: string) => {
        closeHeaderMenu();
        setShowLayouts(false);
        if (next === 'fullscreen' && config) {
            window.location.href = config.fullscreenurl;
            return;
        }
        setLayoutState(next);
        if (conversation) {
            await setLayout(conversation.id, next);
        }
    };

    const ensureConversation = async (): Promise<Conversation> => {
        if (conversation) {
            return conversation;
        }
        return createConversation(props.placementid || config?.placementid || 0, layout);
    };

    const onNewChat = async () => {
        const created = await createConversation(props.placementid || config?.placementid || 0, layout);
        setConversation(created);
        setFiles([]);
        setDraft('');
        setScreen('welcome');
    };

    const onResume = async (id: number) => {
        const loaded = await getConversation(id);
        setConversation(loaded);
        setScreen('chat');
    };

    const toggleHeaderMenu = () => {
        setShowLayouts(false);
        setShowAutoMenu(false);
        setShowHeaderMenu((open) => !open);
        setHeaderMenuView('actions');
    };

    const rememberRecent = (rows: ConversationSummary[]) => {
        setChats(rows);
        setConfig((widget) => {
            if (!widget) {
                return widget;
            }
            const next = rows[0];
            return {
                ...widget,
                hasrecent: rows.length > 0,
                recent: next
                    ? {id: next.id, title: next.title, timemodified: next.timemodified}
                    : {id: 0, title: '', timemodified: 0},
            };
        });
    };

    const clearCurrentChat = () => {
        setConversation(null);
        setFiles([]);
        setDraft('');
        setScreen('welcome');
    };

    const onHeaderNewChat = async () => {
        closeHeaderMenu();
        await onNewChat();
    };

    const onHeaderPrevious = async () => {
        try {
            const result = await listConversations();
            setChats(result.conversations || []);
            setHeaderMenuView('previous');
        } catch (error) {
            showError(error, strings?.requestfailed || '');
        }
    };

    const onDeleteCurrent = async () => {
        const id = conversation?.id || 0;
        if (!id || !strings) {
            return;
        }
        if (!window.confirm(strings.confirmdelchat)) {
            return;
        }
        try {
            await deleteConversation(id);
            const result = await listConversations();
            rememberRecent(result.conversations || []);
            clearCurrentChat();
            closeHeaderMenu();
        } catch (error) {
            showError(error, strings.requestfailed || '');
        }
    };

    const onSelectChat = async (id: number) => {
        closeHeaderMenu();
        if (id === (conversation?.id || 0)) {
            return;
        }
        await onResume(id);
    };

    const onDeleteListedChat = async (id: number) => {
        if (!strings || !window.confirm(strings.confirmdelchat)) {
            return;
        }
        try {
            await deleteConversation(id);
            const result = await listConversations();
            rememberRecent(result.conversations || []);
            if (id === (conversation?.id || 0)) {
                clearCurrentChat();
                closeHeaderMenu();
            }
        } catch (error) {
            showError(error, strings.requestfailed || '');
        }
    };

    const addFiles = async (list: FileList | File[]) => {
        if (!config || !strings) {
            return;
        }
        const max = (config.maxfilesize || 20) * 1024 * 1024;
        const accepted: File[] = [];
        for (const file of Array.from(list)) {
            if (file.size > max) {
                const message = await getString('errorfilesize', config.maxfilesize);
                void loadAmdModule<{addNotification: (n: {type: string; message: string}) => void}>('core/notification')
                    .then((Notification) => Notification.addNotification({type: 'error', message}));
                continue;
            }
            accepted.push(file);
        }
        if (accepted.length) {
            setFiles((current) => current.concat(accepted));
        }
    };

    const onSend = async () => {
        const text = draft.trim();
        if (busy || (!text && !files.length)) {
            return;
        }
        const snapshot = conversation;
        const queued = files;
        const outgoing = queued.length
            ? (text ? (text + '\n\n[Attached files: ' + queued.map((file) => file.name).join(', ') + ']')
                : ('[Attached files: ' + queued.map((file) => file.name).join(', ') + ']'))
            : text;
        setDraft('');
        setFiles([]);
        setShowAutoMenu(false);
        setBusy(true);
        const alreadysearched = !!(snapshot?.toolcalls || []).some((row) => row.functionname === 'search_tools');
        const discover = config?.dynamicdiscovery !== false && !alreadysearched;
        setConversation(withOptimisticUser(
            snapshot || emptyConversation(layout, autoapprove),
            outgoing,
            text,
            discover,
        ));
        setScreen('chat');
        try {
            await withPending('tool_adminagent/chat:send', async () => {
                const conv = await ensureConversation();
                if (!snapshot) {
                    setConversation(withOptimisticUser(conv, outgoing, text, discover));
                }
                let attachments: string[] = [];
                if (queued.length) {
                    const payload = [];
                    for (const file of queued) {
                        payload.push({filename: file.name, filecontent: await readAsBase64(file)});
                    }
                    const uploaded = await uploadFiles(conv.id, payload);
                    attachments = uploaded.files.map((file) => file.filename);
                }
                const updated = await sendMessage(
                    conv.id,
                    text,
                    props.path || window.location.pathname,
                    autoapprove,
                    JSON.stringify(attachments),
                );
                setConversation(updated);
            });
        } catch (error) {
            setConversation(snapshot);
            setDraft(text);
            setFiles(queued);
            if (!snapshot) {
                setScreen('welcome');
            }
            showError(error, strings?.requestfailed || '');
        } finally {
            setBusy(false);
        }
    };

    const onApprove = async (callid: number, approve: boolean) => {
        if (!conversation) {
            return;
        }
        const previous = conversation;
        setBusy(true);
        setDetails(null);
        setConversation({
            ...conversation,
            toolcalls: (conversation.toolcalls || []).map((row) =>
                row.id === callid
                    ? {...row, pending: false, running: true, status: 'running'}
                    : row,
            ),
        });
        try {
            await withPending('tool_adminagent/chat:resolve', async () => {
                const updated = await resolveCall(previous.id, callid, approve);
                setConversation(updated);
                setScreen('chat');
            });
        } catch (error) {
            setConversation(previous);
            showError(error, strings?.requestfailed || '');
        } finally {
            setBusy(false);
        }
    };

    const onFeedback = async (messageid: number, rating: string) => {
        if (!conversation) {
            return;
        }
        try {
            await submitFeedback(messageid, rating);
            setConversation({
                ...conversation,
                messages: conversation.messages.map((message) =>
                    message.id === messageid ? {...message, feedback: rating} : message,
                ),
            });
        } catch (error) {
            showError(error, strings?.requestfailed || '');
        }
    };

    if (!open || !strings || !config) {
        return null;
    }

    const layouts = allowedLayouts(config);
    const lastAgo = strings.lastmessageago.replace('{$a}', ago);
    const title = 'Admin Agent';

    return (
        <div
            className={'tool-adminagent-panel layout-' + layout + (busy ? ' taa-busy' : '')}
            data-region="tool-adminagent-panel"
        >
            <div
                className="taa-header"
                style={{
                    background: 'var(--bs-primary)',
                    color: primaryIsDark ? '#fff' : '#000',
                }}
            >
                <span className="taa-title">{title}</span>
                <span className="taa-actions">
                    <HeaderMenu
                        open={showHeaderMenu}
                        view={headerMenuView}
                        strings={strings}
                        currentid={conversation?.id || 0}
                        chats={chats}
                        onToggle={toggleHeaderMenu}
                        onClose={closeHeaderMenu}
                        onNewChat={() => void onHeaderNewChat()}
                        onPrevious={() => void onHeaderPrevious()}
                        onDeleteCurrent={() => void onDeleteCurrent()}
                        onSelectChat={(id) => void onSelectChat(id)}
                        onDeleteChat={(id) => void onDeleteListedChat(id)}
                    />
                    <button
                        type="button"
                        className="icon-no-margin"
                        data-act="layout"
                        title={strings.changelayout}
                        onClick={() => {
                            closeHeaderMenu();
                            setShowLayouts((value) => !value);
                        }}
                    >
                        <PixIcon pix="layout" />
                    </button>
                    <button
                        type="button"
                        className="icon-no-margin"
                        data-act="close"
                        title={strings.close}
                        onClick={close}
                    >
                        <PixIcon pix="e/cancel" />
                    </button>
                </span>
            </div>
            {showLayouts ? (
                <div className="taa-layout-menu">
                    {layouts.map((name) => (
                        <button key={name} type="button" data-layout={name} onClick={() => void changeLayout(name)}>
                            {name === 'overlay' ? strings['view:overlay'] : null}
                            {name === 'sidebar' ? strings['view:sidebar'] : null}
                            {name === 'fullscreen' ? strings['view:fullscreen'] : null}
                        </button>
                    ))}
                </div>
            ) : null}
            <div className="taa-body" data-region="taa-body" ref={bodyRef}>
                {screen === 'resume' && config.recent ? (
                    <div className="taa-welcome">
                        <div className="taa-icon">
                            <PixIcon pix="t/messages" className="icon-size-5" />
                        </div>
                        <h3>{strings.welcomeback}</h3>
                        <p>{strings.welcomebacktext}</p>
                        <div className="taa-recent-card">
                            <strong>{config.recent.title || ''}</strong>
                            <div className="text-muted">{lastAgo}</div>
                        </div>
                        <DsButton
                            variant="primary"
                            className="w-100 mb-2"
                            onClick={() => void onResume(config.recent.id)}
                        >
                            {strings.resumelast}
                        </DsButton>
                        <DsButton className="w-100" onClick={() => void onNewChat()}>
                            {strings.newchat}
                        </DsButton>
                    </div>
                ) : null}
                {screen === 'welcome' ? (
                    <div className="taa-welcome">
                        <div className="taa-icon">
                            <PixIcon pix="t/messages" className="icon-size-5" />
                        </div>
                        <h3>{welcomeTitle || strings.welcome.replace('{$a}', config.firstname || props.firstname)}</h3>
                        <p>{config.welcome || strings.welcometext}</p>
                        <div className="taa-context">@ {props.path}</div>
                    </div>
                ) : null}
                {screen === 'chat' && conversation ? (
                    <Transcript
                        messages={conversation.messages || []}
                        toolcalls={conversation.toolcalls || []}
                        allowfeedback={!!config.allowfeedback}
                        strings={strings}
                        details={details}
                        waiting={busy && !(conversation.toolcalls || []).some((row) => row.running)}
                        onCopy={(content) => void navigator.clipboard.writeText(content || '')}
                        onFeedback={(id, rating) => void onFeedback(id, rating)}
                        onApprove={(id, approve) => void onApprove(id, approve)}
                        onShowDetails={setDetails}
                    />
                ) : null}
            </div>
            {screen !== 'resume' ? (
                <Composer
                    path={props.path}
                    draft={draft}
                    files={files}
                    autoapprove={autoapprove}
                    autoLabel={autoLabel}
                    showAutoMenu={showAutoMenu}
                    busy={busy}
                    strings={strings}
                    onDraft={setDraft}
                    onSend={() => void onSend()}
                    onFiles={(list) => void addFiles(list)}
                    onRemoveFile={(index) => setFiles((current) => current.filter((_, i) => i !== index))}
                    onToggleAuto={() => setShowAutoMenu((value) => !value)}
                    onSelectAuto={(mode) => {
                        setAutoapprove(mode);
                        setShowAutoMenu(false);
                    }}
                    onHelp={() => setHelpOpen(true)}
                />
            ) : null}
            {helpOpen ? (
                <div className="taa-modal" role="dialog" aria-labelledby="taa-help-title">
                    <div className="taa-modal-card">
                        <h3 id="taa-help-title">{strings.help}</h3>
                        <div dangerouslySetInnerHTML={{__html: strings.helpbody}} />
                        <DsButton variant="primary" onClick={() => setHelpOpen(false)}>
                            {strings.close}
                        </DsButton>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
