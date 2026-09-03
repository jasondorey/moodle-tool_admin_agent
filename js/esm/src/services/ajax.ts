/**
 * AMD compatibility helpers for Moodle 5.2 ESM/React.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare global {
    interface Window {
        require?: (
            modules: string[],
            callback: (...args: unknown[]) => void,
            errback?: (err: Error) => void,
        ) => void;
    }
}

const REQUIRE_WAIT_MS = 2000;
const REQUIRE_POLL_MS = 50;

/**
 * Wait until Moodle's RequireJS loader is on window.
 */
function waitForRequire(timeoutMs = REQUIRE_WAIT_MS): Promise<NonNullable<Window['require']>> {
    return new Promise((resolve, reject) => {
        if (window.require) {
            resolve(window.require);
            return;
        }
        const started = Date.now();
        const timer = window.setInterval(() => {
            if (window.require) {
                window.clearInterval(timer);
                resolve(window.require);
                return;
            }
            if (Date.now() - started >= timeoutMs) {
                window.clearInterval(timer);
                reject(new Error('Moodle AMD loader is unavailable'));
            }
        }, REQUIRE_POLL_MS);
    });
}

/**
 * Unwrap ESM-interop `{default: T}` if present.
 */
export function unwrapAmdExport<T = unknown>(raw: unknown): T {
    if (raw && typeof raw === 'object' && 'default' in (raw as object)) {
        const def = (raw as {default: unknown}).default;
        if (def !== undefined && def !== null) {
            return def as T;
        }
    }
    return raw as T;
}

/**
 * Load an AMD module by name.
 */
export function loadAmdModule<T = unknown>(name: string): Promise<T> {
    return waitForRequire().then(
        (require) =>
            new Promise<T>((resolve, reject) => {
                require(
                    [name],
                    (...args: unknown[]) => {
                        resolve(unwrapAmdExport<T>(args[0]));
                    },
                    reject,
                );
            }),
    );
}

type AjaxCall = {
    methodname: string;
    args: Record<string, unknown>;
};

/**
 * Call a Moodle web service via core/ajax.
 */
export function callAjax<T = unknown>(methodname: string, args: Record<string, unknown> = {}): Promise<T> {
    return waitForRequire().then(
        (require) =>
            new Promise<T>((resolve, reject) => {
                require(
                    ['core/ajax'],
                    (...mods: unknown[]) => {
                        const Ajax = unwrapAmdExport<{
                            call: (c: AjaxCall[]) => Array<{
                                then: (fn: (v: T) => void) => {catch: (fn: (e: Error) => void) => void};
                            }>;
                        }>(mods[0]);
                        Ajax.call([{methodname, args}])[0].then(resolve).catch(reject);
                    },
                    reject,
                );
            }),
    );
}
