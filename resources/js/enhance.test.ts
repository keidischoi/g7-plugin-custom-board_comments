import { describe, expect, it } from 'vitest';
import { bindCommentRows, commentRows, ensureToolbar, paintLikes } from './enhance';
import { DEFAULT_CONFIG } from './config';
import type { BoardComment } from './sort';

function commentSection(): HTMLElement {
    document.body.innerHTML = `
        <div class="bg-white rounded-lg shadow" data-cbc-section="1">
            <h3>댓글 2</h3>
            <div class="space-y-4">
                <div class="border-b"><div class="flex-1"><div class="flex items-center gap-1">홍길동</div></div></div>
                <div class="border-b"><div class="flex-1"><div class="flex items-center gap-1">임꺽정</div></div></div>
            </div>
        </div>
    `;
    return document.querySelector('[data-cbc-section]') as HTMLElement;
}

const comments: BoardComment[] = [
    { id: 11, parent_id: null, depth: 0, created_at: '2026-01-02 00:00:00' },
    { id: 12, parent_id: null, depth: 0, created_at: '2026-01-01 00:00:00' },
];

describe('enhance', () => {
    it('binds comment ids and paints like buttons', () => {
        const section = commentSection();
        bindCommentRows(section, comments);
        expect(commentRows(section).map((row) => row.getAttribute('data-cbc-comment-id'))).toEqual(['11', '12']);

        paintLikes(section, {
            counts: { 11: 5, 12: 1 },
            liked: [11],
            best: [11],
        }, DEFAULT_CONFIG, {
            like: '추천',
            liked: '추천함',
            best: '베스트',
            latest: '최신순',
            oldest: '등록순',
            popular: '추천순',
            login: '로그인',
        });

        const liked = section.querySelector('[data-cbc-comment-id="11"] [data-cbc-like]');
        expect(liked?.textContent).toContain('추천함 5');
        expect(section.querySelector('[data-cbc-comment-id="11"] [data-cbc-best]')?.textContent).toBe('베스트');
    });

    it('inserts a sort toolbar on the comments heading', () => {
        const section = commentSection();
        const toolbar = ensureToolbar(section, 'popular', {
            like: '추천',
            liked: '추천함',
            best: '베스트',
            latest: '최신순',
            oldest: '등록순',
            popular: '추천순',
            login: '로그인',
        });
        expect(toolbar.querySelector('[data-cbc-sort="popular"]')?.classList.contains('is-active')).toBe(true);
        expect(section.querySelectorAll('[data-cbc-toolbar]').length).toBe(1);
        ensureToolbar(section, 'latest', {
            like: '추천', liked: '추천함', best: '베스트', latest: '최신순', oldest: '등록순', popular: '추천순', login: '로그인',
        });
        expect(section.querySelectorAll('[data-cbc-toolbar]').length).toBe(1);
    });
});
