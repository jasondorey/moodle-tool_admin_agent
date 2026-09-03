function a(n=2e3){return new Promise((e,r)=>{if(window.require){e(window.require);return}let o=Date.now(),i=window.setInterval(()=>{if(window.require){window.clearInterval(i),e(window.require);return}Date.now()-o>=n&&(window.clearInterval(i),r(new Error("Moodle AMD loader is unavailable")))},50)})}function d(n){if(n&&typeof n=="object"&&"default"in n){let e=n.default;if(e!=null)return e}return n}function t(n){return a().then(e=>new Promise((r,o)=>{e([n],(...i)=>{r(d(i[0]))},o)}))}async function s(n,e){let r=await t("core/pending"),o=new r(n);try{return await e()}finally{o.resolve()}}export{s as withPending};
/**
 * AMD compatibility helpers for Moodle 5.2 ESM/React.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Behat-friendly pending promises via core/pending.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
