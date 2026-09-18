import { describe, expect, it } from 'vitest';
import { STICKERS, SIMPLE_STICKER_IDS, stickerById, stickersForPack, stickerToken } from './stickers';

describe('stickers', () => {
    it('offers a large unique sticker set', () => {
        expect(STICKERS.length).toBeGreaterThanOrEqual(500);
        const ids = STICKERS.map((item) => item.id);
        const emojis = STICKERS.map((item) => item.emoji);
        expect(new Set(ids).size).toBe(ids.length);
        expect(new Set(emojis).size).toBe(emojis.length);
        expect(stickerById('love')?.emoji).toBe('😍');
        expect(stickerToken('love')).toBe('[[s:love]]');
    });

    it('keeps a smaller simple pack inside the full set', () => {
        const simple = stickersForPack('simple');
        expect(simple.length).toBe(SIMPLE_STICKER_IDS.length);
        expect(simple.length).toBeGreaterThanOrEqual(40);
        expect(simple.length).toBeLessThanOrEqual(80);
        expect(simple.length).toBeLessThan(STICKERS.length / 5);
        expect(stickersForPack('full').length).toBe(STICKERS.length);
        expect(simple.every((item) => STICKERS.some((full) => full.id === item.id))).toBe(true);
    });
});
