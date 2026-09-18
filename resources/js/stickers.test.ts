import { describe, expect, it } from 'vitest';
import { STICKERS, stickerById, stickerToken } from './stickers';

describe('stickers', () => {
    it('offers a large unique sticker set', () => {
        expect(STICKERS.length).toBeGreaterThanOrEqual(200);
        const ids = STICKERS.map((item) => item.id);
        expect(new Set(ids).size).toBe(ids.length);
        expect(stickerById('love')?.emoji).toBe('😍');
        expect(stickerToken('love')).toBe('[[s:love]]');
    });
});
