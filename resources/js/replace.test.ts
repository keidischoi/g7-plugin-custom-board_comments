// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { hasMountBox, mountAll, mountPostId, pendingMounts, TARGET_TYPE } from './replace';
import { isReplacedBoard, normalizeConfig, parseReplaceSlugs } from './config';

afterEach(() => {
    document.body.innerHTML = '';
    delete (window as unknown as Record<string, unknown>).CustomComments;
    delete (window as unknown as Record<string, unknown>).CustomDigitalComments;
});

describe('replace mode config', () => {
    it('parses replace slugs (empty = off, * = all)', () => {
        expect(parseReplaceSlugs('')).toEqual([]);
        expect(parseReplaceSlugs('Free, qa')).toEqual(['free', 'qa']);
        expect(parseReplaceSlugs('free,*')).toEqual(['*']);
        expect(isReplacedBoard('free', [])).toBe(false);
        expect(isReplacedBoard('FREE', ['free'])).toBe(true);
        expect(isReplacedBoard('notice', ['*'])).toBe(true);
    });
    it('defaults: replace off, mirror on', () => {
        const c = normalizeConfig({});
        expect(c.replaceSlugs).toEqual([]);
        expect(c.mirrorEnabled).toBe(true);
        expect(normalizeConfig({ mirror_enabled: false }).mirrorEnabled).toBe(false);
    });
});

describe('mount boxes', () => {
    it('finds pending boxes and mounts board_post targets', async () => {
        document.body.innerHTML = '<div data-cbc-cc-post="12"></div><div data-cbc-cc-post="abc"></div>';
        expect(hasMountBox()).toBe(true);
        expect(pendingMounts().length).toBe(1);
        expect(mountPostId(document.querySelector('[data-cbc-cc-post]')!)).toBe(12);
        const calls: Array<[string, number]> = [];
        (window as unknown as Record<string, unknown>).CustomComments = {
            mountTarget: (el: Element, type: string, id: number) => {
                calls.push([type, id]);
                el.setAttribute('data-cdc-owned', '1');
                el.setAttribute('data-cdc-target-id', String(id));
                return true;
            },
        };
        expect(await mountAll()).toBe(1);
        expect(calls).toEqual([[TARGET_TYPE, 12]]);
        // 이미 붙은 상자는 다시 붙이지 않음
        expect(pendingMounts().length).toBe(0);
        expect(await mountAll()).toBe(0);
    });

    it('re-mounts when the same box now shows another post (SPA)', async () => {
        document.body.innerHTML = '<div data-cbc-cc-post="5" data-cdc-owned="1" data-cdc-target-id="4"></div>';
        const spy = vi.fn(() => true);
        (window as unknown as Record<string, unknown>).CustomComments = { mountTarget: spy };
        expect(pendingMounts().length).toBe(1);
        await mountAll();
        expect(spy).toHaveBeenCalledWith(expect.anything(), 'board_post', 5);
    });

    it('shows a note when custom-comments script cannot load', async () => {
        document.body.innerHTML = '<div data-cbc-cc-post="7"></div>';
        const p = mountAll();
        const s = document.head.querySelector('script[src*="custom-comments"]') as HTMLScriptElement;
        expect(s).toBeTruthy();
        s.onerror?.(new Event('error'));
        expect(await p).toBe(0);
        expect(document.querySelector('[data-cbc-cc-fallback]')).toBeTruthy();
    });

    it('no boxes → nothing', async () => {
        expect(hasMountBox()).toBe(false);
        expect(await mountAll()).toBe(0);
    });
});
