function o(e){return(t=>t.replace(/[&<>]/g,r=>({"&":"&amp;","<":"&lt;",">":"&gt;"})[r]||r))(e||"").replace(/\[([^\]]+)\]\((https?:[^)]+)\)/g,'<a href="$2" target="_blank" rel="noopener">$1</a>').replace(/\*\*([^*]+)\*\*/g,"<strong>$1</strong>").replace(/\n/g,"<br>")}function i(e){try{return JSON.stringify(JSON.parse(e||"{}"),null,2)}catch{return e||""}}function a(e){return new Promise((s,t)=>{let r=new FileReader;r.onload=()=>{let n=String(r.result||"");s(n.includes(",")?n.split(",")[1]:n)},r.onerror=t,r.readAsDataURL(e)})}export{o as mdLite,i as prettyJson,a as readAsBase64};
/**
 * Conservative markdown-to-HTML for assistant messages.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
