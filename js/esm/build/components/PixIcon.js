import{jsx as t}from"react/jsx-runtime";var n={"t/message":"fa-message","t/messages":"fa-comments","t/log":"fa-history","t/add":"fa-plus","i/menu":"fa-ellipsis-vertical","i/moremenu":"fa-ellipsis","e/cancel":"fa-xmark","t/sendmessage":"fa-regular fa-paper-plane","i/sendmessage":"fa-regular fa-paper-plane","e/help":"fa-circle-question","t/block":"fa-ban","t/hide":"fa-regular fa-eye","i/questions":"fa-question","i/checked":"fa-check","t/approve":"fa-thumbs-up","i/loading":"fa-spinner fa-spin","i/addblock":"fa-regular fa-square-plus","t/delete":"fa-trash-can",attach:"fa-paperclip",layout:"fa-table-columns",safe:"fa-shield-halved",all:"fa-bolt",thumbsdown:"fa-thumbs-down","comment-nodes":"fa-comment-nodes"};function f({pix:e,title:a,className:i=""}){let s=["icon","fa","fa-fw",n[e]||e,i].filter(Boolean).join(" ");return a?t("i",{className:s,title:a,"aria-label":a}):t("i",{className:s,"aria-hidden":"true"})}export{n as PIX_FA,f as PixIcon};
/**
 * Moodle pix / Font Awesome icon (Boost markup).
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
