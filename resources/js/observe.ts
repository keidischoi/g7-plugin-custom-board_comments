export function isOurWidget(node: Node): boolean {
    if (!(node instanceof Element)) {
        return false;
    }
    return Boolean(
        node.matches('[data-cbc-toolbar], [data-cbc-like], [data-cbc-best], [data-cbc-stickers], [data-cbc-media]')
        || node.closest('[data-cbc-toolbar], [data-cbc-like], [data-cbc-best], [data-cbc-stickers], [data-cbc-media]'),
    );
}

export function looksLikeCommentTree(node: Node): boolean {
    if (node instanceof DocumentFragment) {
        return [...node.childNodes].some(looksLikeCommentTree);
    }
    if (node.nodeType === Node.TEXT_NODE) {
        return /댓글|comments?/i.test(node.textContent ?? '');
    }
    if (!(node instanceof Element)) {
        return false;
    }
    if (isOurWidget(node) && !node.querySelector('h2, h3, h4')) {
        return false;
    }
    return Boolean(
        node.matches('h2, h3, h4')
        || node.querySelector('h2, h3, h4, .space-y-4')
        || /댓글|comments?/i.test(node.textContent ?? ''),
    );
}

function isCommentStackShuffle(mutation: MutationRecord): boolean {
    const target = mutation.target;
    if (!(target instanceof Element) || !target.classList.contains('cbc-comment-stack')) {
        return false;
    }
    const nodes = [...mutation.addedNodes, ...mutation.removedNodes];
    return nodes.length > 0 && nodes.every((node) => (
        node instanceof Element && (node.hasAttribute('data-cbc-comment-id') || Boolean(node.querySelector('[data-cbc-comment-id]')))
    ));
}

export function mutationNeedsCommentSync(mutations: MutationRecord[]): boolean {
    for (const mutation of mutations) {
        if (mutation.type !== 'childList') {
            continue;
        }
        if (isCommentStackShuffle(mutation)) {
            continue;
        }
        for (const node of mutation.removedNodes) {
            if (node instanceof Element && (
                node.matches('[data-cbc-toolbar], [data-cbc-section]')
                || node.querySelector('[data-cbc-toolbar], [data-cbc-section]')
            )) {
                return true;
            }
            if (looksLikeCommentTree(node)) {
                return true;
            }
        }
        for (const node of mutation.addedNodes) {
            if (isOurWidget(node)) {
                continue;
            }
            if (looksLikeCommentTree(node)) {
                return true;
            }
        }
    }
    return false;
}
