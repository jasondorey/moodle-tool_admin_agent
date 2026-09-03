function i(n=2e3){return new Promise((e,o)=>{if(window.require){e(window.require);return}let t=Date.now(),r=window.setInterval(()=>{if(window.require){window.clearInterval(r),e(window.require);return}Date.now()-t>=n&&(window.clearInterval(r),o(new Error("Moodle AMD loader is unavailable")))},50)})}function a(n){if(n&&typeof n=="object"&&"default"in n){let e=n.default;if(e!=null)return e}return n}function d(n){return i().then(e=>new Promise((o,t)=>{e([n],(...r)=>{o(a(r[0]))},t)}))}function w(n,e={}){return i().then(o=>new Promise((t,r)=>{o(["core/ajax"],(...u)=>{a(u[0]).call([{methodname:n,args:e}])[0].then(t).catch(r)},r)}))}export{w as callAjax,d as loadAmdModule,a as unwrapAmdExport};
/**
 * AMD compatibility helpers for Moodle 5.2 ESM/React.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
