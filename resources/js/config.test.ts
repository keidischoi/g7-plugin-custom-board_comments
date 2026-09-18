import { describe, expect, it } from 'vitest';
import { appliesToBoard, normalizeConfig, parseSlugs } from './config';

describe('normalizeConfig', () => {
    it('uses safe defaults', () => {
        expect(normalizeConfig(undefined)).toMatchObject({
            enabled: true,
            allowGuestLikes: false,
            defaultSort: 'latest',
            boardSlugs: ['free'],
        });
    });

    it('clamps and parses slugs', () => {
        expect(normalizeConfig({
            enabled: '0',
            best_threshold: 5000,
            default_sort: 'popular',
            board_slugs: 'Free, qna',
        })).toMatchObject({
            enabled: false,
            bestThreshold: 999,
            defaultSort: 'popular',
            boardSlugs: ['free', 'qna'],
        });
    });

    it('rejects invalid slugs', () => {
        expect(parseSlugs('ok, ../x, Hello!')).toEqual(['ok']);
        expect(normalizeConfig({ board_slugs: '' }).boardSlugs).toEqual([]);
        expect(appliesToBoard('qna', [])).toBe(true);
        expect(appliesToBoard('qna', ['free'])).toBe(false);
    });
});
