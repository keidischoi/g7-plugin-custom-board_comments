import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    computePanelPlacement,
    ensureComposerControls,
    findComposer,
    insertIntoComposer,
    PANEL_MARGIN,
    PANEL_MAX_WIDTH,
    STICKER_DISMISS_ARM_MS,
} from './composer';
import { imageToken, SIMPLE_STICKER_IDS, stickerToken } from './stickers';
import { paintTokens } from './tokens';
import { DEFAULT_CONFIG } from './config';

const copy = {
    sticker: '스티커',
    image: '이미지',
    needComposer: '없음',
    uploadFail: '실패',
    login: '로그인',
};

function openStickerPanel(config = DEFAULT_CONFIG): { toolbar: HTMLElement; textarea: HTMLTextAreaElement } {
    document.body.innerHTML = `
        <div data-cbc-section="1">
            <h3>댓글</h3>
            <textarea></textarea>
        </div>
    `;
    const toolbar = document.createElement('div');
    toolbar.innerHTML = '<button type="button" data-cbc-sticker="1">스티커</button><button type="button" data-cbc-sort="latest">최신순</button>';
    document.body.appendChild(toolbar);
    ensureComposerControls(toolbar, document.querySelector('[data-cbc-section]'), config, copy, async () => ({ token: '' }));
    toolbar.querySelector<HTMLButtonElement>('[data-cbc-sticker]')?.click();
    return {
        toolbar,
        textarea: document.querySelector('textarea') as HTMLTextAreaElement,
    };
}

describe('composer', () => {
    it('inserts a sticker token into a comment textarea the way React can see', () => {
        document.body.innerHTML = `
            <div data-cbc-section="1">
                <h3>댓글</h3>
                <textarea></textarea>
            </div>
        `;
        const field = findComposer(document.querySelector('[data-cbc-section]'));
        expect(field).toBeInstanceOf(HTMLTextAreaElement);
        expect(insertIntoComposer(field as HTMLElement, stickerToken('love'))).toBe(true);
        expect((field as HTMLTextAreaElement).value).toContain('[[s:love]]');
    });

    it('turns sticker and image tokens into visible nodes', () => {
        document.body.innerHTML = '<div class="border-b" data-cbc-comment-id="11">안녕 [[s:love]] [[i:9]]</div>';
        paintTokens(document.body);
        expect(document.querySelector('[data-cbc-media="sticker"]')?.textContent).toBe('😍');
        expect(document.querySelector('[data-cbc-media="sticker"] .cbc-sticker-face')?.textContent).toBe('😍');
        expect(document.querySelector('[data-cbc-media="sticker"]')?.getAttribute('data-cbc-motion')).toBeTruthy();
        expect(document.querySelector('[data-cbc-media="sticker"]')?.childElementCount).toBe(1);
        expect(document.querySelector('[data-cbc-media="image"]')?.getAttribute('src')).toContain('/media/9');
        expect(document.body.textContent).not.toContain('[[s:love]]');
        expect(imageToken(9)).toBe('[[i:9]]');
    });

    it('closes the sticker panel when clicking outside', () => {
        openStickerPanel();
        expect(document.querySelector('[data-cbc-stickers]')).not.toBeNull();
        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true }));
        expect(document.querySelector('[data-cbc-stickers]')).toBeNull();
    });

    it('closes the sticker panel on Escape', () => {
        openStickerPanel();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
        expect(document.querySelector('[data-cbc-stickers]')).toBeNull();
    });

    it('uses the 48 sticker pack when configured', () => {
        openStickerPanel({ ...DEFAULT_CONFIG, stickerPack: '48' });
        expect(document.querySelectorAll('[data-cbc-sticker-id]').length).toBe(SIMPLE_STICKER_IDS.length);
        expect(document.querySelector('[data-cbc-stickers]')?.getAttribute('data-cbc-pack')).toBe('48');
        expect(document.querySelector('[data-cbc-stickers]')?.className).toContain('cbc-stickers--animated');
        expect(document.querySelector('[data-cbc-sticker-id]')?.tagName).toBe('DIV');
        expect(document.querySelector('[data-cbc-sticker-id]')?.getAttribute('role')).toBe('button');
        expect(document.querySelector('.cbc-sticker-icon')?.getAttribute('data-cbc-motion')).toBeTruthy();
        expect(document.querySelector('.cbc-sticker-icon .cbc-sticker-face')?.textContent).toBeTruthy();
        expect(document.querySelector('.cbc-sticker-icon')?.childElementCount).toBe(1);
        expect(document.querySelector('.cbc-sticker-pick')?.getAttribute('data-cbc-motion')).toBeNull();
        expect(document.querySelector('.cbc-sticker-name')?.getAttribute('data-cbc-motion')).toBeNull();
    });

    it('shows only 12 stickers for the smallest pack', () => {
        openStickerPanel({ ...DEFAULT_CONFIG, stickerPack: '12' });
        expect(document.querySelectorAll('[data-cbc-sticker-id]').length).toBe(12);
        expect(document.querySelector('[data-cbc-stickers]')?.getAttribute('data-cbc-pack')).toBe('12');
    });

    it('keeps stickers still when animation is off', () => {
        openStickerPanel({ ...DEFAULT_CONFIG, stickerPack: '48', stickersAnimated: false });
        expect(document.querySelector('[data-cbc-stickers]')?.className).toBe('cbc-stickers');
        expect(document.querySelector('[data-cbc-stickers]')?.className).not.toContain('cbc-stickers--animated');
    });
});

