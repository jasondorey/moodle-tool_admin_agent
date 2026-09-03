/**
 * Chat composer: textarea, send, attach, auto-approve.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ChangeEvent, ClipboardEvent, DragEvent, KeyboardEvent} from 'react';
import type {AutoApprove} from '../services/api';
import type {StringMap} from '../services/strings';
import {PixIcon} from './PixIcon';

type Props = {
    path: string;
    draft: string;
    files: File[];
    autoapprove: AutoApprove;
    autoLabel: string;
    showAutoMenu: boolean;
    busy: boolean;
    strings: StringMap;
    onDraft: (value: string) => void;
    onSend: () => void;
    onFiles: (files: FileList | File[]) => void;
    onRemoveFile: (index: number) => void;
    onToggleAuto: () => void;
    onSelectAuto: (mode: AutoApprove) => void;
    onHelp: () => void;
};

const AUTO_MODES: AutoApprove[] = ['none', 'readonly', 'safe', 'all'];

const AUTO_ICONS: Record<AutoApprove, string> = {
    none: 't/block',
    readonly: 't/hide',
    safe: 'safe',
    all: 'all',
};

/**
 * Message composer.
 */
export function Composer({
    path,
    draft,
    files,
    autoapprove,
    autoLabel,
    showAutoMenu,
    busy,
    strings,
    onDraft,
    onSend,
    onFiles,
    onRemoveFile,
    onToggleAuto,
    onSelectAuto,
    onHelp,
}: Props) {
    const onKey = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            onSend();
        }
    };
    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        if (event.dataTransfer.files?.length) {
            onFiles(event.dataTransfer.files);
        }
    };
    const onPaste = (event: ClipboardEvent<HTMLTextAreaElement>) => {
        const pasted = Array.from(event.clipboardData?.files || []);
        if (pasted.length) {
            event.preventDefault();
            onFiles(pasted);
        }
    };

    return (
        <div className="taa-composer" data-region="taa-composer" onDragOver={(e) => e.preventDefault()} onDrop={onDrop}>
            <div className="taa-context-row">
                <span className="taa-context">@ {path}</span>
            </div>
            <label className="visually-hidden" htmlFor="taa-input">
                {strings.typemessage}
            </label>
            <div className="d-flex">
                <textarea
                    id="taa-input"
                    data-act="input"
                    className="form-control flex-grow-1"
                    rows={2}
                    placeholder={strings.typemessage}
                    value={draft}
                    disabled={busy}
                    onChange={(event: ChangeEvent<HTMLTextAreaElement>) => onDraft(event.target.value)}
                    onKeyDown={onKey}
                    onPaste={onPaste}
                />
                <div className="d-flex flex-column">
                    <button
                        type="button"
                        className="btn btn-icon icon-no-margin ms-1 mt-auto"
                        data-act="send"
                        title={strings.send}
                        aria-label={strings.send}
                        disabled={busy}
                        onClick={onSend}
                    >
                        <PixIcon pix="i/sendmessage" />
                    </button>
                </div>
            </div>
            <div className="taa-composer-bar">
                <button
                    type="button"
                    className="btn btn-icon icon-no-margin"
                    data-act="attach"
                    title={strings.attach}
                    onClick={() => {
                        document.getElementById('taa-files')?.click();
                    }}
                >
                    <PixIcon pix="attach" />
                </button>
                <button type="button" className="taa-auto" data-act="auto" onClick={onToggleAuto}>
                    <PixIcon pix="i/settings" />
                    {autoLabel}
                </button>
                <button
                    type="button"
                    className="btn btn-icon icon-no-margin"
                    data-act="help"
                    title={strings.help}
                    onClick={onHelp}
                >
                    <PixIcon pix="e/help" />
                </button>
            </div>
            <div className="taa-file-list" data-act="filelist">
                {files.map((file, index) => (
                    <span key={file.name + index} className="taa-file-chip">
                        {file.name}{' '}
                        <button
                            type="button"
                            className="icon-no-margin"
                            title={strings.remove}
                            onClick={() => onRemoveFile(index)}
                        >
                            <PixIcon pix="e/cancel" />
                        </button>
                    </span>
                ))}
            </div>
            <input
                id="taa-files"
                type="file"
                multiple
                className="d-none"
                data-act="files"
                onChange={(event) => {
                    if (event.target.files) {
                        onFiles(event.target.files);
                    }
                    event.target.value = '';
                }}
            />
            {showAutoMenu ? (
                <div className="taa-auto-menu">
                    <div className="taa-auto-heading">{strings.autoapprove_heading}</div>
                    {AUTO_MODES.map((mode) => {
                        const labels: Record<AutoApprove, string> = {
                            none: strings.autoapprove_none,
                            readonly: strings.autoapprove_readonly,
                            safe: strings.autoapprove_safe,
                            all: strings.autoapprove_all,
                        };
                        const descs: Record<AutoApprove, string> = {
                            none: strings.autoapprove_none_desc,
                            readonly: strings.autoapprove_readonly_desc,
                            safe: strings.autoapprove_safe_desc,
                            all: strings.autoapprove_all_desc,
                        };
                        return (
                            <button
                                key={mode}
                                type="button"
                                data-mode={mode}
                                className={autoapprove === mode ? 'active' : ''}
                                onClick={() => onSelectAuto(mode)}
                            >
                                <span className="taa-auto-title">
                                    <PixIcon pix={AUTO_ICONS[mode]} />
                                    {labels[mode]}
                                </span>
                                <small>{descs[mode]}</small>
                            </button>
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
}
