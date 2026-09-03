function c(n=2e3){return new Promise((e,o)=>{if(window.require){e(window.require);return}let t=Date.now(),r=window.setInterval(()=>{if(window.require){window.clearInterval(r),e(window.require);return}Date.now()-t>=n&&(window.clearInterval(r),o(new Error("Moodle AMD loader is unavailable")))},50)})}function d(n){if(n&&typeof n=="object"&&"default"in n){let e=n.default;if(e!=null)return e}return n}function i(n){return c().then(e=>new Promise((o,t)=>{e([n],(...r)=>{o(d(r[0]))},t)}))}var u=["pluginname","welcome","welcometext","welcomeback","welcomebacktext","resumelast","newchat","previouschats","deletechat","confirmdelchat","untitledchat","nopreviouschats","lastmessageago","typemessage","send","help","helpbody","autoapprovelabel","autoapprove_heading","autoapprove_none","autoapprove_none_desc","autoapprove_readonly","autoapprove_readonly_desc","autoapprove_safe","autoapprove_safe_desc","autoapprove_all","autoapprove_all_desc","approve","deny","functioncalldetails","parameters","result","copy","thumbs_up","thumbs_down","attach","history","changelayout","moreactions","close","remove","errorfilesize","requestfailed","waiting","view:overlay","view:sidebar","view:fullscreen","ago_secs","ago_mins","ago_hours"],a=null;async function l(){return a||(a=await i("core/str")),a}async function m(){let e=await(await l()).get_strings(u.map(t=>({key:t,component:"tool_adminagent"}))),o={};return u.forEach((t,r)=>{o[t]=e[r]}),o}async function s(n,e){return(await l()).get_string(n,"tool_adminagent",e)}async function w(n){let e=Math.max(1,Math.floor(Date.now()/1e3-n));return e<60?s("ago_secs",e):e<3600?s("ago_mins",{mins:Math.floor(e/60),secs:e%60}):s("ago_hours",Math.floor(e/3600))}export{u as STRING_KEYS,w as formatAgo,l as getStrModule,s as getString,m as loadChatStrings};
/**
 * AMD compatibility helpers for Moodle 5.2 ESM/React.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Lang string loading for the chat widget.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