describe('sticker panel blur', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('does not close while G7 steals focus right after open', () => {
        const { textarea } = openStickerPanel();
        textarea.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
        expect(document.querySelector('[data-cbc-stickers]')).not.toBeNull();
    });

    it('closes when the comment box takes focus after the panel is armed', () => {
        const { textarea } = openStickerPanel();
        vi.advanceTimersByTime(STICKER_DISMISS_ARM_MS);
        textarea.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
        expect(document.querySelector('[data-cbc-stickers]')).toBeNull();
    });

    it('closes when a sort button takes focus after the panel is armed', () => {
        const { toolbar } = openStickerPanel();
        vi.advanceTimersByTime(STICKER_DISMISS_ARM_MS);
        toolbar.querySelector('[data-cbc-sort]')?.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
        expect(document.querySelector('[data-cbc-stickers]')).toBeNull();
    });

    it('stays open when a sticker inside the panel is focused', () => {
        openStickerPanel();
        vi.advanceTimersByTime(STICKER_DISMISS_ARM_MS);
        document.querySelector('[data-cbc-sticker-id]')?.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
        expect(document.querySelector('[data-cbc-stickers]')).not.toBeNull();
    });
});

describe('sticker panel placement', () => {
    const box = (left: number, top: number, right: number, bottom: number) => ({ left, top, right, bottom });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('aligns its right edge to the sticker button and grows leftward', () => {
        const place = computePanelPlacement(box(560, 210, 630, 240), box(0, 100, 640, 900), { width: 1280, height: 900 });
        expect(place.width).toBe(PANEL_MAX_WIDTH);
        expect(place.left + place.width).toBe(630);
        expect(place.top).toBe(248);
    });

    it('stays inside the comment card and the viewport when the window narrows', () => {
        const place = computePanelPlacement(box(800, 210, 890, 240), box(20, 100, 880, 900), { width: 900, height: 700 });
        expect(place.left).toBeGreaterThanOrEqual(20);
        expect(place.left + place.width).toBeLessThanOrEqual(880);
        expect(place.left + place.width).toBeLessThanOrEqual(900 - PANEL_MARGIN);
    });

    it('never runs past the right edge even if the button does', () => {
        const place = computePanelPlacement(box(1200, 210, 1300, 240), null, { width: 1000, height: 800 });
        expect(place.left + place.width).toBeLessThanOrEqual(1000 - PANEL_MARGIN);
        expect(place.left).toBeGreaterThanOrEqual(PANEL_MARGIN);
    });

    it('fills the comment area width on narrow screens', () => {
        const place = computePanelPlacement(box(300, 210, 374, 240), box(0, 100, 390, 900), { width: 390, height: 800 });
        expect(place.left).toBe(PANEL_MARGIN);
        expect(place.width).toBe(390 - PANEL_MARGIN * 2);
    });

    it('opens above the toolbar when there is no room below and limits its height', () => {
        const place = computePanelPlacement(box(560, 600, 630, 630), null, { width: 1280, height: 700 });
        expect(place.top).toBeNull();
        expect(place.bottom).toBe(700 - 592);
        expect(place.maxHeight).toBeLessThanOrEqual(600 - 8 - PANEL_MARGIN);
    });

    it('marks the sticker button expanded while the panel is open', () => {
        const { toolbar } = openStickerPanel();
        const button = toolbar.querySelector('[data-cbc-sticker]');
        const panel = document.querySelector<HTMLElement>('[data-cbc-stickers]');
        expect(button?.getAttribute('aria-expanded')).toBe('true');
        expect(button?.getAttribute('aria-controls')).toBe(panel?.id);
        expect(panel?.getAttribute('role')).toBe('dialog');
        expect(panel?.style.width).toMatch(/px$/);
        expect(panel?.style.maxHeight).toMatch(/px$/);
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
        expect(button?.getAttribute('aria-expanded')).toBe('false');
    });
});
