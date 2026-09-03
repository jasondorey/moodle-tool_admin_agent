/**
 * Moodle Design System Button with Bootstrap fallback.
 *
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    Children,
    forwardRef,
    isValidElement,
    type ButtonHTMLAttributes,
    type ForwardedRef,
    type ReactElement,
    type ReactNode,
} from 'react';
import {Button as MdsButton} from '@moodlehq/design-system';

type ButtonVariant = 'primary' | 'secondary' | 'outline-secondary';
type ButtonSize = 'sm' | undefined;

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: ButtonVariant;
    size?: ButtonSize;
    children?: ReactNode;
};

function isIconElement(element: ReactNode): element is ReactElement {
    if (!isValidElement(element)) {
        return false;
    }
    return element.type === 'svg' || element.type === 'i' || typeof element.type === 'function';
}

function extractButtonLabel(children: ReactNode): {label?: string; useBootstrap: boolean} {
    if (children === null || children === undefined || children === false) {
        return {useBootstrap: true};
    }
    if (typeof children === 'string' || typeof children === 'number') {
        return {label: String(children), useBootstrap: false};
    }
    const items = Children.toArray(children);
    if (items.length === 1 && !isIconElement(items[0]) && (typeof items[0] === 'string' || typeof items[0] === 'number')) {
        return {label: String(items[0]), useBootstrap: false};
    }
    return {useBootstrap: true};
}

function renderBootstrapButton(
    variant: ButtonVariant,
    size: ButtonSize,
    className: string,
    children: ReactNode,
    ref: ForwardedRef<HTMLButtonElement>,
    props: ButtonHTMLAttributes<HTMLButtonElement>,
) {
    const variantClass = variant === 'outline-secondary' ? 'btn-outline-secondary' : `btn-${variant}`;
    const sizeClass = size ? `btn-${size}` : '';
    return (
        <button
            ref={ref}
            type="button"
            className={['btn', variantClass, sizeClass, className].filter(Boolean).join(' ')}
            {...props}
        >
            {children}
        </button>
    );
}

export const DsButton = forwardRef<HTMLButtonElement, ButtonProps>(function DsButton(
    {variant = 'secondary', size, className = '', children, ...props},
    ref,
) {
    const content = extractButtonLabel(children);
    if (!content.useBootstrap && content.label !== undefined) {
        return (
            <MdsButton
                type="button"
                variant={variant}
                size={size === 'sm' ? 'sm' : undefined}
                label={content.label}
                className={className}
                {...props}
            />
        );
    }
    return renderBootstrapButton(variant, size, className, children, ref, props);
});
