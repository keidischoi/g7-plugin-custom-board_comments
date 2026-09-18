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

    it('resyncs when the toolbar is removed from the live comment card', () => {
        document.body.innerHTML = `
            <div class="bg-white rounded-lg shadow" data-cbc-section="1">
                <h3><span>댓글</span></h3>
                <div data-cbc-toolbar="1" class="cbc-toolbar"></div>
            </div>
        `;
        const toolbar = document.querySelector('[data-cbc-toolbar]') as HTMLElement;
        const section = document.querySelector('[data-cbc-section]') as HTMLElement;
        const record = {
            type: 'childList',
            addedNodes: [] as unknown as NodeList,
            removedNodes: [toolbar] as unknown as NodeList,
            target: section,
        } as MutationRecord;
        expect(mutationNeedsCommentSync([record])).toBe(true);
    });
});
