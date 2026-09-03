/**
 * Header ellipsis: new chat, previous chats, delete chat.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef} from 'react';
import {PixIcon} from './PixIcon';
import type {ConversationSummary} from '../services/api';
import type {StringMap} from '../services/strings';

type View = 'actions' | 'previous';

type Props = {
    open: boolean;
    view: View;
    strings: StringMap;
    currentid: number;
    chats: ConversationSummary[];
    onToggle: () => void;
    onClose: () => void;
    onNewChat: () => void;
    onPrevious: () => void;
    onDeleteCurrent: () => void;
    onSelectChat: (id: number) => void;
    onDeleteChat: (id: number) => void;
};

/**
 * More-actions dropdown for the chat header.
 */
export function HeaderMenu({
    open,
    view,
    strings,
    currentid,
    chats,
    onToggle,
    onClose,
    onNewChat,
    onPrevious,
    onDeleteCurrent,
    onSelectChat,
    onDeleteChat,
}: Props) {
    const wrapRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        const onDoc = (event: MouseEvent) => {
            const target = event.target as Node | null;
            if (wrapRef.current && target && !wrapRef.current.contains(target)) {
                onClose();
            }
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('mousedown', onDoc);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDoc);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, onClose]);

    const label = (row: ConversationSummary) => row.title.trim() || strings.untitledchat;
    const canDelete = currentid > 0;

    return (
        <div className="taa-header-menu-wrap" data-region="taa-header-menu" ref={wrapRef}>
            <button
                type="button"
                className={'icon-no-margin' + (open ? ' active' : '')}
                data-act="menu"
                title={strings.moreactions}
                aria-expanded={open}
                aria-haspopup="menu"
                onClick={onToggle}
            >
                <PixIcon pix="i/moremenu" />
            </button>
            {open ? (
                <div className={'taa-header-menu' + (view === 'previous' ? ' previous' : '')} role="menu">
                    {view === 'actions' ? (
                        <>
                            <button type="button" className="taa-header-menu-item" role="menuitem" onClick={onNewChat}>
                                <PixIcon pix="i/addblock" />
                                {strings.newchat}
                            </button>
                            <button type="button" className="taa-header-menu-item" role="menuitem" onClick={onPrevious}>
                                <PixIcon pix="t/log" />
                                {strings.previouschats}
                            </button>
                            <div className="taa-header-menu-sep"></div>
                            <button
                                type="button"
                                className="taa-header-menu-item danger"
                                role="menuitem"
                                disabled={!canDelete}
                                onClick={onDeleteCurrent}
                            >
                                <PixIcon pix="t/delete" />
                                {strings.deletechat}
                            </button>
                        </>
                    ) : (
                        <>
                            <div className="taa-header-menu-heading">{strings.previouschats}</div>
                            {chats.length ? chats.map((row) => {
                                const current = row.id === currentid;
                                return (
                                    <div
                                        key={row.id}
                                        className={'taa-header-menu-chat' + (current ? ' current' : '')}
                                    >
                                        <button
                                            type="button"
                                            className="taa-header-menu-chat-main"
                                            onClick={() => onSelectChat(row.id)}
                                        >
                                            {current ? (
                                                <span className="taa-chat-check">
                                                    <PixIcon pix="i/checked" />
                                                </span>
                                            ) : null}
                                            <span className="taa-chat-title">{label(row)}</span>
                                            {current ? null : (
                                                <span className="taa-chat-date">{row.datedisplay}</span>
                                            )}
                                        </button>
                                        {current ? (
                                            <button
                                                type="button"
                                                className="taa-chat-del icon-no-margin"
                                                title={strings.deletechat}
                                                onClick={() => onDeleteChat(row.id)}
                                            >
                                                <PixIcon pix="t/delete" />
                                            </button>
                                        ) : null}
                                    </div>
                                );
                            }) : (
                                <div className="taa-header-menu-empty">{strings.nopreviouschats}</div>
                            )}
                        </>
                    )}
                </div>
            ) : null}
        </div>
    );
}
