/**
 * Conservative markdown-to-HTML for assistant messages.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Escape, then allow links, bold, and line breaks.
 */
export function mdLite(text: string): string {
    const esc = (value: string) =>
        value.replace(/[&<>]/g, (char) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;'}[char] || char));
    return esc(text || '')
        .replace(/\[([^\]]+)\]\((https?:[^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
}

/**
 * Pretty-print JSON arguments or results.
 */
export function prettyJson(raw: string): string {
    try {
        return JSON.stringify(JSON.parse(raw || '{}'), null, 2);
    } catch {
        return raw || '';
    }
}

/**
 * Read a File as base64 without the data: prefix.
 */
export function readAsBase64(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const result = String(reader.result || '');
            resolve(result.includes(',') ? result.split(',')[1] : result);
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}
