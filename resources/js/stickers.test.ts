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

    it('slices packs by 12, 24, 48, 96 and so on', () => {
        const twelve = stickersForPack('12');
        const fortyEight = stickersForPack('48');
        expect(twelve.length).toBe(12);
        expect(stickersForPack(24).length).toBe(24);
        expect(fortyEight.length).toBe(48);
        expect(stickersForPack('simple').length).toBe(48);
        expect(stickersForPack('96').length).toBe(96);
        expect(stickersForPack('192').length).toBe(192);
        expect(stickersForPack('384').length).toBe(384);
        expect(stickersForPack('full').length).toBe(STICKERS.length);
        expect(SIMPLE_STICKER_IDS.length).toBe(48);
        expect(twelve.map((item) => item.id)).toEqual(SIMPLE_STICKER_IDS.slice(0, 12));
        expect(fortyEight.every((item) => STICKERS.some((full) => full.id === item.id))).toBe(true);
    });
});
