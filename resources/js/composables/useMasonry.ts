import type { Ref } from 'vue';
import { watch } from 'vue';

/**
 * Masonry that reads left to right: the container is a CSS grid of 1px rows, and each child
 * spans as many rows as it is tall (plus the gap), re-measured whenever its size changes.
 * Children stay in DOM order, unlike CSS columns, which fill each column top to bottom.
 */
export function useMasonry(container: Ref<HTMLElement | null>, gap: number) {
    watch(
        container,
        (element, _previous, onCleanup) => {
            if (!element) {
                return;
            }

            const span = (child: Element): void => {
                const height = child.getBoundingClientRect().height;
                (child as HTMLElement).style.gridRowEnd =
                    `span ${Math.ceil(height + gap)}`;
            };
            const sizes = new ResizeObserver((entries) =>
                entries.forEach((entry) => span(entry.target)),
            );
            const observeChildren = (): void =>
                Array.from(element.children).forEach((child) =>
                    sizes.observe(child),
                );
            const children = new MutationObserver((records) => {
                records.forEach((record) =>
                    record.removedNodes.forEach((node) =>
                        sizes.unobserve(node as Element),
                    ),
                );
                observeChildren();
            });

            observeChildren();
            children.observe(element, { childList: true });
            onCleanup(() => {
                sizes.disconnect();
                children.disconnect();
            });
        },
        { flush: 'post' },
    );
}
