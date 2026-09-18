import { describe, expect, it } from 'vitest';
import { isBoardPostApi, parseBoardShowPath, unwrapApiData } from './url';

describe('parseBoardShowPath', () => {
    it('reads slug and id with an optional locale prefix', () => {
        expect(parseBoardShowPath('/board/free/12')).toEqual({ slug: 'free', postId: 12 });
        expect(parseBoardShowPath('/ko/board/qna/3')).toEqual({ slug: 'qna', postId: 3 });
        expect(parseBoardShowPath('/maker-bids/1')).toBeNull();
    });
});

describe('isBoardPostApi', () => {
    it('matches the post detail endpoint only', () => {
        expect(isBoardPostApi('/api/modules/sirsoft-board/boards/free/posts/12')).toBe(true);
        expect(isBoardPostApi('/api/modules/sirsoft-board/boards/free/posts/12?del_cmt=1')).toBe(true);
        expect(isBoardPostApi('/api/modules/sirsoft-board/boards/free/posts/12/navigation')).toBe(false);
    });
});

describe('unwrapApiData', () => {
    it('reads nested G7 resource payloads', () => {
        expect(unwrapApiData({ success: true, data: { id: 1, comments: [] } })).toMatchObject({ id: 1 });
    });
});
