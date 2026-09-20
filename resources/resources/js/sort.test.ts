import { describe, expect, it } from 'vitest';
import { bestIds, pinBest, sortTree, visibleComments, type BoardComment } from './sort';

const comments: BoardComment[] = [
    { id: 1, parent_id: null, depth: 0, created_at: '2026-01-02 00:00:00' },
    { id: 2, parent_id: 1, depth: 1, created_at: '2026-01-03 00:00:00' },
    { id: 3, parent_id: null, depth: 0, created_at: '2026-01-01 00:00:00' },
];

const counts = { 1: 2, 2: 9, 3: 8 };

describe('sortTree', () => {
    it('keeps replies under their parent', () => {
        expect(sortTree(comments, 'oldest', counts).map((item) => item.id)).toEqual([3, 1, 2]);
        expect(sortTree(comments, 'popular', counts).map((item) => item.id)).toEqual([3, 1, 2]);
    });
});

describe('best and pin', () => {
    it('pins best root threads first', () => {
        expect(bestIds(counts, 5, 2)).toEqual([2, 3]);
        const sorted = sortTree(comments, 'popular', counts);
        expect(pinBest(sorted, [3]).map((item) => item.id)).toEqual([3, 1, 2]);
    });
});

describe('visibleComments', () => {
    it('hides replies until the root is expanded', () => {
        expect(visibleComments(comments, []).map((item) => item.id)).toEqual([1, 3]);
        expect(visibleComments(comments, [1]).map((item) => item.id)).toEqual([1, 2, 3]);
    });
});
