/**
 * Behat-friendly pending promises via core/pending.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {loadAmdModule} from './ajax';

type PendingCtor = new (name: string) => {resolve: () => void};

/**
 * Run an async task under a Moodle Pending key.
 */
export async function withPending<T>(name: string, task: () => Promise<T>): Promise<T> {
    const Pending = await loadAmdModule<PendingCtor>('core/pending');
    const pending = new Pending(name);
    try {
        return await task();
    } finally {
        pending.resolve();
    }
}
