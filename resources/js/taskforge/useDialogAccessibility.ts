import { useEffect, useRef, type RefObject } from 'react';

/**
 * Modal focus lifecycle:
 * - first suitable input/heading receives focus;
 * - Tab and Shift+Tab stay inside the dialog;
 * - Escape closes unless an operation is pending;
 * - focus returns to the trigger after unmount.
 */
export function useDialogAccessibility<T extends HTMLElement>(
    open: boolean,
    onClose: () => void,
    busy = false,
): RefObject<T> {
    const ref = useRef<T>(null);
    const closeRef = useRef(onClose);
    const busyRef = useRef(busy);
    closeRef.current = onClose;
    busyRef.current = busy;

    useEffect(() => {
        if (!open) return;
        const dialog = ref.current;
        if (!dialog) return;
        const previous = document.activeElement instanceof HTMLElement
            ? document.activeElement : null;
        const selector = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])';
        const focusables = () => Array.from(dialog.querySelectorAll<HTMLElement>(selector))
            .filter(element => element.getClientRects().length > 0);
        (dialog.querySelector<HTMLElement>('[autofocus]') || focusables()[0] || dialog).focus();
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const keydown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                if (!busyRef.current) {
                    event.preventDefault();
                    closeRef.current();
                }
                return;
            }
            if (event.key !== 'Tab') return;
            const items = focusables();
            if (items.length === 0) {
                event.preventDefault();
                dialog.focus();
                return;
            }
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
                event.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', keydown);
        return () => {
            document.removeEventListener('keydown', keydown);
            document.body.style.overflow = previousOverflow;
            previous?.focus();
        };
    }, [open]);

    return ref;
}
