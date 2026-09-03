import{Children as f,forwardRef as l,isValidElement as B}from"react";import{Button as p}from"@moodlehq/design-system";import{jsx as i}from"react/jsx-runtime";function y(t){return B(t)?t.type==="svg"||t.type==="i"||typeof t.type=="function":!1}function b(t){if(t==null||t===!1)return{useBootstrap:!0};if(typeof t=="string"||typeof t=="number")return{label:String(t),useBootstrap:!1};let e=f.toArray(t);return e.length===1&&!y(e[0])&&(typeof e[0]=="string"||typeof e[0]=="number")?{label:String(e[0]),useBootstrap:!1}:{useBootstrap:!0}}function d(t,e,o,r,u,s){let a=t==="outline-secondary"?"btn-outline-secondary":`btn-${t}`,n=e?`btn-${e}`:"";return i("button",{ref:u,type:"button",className:["btn",a,n,o].filter(Boolean).join(" "),...s,children:r})}var R=l(function({variant:e="secondary",size:o,className:r="",children:u,...s},a){let n=b(u);return!n.useBootstrap&&n.label!==void 0?i(p,{type:"button",variant:e,size:o==="sm"?"sm":void 0,label:n.label,className:r,...s}):d(e,o,r,u,a,s)});export{R as DsButton};
/**
 * Moodle Design System Button with Bootstrap fallback.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
