import { describe, expect, it } from 'vitest';
import { applyPluginClasses, applyStickerMotionClass, appliesToBoard, DEFAULT_CONFIG, normalizeConfig, overlayConfig, parseSlugs, readInlineConfig } from './config';

describe('normalizeConfig', () => {
    it('uses safe defaults', () => {
        expect(normalizeConfig(undefined)).toMatchObject({
            enabled: true,
            allowGuestLikes: false,
            defaultSort: 'latest',
            boardSlugs: ['free'],
            stickerPack: 'full',
            stickersAnimated: true,
        });
    });

    it('clamps and parses slugs', () => {
        expect(normalizeConfig({
            enabled: '0',
            best_threshold: 5000,
            default_sort: 'popular',
            board_slugs: 'Free, qna',
            sticker_pack: 'simple',
            stickers_animated: '0',
        })).toMatchObject({
            enabled: false,
            bestThreshold: 999,
            defaultSort: 'popular',
            boardSlugs: ['free', 'qna'],
            stickerPack: '48',
            stickersAnimated: false,
        });
        expect(normalizeConfig({ sticker_pack: '96' }).stickerPack).toBe('96');
        expect(normalizeConfig({ sticker_pack: 'pack_12' }).stickerPack).toBe('12');
        expect(normalizeConfig({ sticker_pack: 24 }).stickerPack).toBe('24');
        expect(normalizeConfig({ sticker_pack: 'all' }).stickerPack).toBe('full');
    });

    it('rejects invalid slugs', () => {
        expect(parseSlugs('ok, ../x, Hello!')).toEqual(['ok']);
        expect(normalizeConfig({ board_slugs: '' }).boardSlugs).toEqual([]);
        expect(appliesToBoard('qna', [])).toBe(true);
        expect(appliesToBoard('qna', ['free'])).toBe(false);
        expect(normalizeConfig({ sticker_pack: 'nope' }).stickerPack).toBe('full');
    });

    it('toggles the animated sticker class on the document root', () => {
        const root = document.createElement('html');
        applyStickerMotionClass(true, root);
        expect(root.classList.contains('cbc-stickers-animated')).toBe(true);
        applyStickerMotionClass(false, root);
        expect(root.classList.contains('cbc-stickers-animated')).toBe(false);
        applyPluginClasses({
            ...normalizeConfig(undefined),
            styleEnabled: true,
            stickersAnimated: true,
        }, root);
        expect(root.classList.contains('cbc-styled')).toBe(true);
        expect(root.classList.contains('cbc-stickers-animated')).toBe(true);
    });

    it('keeps inline pack size when the settings payload omits it', () => {
        const merged = overlayConfig({ ...DEFAULT_CONFIG, stickerPack: '12', stickersAnimated: false }, {
            stickers_animated: true,
        });
        expect(merged.stickerPack).toBe('12');
        expect(merged.stickersAnimated).toBe(true);
    });

    it('reads config from the GitHub folder id when the install folder still uses it', () => {
        const win = {
            G7Config: {
                plugins: {
                    'g7-plugin-custom-board_comments': { sticker_pack: '24', stickers_animated: false },
                },
            },
        } as Window & { G7Config?: { plugins?: Record<string, unknown> } };
        expect(readInlineConfig(win)).toMatchObject({
            stickerPack: '24',
            stickersAnimated: false,
        });
    });

    it('prefers custom-board_comments over the GitHub folder id', () => {
        const win = {
            G7Config: {
                plugins: {
                    'g7-plugin-custom-board_comments': { sticker_pack: '24' },
                    'custom-board_comments': { sticker_pack: '96' },
                },
            },
        } as Window & { G7Config?: { plugins?: Record<string, unknown> } };
        expect(readInlineConfig(win).stickerPack).toBe('96');
    });
});
