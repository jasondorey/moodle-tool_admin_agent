function s(e=2e3){return new Promise((n,t)=>{if(window.require){n(window.require);return}let i=Date.now(),o=window.setInterval(()=>{if(window.require){window.clearInterval(o),n(window.require);return}Date.now()-i>=e&&(window.clearInterval(o),t(new Error("Moodle AMD loader is unavailable")))},50)})}function l(e){if(e&&typeof e=="object"&&"default"in e){let n=e.default;if(n!=null)return n}return e}function r(e,n={}){return s().then(t=>new Promise((i,o)=>{t(["core/ajax"],(...a)=>{l(a[0]).call([{methodname:e,args:n}])[0].then(i).catch(o)},o)}))}function g(e){return r("tool_adminagent_get_widget_config",{path:e})}function c(e,n){return r("tool_adminagent_create_conversation",{placementid:e,layout:n})}function b(e){return r("tool_adminagent_get_conversation",{conversationid:e})}function f(e,n){return r("tool_adminagent_set_layout",{conversationid:e,layout:n})}function p(e,n,t,i,o){return r("tool_adminagent_send_message",{conversationid:e,message:n,pageurl:t,autoapprove:i,attachments:o})}function v(e,n,t){return r("tool_adminagent_resolve_call",{conversationid:e,callid:n,approve:t})}function w(e,n){return r("tool_adminagent_submit_feedback",{messageid:e,rating:n})}function _(e,n){return r("tool_adminagent_upload_files",{conversationid:e,files:n})}function x(){return r("tool_adminagent_list_conversations",{})}function C(e){return r("tool_adminagent_delete_conversation",{conversationid:e})}export{c as createConversation,C as deleteConversation,b as getConversation,g as getWidgetConfig,x as listConversations,v as resolveCall,p as sendMessage,f as setLayout,w as submitFeedback,_ as uploadFiles};
/**
 * AMD compatibility helpers for Moodle 5.2 ESM/React.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Web service wrappers for the admin agent chat.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
