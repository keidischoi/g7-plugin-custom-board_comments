import { describe, expect, it } from 'vitest';
import { looksLikeCommentTree, mutationNeedsCommentSync } from './observe';

describe('observe', () => {
    it('sees a DocumentFragment that wraps the comment heading', () => {
        const fragment = document.createDocumentFragment();
        const wrap = document.createElement('div');
        wrap.innerHTML = '<h3><span>댓글</span><span>0</span></h3>';
        fragment.appendChild(wrap);
        expect(looksLikeCommentTree(fragment)).toBe(true);
    });

    it('resyncs when the overlay toolbar is removed from document.body', () => {
        document.body.innerHTML = '<div id="app"><div class="bg-white rounded-lg shadow" data-cbc-section="1"><h3><span>댓글</span></h3></div></div>';
        const toolbar = document.createElement('div');
        toolbar.setAttribute('data-cbc-toolbar', '1');
        toolbar.className = 'cbc-toolbar cbc-toolbar--overlay';
        document.body.appendChild(toolbar);
        const record = {
            type: 'childList',
            addedNodes: [] as unknown as NodeList,
            removedNodes: [toolbar] as unknown as NodeList,
            target: document.body,
        } as MutationRecord;
        expect(mutationNeedsCommentSync([record])).toBe(true);
    });

    it('ignores comment rows moving inside the sorted stack', () => {
        document.body.innerHTML = '<div class="space-y-4 cbc-comment-stack"><div data-cbc-comment-id="2"></div><div data-cbc-comment-id="3"></div></div>';
        const stack = document.querySelector('.cbc-comment-stack') as HTMLElement;
        const row = stack.firstElementChild as HTMLElement;
        const record = {
            type: 'childList',
            addedNodes: [row] as unknown as NodeList,
            removedNodes: [row] as unknown as NodeList,
            target: stack,
        } as MutationRecord;
        expect(mutationNeedsCommentSync([record])).toBe(false);
    });
});
