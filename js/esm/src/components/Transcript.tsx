/**
 * Conversation transcript: messages, function calls, details.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Fragment} from 'react';
import {DsButton} from './Ds';
import {PixIcon} from './PixIcon';
import {mdLite, prettyJson} from '../services/markdown';
import type {ChatMessage, ToolCall} from '../services/api';
import type {StringMap} from '../services/strings';

type Props = {
    messages: ChatMessage[];
    toolcalls: ToolCall[];
    allowfeedback: boolean;
    strings: StringMap;
    details: ToolCall | null;
    onCopy: (content: string) => void;
    onFeedback: (messageid: number, rating: string) => void;
    onApprove: (callid: number, approve: boolean) => void;
    onShowDetails: (row: ToolCall | null) => void;
    waiting: boolean;
};

function statusPix(row: ToolCall): string {
    if (row.pending) {
        return 'i/questions';
    }
    if (row.status === 'error' || row.status === 'denied') {
        return 'e/cancel';
    }
    return 'i/checked';
}

function CallRow({
    row,
    strings,
    onApprove,
    onShowDetails,
}: {
    row: ToolCall;
    strings: StringMap;
    onApprove: (callid: number, approve: boolean) => void;
    onShowDetails: (row: ToolCall) => void;
}) {
    const rw = row.readwrite === 'W' ? 'W' : 'R';
    const running = !!row.running || row.status === 'running';
    return (
        <div className={'taa-call taa-call-' + (running ? 'running' : row.status)} data-callid={row.id}>
            {running ? (
                <PixIcon pix="i/loading" className="taa-call-status" />
            ) : (
                <span className="taa-call-status">
                    <PixIcon pix={statusPix(row)} />
                </span>
            )}
            <span className={'taa-call-rw taa-rw-' + rw}>{rw}</span>
            <code>{row.functionname}</code>
            <button
                type="button"
                className="taa-call-more icon-no-margin"
                title={strings.moreactions}
                onClick={() => onShowDetails(row)}
            >
                <PixIcon pix="i/moremenu" />
            </button>
            {row.pending && !running ? (
                <div className="taa-call-actions">
                    <DsButton variant="primary" size="sm" onClick={() => onApprove(row.id, true)}>
                        {strings.approve}
                    </DsButton>{' '}
                    <DsButton size="sm" onClick={() => onApprove(row.id, false)}>
                        {strings.deny}
                    </DsButton>
                </div>
            ) : null}
        </div>
    );
}

/**
 * Message list plus leftover tool calls.
 */
export function Transcript({
    messages,
    toolcalls,
    allowfeedback,
    strings,
    details,
    onCopy,
    onFeedback,
    onApprove,
    onShowDetails,
    waiting,
}: Props) {
    const callsByMsg: Record<number, ToolCall[]> = {};
    (toolcalls || []).forEach((row) => {
        const key = row.messageid || 0;
        (callsByMsg[key] = callsByMsg[key] || []).push(row);
    });
    const used: Record<number, boolean> = {};
    messages.forEach((message) => {
        (callsByMsg[message.id] || []).forEach((row) => {
            used[row.id] = true;
        });
    });
    (toolcalls || []).forEach((row) => {
        if (used[row.id]) {
            return;
        }
        let attach: number | null = null;
        messages.forEach((message) => {
            if (!row.messageid || message.id <= row.messageid) {
                attach = message.id;
            }
        });
        if (attach === null && messages.length) {
            attach = messages[messages.length - 1].id;
        }
        if (attach !== null) {
            (callsByMsg[attach] = callsByMsg[attach] || []).push(row);
            used[row.id] = true;
        }
    });
    const leftover = (toolcalls || []).filter((row) => !used[row.id]);

    return (
        <>
            {messages.map((message) => (
                <Fragment key={message.id}>
                    {message.content ? (
                        <div className={'taa-msg taa-msg-' + message.role} data-msgid={message.id}>
                            <div dangerouslySetInnerHTML={{__html: mdLite(message.content)}} />
                            {message.role === 'assistant' ? (
                                <div className="taa-msg-tools">
                                    <button type="button" className="taa-copy icon-no-margin" title={strings.copy} onClick={() => onCopy(message.content)}>
                                        <PixIcon pix="t/copy" />
                                    </button>
                                    {allowfeedback ? (
                                        <>
                                            <button
                                                type="button"
                                                className={'taa-fb icon-no-margin' + (message.feedback === 'up' ? ' active' : '')}
                                                title={strings.thumbs_up}
                                                onClick={() => onFeedback(message.id, 'up')}
                                            >
                                                <PixIcon pix="t/approve" />
                                            </button>
                                            <button
                                                type="button"
                                                className={'taa-fb icon-no-margin' + (message.feedback === 'down' ? ' active' : '')}
                                                title={strings.thumbs_down}
                                                onClick={() => onFeedback(message.id, 'down')}
                                            >
                                                <PixIcon pix="thumbsdown" />
                                            </button>
                                        </>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                    {(callsByMsg[message.id] || []).map((row) => (
                        <CallRow
                            key={row.id}
                            row={row}
                            strings={strings}
                            onApprove={onApprove}
                            onShowDetails={onShowDetails}
                        />
                    ))}
                </Fragment>
            ))}
            {leftover.map((row) => (
                <CallRow
                    key={row.id}
                    row={row}
                    strings={strings}
                    onApprove={onApprove}
                    onShowDetails={onShowDetails}
                />
            ))}
            {waiting ? (
                <div className="taa-msg-waiting" aria-live="polite" aria-label={strings.waiting}>
                    <span className="taa-waiting-dots" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </span>
                </div>
            ) : null}
            {details ? (
                <div className="taa-modal" role="dialog" aria-labelledby="taa-call-title">
                    <div className="taa-modal-card">
                        <h3 id="taa-call-title">{strings.functioncalldetails}</h3>
                        <p>
                            <strong>{details.functionname}</strong> ({details.readwrite})
                        </p>
                        <h5>{strings.parameters}</h5>
                        <pre>{prettyJson(details.arguments)}</pre>
                        {details.result ? (
                            <>
                                <h5>{strings.result}</h5>
                                <pre>{prettyJson(details.result)}</pre>
                            </>
                        ) : null}
                        {details.pending ? (
                            <p>
                                <DsButton variant="primary" onClick={() => onApprove(details.id, true)}>
                                    {strings.approve}
                                </DsButton>{' '}
                                <DsButton onClick={() => onApprove(details.id, false)}>{strings.deny}</DsButton>
                            </p>
                        ) : null}
                        <DsButton variant="secondary" onClick={() => onShowDetails(null)}>
                            {strings.close}
                        </DsButton>
                    </div>
                </div>
            ) : null}
        </>
    );
}
