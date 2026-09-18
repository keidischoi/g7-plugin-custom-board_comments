import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ensureComposerControls, findComposer, insertIntoComposer, STICKER_DISMISS_ARM_MS } from './composer';
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
        expect(document.querySelector('[data-cbc-media="sticker"]')?.getAttribute('data-cbc-motion')).toBeTruthy();
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

    it('uses the simple sticker pack when configured', () => {
        openStickerPanel({ ...DEFAULT_CONFIG, stickerPack: 'simple' });
        expect(document.querySelectorAll('[data-cbc-sticker-id]').length).toBe(SIMPLE_STICKER_IDS.length);
        expect(document.querySelector('[data-cbc-stickers]')?.className).toContain('cbc-stickers--animated');
    });

    it('keeps stickers still when animation is off', () => {
        openStickerPanel({ ...DEFAULT_CONFIG, stickerPack: 'simple', stickersAnimated: false });
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
