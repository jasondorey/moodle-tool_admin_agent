/**
 * Moodle pix / Font Awesome icon (Boost markup).
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Pix name to Font Awesome classes (Moodle 5.2 Boost map, plus plugin glyphs).
 */
export const PIX_FA: Record<string, string> = {
    't/message': 'fa-message',
    't/messages': 'fa-comments',
    't/log': 'fa-history',
    't/add': 'fa-plus',
    'i/menu': 'fa-ellipsis-vertical',
    'i/moremenu': 'fa-ellipsis',
    'e/cancel': 'fa-xmark',
    't/sendmessage': 'fa-regular fa-paper-plane',
    'i/sendmessage': 'fa-regular fa-paper-plane',
    'e/help': 'fa-circle-question',
    't/block': 'fa-ban',
    't/hide': 'fa-regular fa-eye',
    'i/questions': 'fa-question',
    'i/checked': 'fa-check',
    't/approve': 'fa-thumbs-up',
    'i/loading': 'fa-spinner fa-spin',
    'i/addblock': 'fa-regular fa-square-plus',
    't/delete': 'fa-trash-can',
    attach: 'fa-paperclip',
    layout: 'fa-table-columns',
    safe: 'fa-shield-halved',
    all: 'fa-bolt',
    thumbsdown: 'fa-thumbs-down',
    'comment-nodes': 'fa-comment-nodes',
};

type Props = {
    pix: string;
    title?: string;
    className?: string;
};

/**
 * Render the same <i class="icon fa fa-fw …"> markup Boost uses for pix icons.
 */
export function PixIcon({pix, title, className = ''}: Props) {
    const mapped = PIX_FA[pix] || pix;
    const classes = ['icon', 'fa', 'fa-fw', mapped, className].filter(Boolean).join(' ');
    if (title) {
        return <i className={classes} title={title} aria-label={title}></i>;
    }
    return <i className={classes} aria-hidden="true"></i>;
}
