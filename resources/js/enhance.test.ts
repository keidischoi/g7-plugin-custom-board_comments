import { describe, expect, it } from 'vitest';
import { bindCommentRows, commentRows, ensureToolbar, findCommentSection, paintLikes } from './enhance';
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

const copy = {
    like: '추천',
    liked: '추천함',
    best: '베스트',
    latest: '최신순',
    oldest: '등록순',
    popular: '추천순',
    sortLabel: '댓글 정렬',
    sticker: '스티커',
    image: '이미지',
    needComposer: '댓글 입력창이 없습니다.',
    uploadFail: '업로드 실패',
    login: '로그인',
};

describe('enhance', () => {
    it('binds comment ids and paints like buttons', () => {
        const section = commentSection();
        bindCommentRows(section, comments);
        expect(commentRows(section).map((row) => row.getAttribute('data-cbc-comment-id'))).toEqual(['11', '12']);

        paintLikes(section, {
            counts: { 11: 5, 12: 1 },
            liked: [11],
            best: [11],
        }, DEFAULT_CONFIG, copy);

        const liked = section.querySelector('[data-cbc-comment-id="11"] [data-cbc-like]');
        expect(liked?.textContent).toContain('추천함 5');
        expect(section.querySelector('[data-cbc-comment-id="11"] [data-cbc-best]')?.textContent).toBe('베스트');
    });

    it('inserts a sort toolbar on document.body, not inside the comment card', () => {
        const section = commentSection();
        const toolbar = ensureToolbar(section, 'popular', copy);
        expect(toolbar.querySelector('[data-cbc-sort="popular"]')?.classList.contains('is-active')).toBe(true);
        expect(toolbar.parentElement).toBe(document.body);
        expect(section.querySelectorAll('[data-cbc-toolbar]').length).toBe(0);
        expect(document.querySelectorAll('[data-cbc-toolbar]').length).toBe(1);
        ensureToolbar(section, 'latest', copy);
        expect(document.querySelectorAll('[data-cbc-toolbar]').length).toBe(1);
    });

    it('finds the official comment heading even when there are no comments', () => {
        document.body.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <h3 class="text-lg font-semibold">댓글 0</h3>
                <div class="px-6 py-8 text-center">아직 댓글이 없습니다.</div>
            </div>
        `;
        const section = findCommentSection();
        expect(section).not.toBeNull();
        const toolbar = ensureToolbar(section as Element, 'latest', copy);
        expect(toolbar.querySelector('[data-cbc-sort="latest"]')?.textContent).toBe('최신순');
        expect(toolbar.parentElement).toBe(document.body);
        expect(toolbar.classList.contains('cbc-toolbar--overlay')).toBe(true);
    });

    it('finds the live 3D Store comment card and mounts the toolbar outside React', () => {
        document.body.innerHTML = `
            <div id="app">
                <div class="mt-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center justify-between px-6 pt-6 pb-4">
                            <div class="flex items-center gap-2"><span>댓글</span><span>0</span></div>
                        </h3>
                        <div class="border-b border-gray-200 dark:border-gray-700"></div>
                        <div class="px-6 py-8 text-center">댓글 작성 권한이 없습니다</div>
                    </div>
                </div>
            </div>
        `;
        const section = findCommentSection();
        expect(section).not.toBeNull();
        const toolbar = ensureToolbar(section as Element, 'latest', copy);
        expect(toolbar.parentElement).toBe(document.body);
        expect(document.getElementById('app')?.contains(toolbar)).toBe(false);
        expect(toolbar.textContent).toContain('최신순');
        expect(toolbar.textContent).toContain('댓글 정렬');
        expect(toolbar.textContent).toContain('스티커');
        expect(toolbar.textContent).toContain('이미지');
        document.getElementById('app')!.innerHTML = '<div>replaced</div>';
        expect(document.querySelector('[data-cbc-toolbar]')).toBe(toolbar);
    });

    it('shows the overlay even before the comment card exists', () => {
        document.body.innerHTML = '<div id="app"></div>';
        const toolbar = ensureToolbar(null, 'oldest', copy);
        expect(toolbar.parentElement).toBe(document.body);
        expect(toolbar.dataset.cbcAnchored).toBe('0');
        expect(toolbar.querySelector('[data-cbc-sort="oldest"]')?.classList.contains('is-active')).toBe(true);
    });
});
