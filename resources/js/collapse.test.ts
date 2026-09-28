import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindToolbarCollapse, COLLAPSE_DELAY_MS, unbindToolbarCollapse } from './collapse';
import { ensureToolbar, hideToolbar } from './enhance';
import { ensureComposerControls } from './composer';
import { DEFAULT_CONFIG, normalizeConfig } from './config';

const copy = {
    like: '추천',
    liked: '추천함',
    best: '베스트',
    latest: '최신순',
    oldest: '등록순',
    popular: '추천순',
    sortLabel: '댓글 정렬',
    sticker: '스티커',
    image: '이미지',
    needComposer: '없음',
    uploadFail: '실패',
    login: '로그인',
};

function pointer(type: string, pointerType: string, target: EventTarget): void {
    const event = new Event(type, { bubbles: type !== 'pointerenter' && type !== 'pointerleave', cancelable: true });
    Object.defineProperty(event, 'pointerType', { value: pointerType });
    target.dispatchEvent(event);
}

function setup(config = DEFAULT_CONFIG): HTMLElement {
    document.body.innerHTML = `
        <div data-cbc-section="1"><h3>댓글</h3><textarea></textarea></div>
        <button type="button" id="outside">밖</button>
    `;
    const section = document.querySelector('[data-cbc-section]');
    const toolbar = ensureToolbar(section, 'latest', copy);
    ensureComposerControls(toolbar, section, config, copy, async () => ({ token: '' }));
    bindToolbarCollapse(toolbar, config.toolbarCollapsed);
    return toolbar;
}

describe('toolbar collapse', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        hideToolbar();
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('reads toolbar_collapsed from settings (default on)', () => {
        expect(DEFAULT_CONFIG.toolbarCollapsed).toBe(true);
        expect(normalizeConfig({}).toolbarCollapsed).toBe(true);
        expect(normalizeConfig({ toolbar_collapsed: '0' }).toolbarCollapsed).toBe(false);
        expect(normalizeConfig({ toolbarCollapsed: false }).toolbarCollapsed).toBe(false);
    });

    it('starts collapsed with aria-expanded=false and only the sticker visible', () => {
        const toolbar = setup();
        expect(toolbar.classList.contains('cbc-toolbar--collapsible')).toBe(true);
        expect(toolbar.classList.contains('is-collapsed')).toBe(true);
        expect(toolbar.getAttribute('aria-expanded')).toBe('false');
        expect(toolbar.querySelector<HTMLElement>('[data-cbc-sticker]')?.hidden).toBe(false);
    });

    it('expands on mouse hover and collapses after the delay when the pointer leaves', () => {
        const toolbar = setup();
        pointer('pointerenter', 'mouse', toolbar);
        expect(toolbar.getAttribute('aria-expanded')).toBe('true');
        expect(toolbar.classList.contains('is-collapsed')).toBe(false);

        pointer('pointerleave', 'mouse', toolbar);
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS - 50);
        expect(toolbar.getAttribute('aria-expanded')).toBe('true');
        pointer('pointerenter', 'mouse', toolbar);
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS * 2);
        expect(toolbar.getAttribute('aria-expanded')).toBe('true');

        pointer('pointerleave', 'mouse', toolbar);
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS);
        expect(toolbar.getAttribute('aria-expanded')).toBe('false');
    });

    it('stays open while keyboard focus is inside and collapses when focus leaves', () => {
        const toolbar = setup();
        toolbar.querySelector<HTMLButtonElement>('[data-cbc-sticker]')?.focus();
        expect(toolbar.getAttribute('aria-expanded')).toBe('true');
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS * 2);
        expect(toolbar.getAttribute('aria-expanded')).toBe('true');

        toolbar.querySelector<HTMLButtonElement>('[data-cbc-sort="oldest"]')?.focus();
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS * 2);
        expect(toolbar.getAttribute('aria-expanded')).toBe('true');

        document.getElementById('outside')?.focus();
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS);
        expect(toolbar.getAttribute('aria-expanded')).toBe('false');
    });

    it('does not collapse while the sticker panel opened from it is open', () => {
        const toolbar = setup();
        pointer('pointerenter', 'mouse', toolbar);
        toolbar.querySelector<HTMLButtonElement>('[data-cbc-sticker]')?.click();
        expect(document.querySelector('[data-cbc-stickers]')).not.toBeNull();

        pointer('pointerleave', 'mouse', toolbar);
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS * 3);
        expect(toolbar.getAttribute('aria-expanded')).toBe('true');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(document.querySelector('[data-cbc-stickers]')).toBeNull();
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS);
        expect(toolbar.getAttribute('aria-expanded')).toBe('false');
    });

    it('expands on touch tap and collapses when tapping outside', () => {
        const toolbar = setup();
        pointer('pointerdown', 'touch', toolbar);
        // 터치는 탭 뒤 pointerleave가 와도 접지 않습니다.
        pointer('pointerleave', 'touch', toolbar);
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS * 2);
        expect(toolbar.getAttribute('aria-expanded')).toBe('true');

        pointer('pointerdown', 'touch', document.getElementById('outside') as HTMLElement);
        vi.advanceTimersByTime(COLLAPSE_DELAY_MS);
        expect(toolbar.getAttribute('aria-expanded')).toBe('false');
    });

    it('stays fully expanded when the setting is off or stickers are disabled', () => {
        const off = setup({ ...DEFAULT_CONFIG, toolbarCollapsed: false });
        expect(off.classList.contains('is-collapsed')).toBe(false);
        expect(off.hasAttribute('aria-expanded')).toBe(false);
        hideToolbar();

        const noStickers = setup({ ...DEFAULT_CONFIG, stickersEnabled: false });
        expect(noStickers.classList.contains('cbc-toolbar--collapsible')).toBe(false);
        expect(noStickers.classList.contains('is-collapsed')).toBe(false);
    });

    it('re-binding is idempotent and turning the setting off expands immediately', () => {
        const toolbar = setup();
        bindToolbarCollapse(toolbar, true);
        expect(toolbar.classList.contains('is-collapsed')).toBe(true);
        bindToolbarCollapse(toolbar, false);
        expect(toolbar.classList.contains('is-collapsed')).toBe(false);
        expect(toolbar.classList.contains('cbc-toolbar--collapsible')).toBe(false);
        unbindToolbarCollapse(toolbar);
    });

    it('anchors the right edge so the toolbar grows leftward when expanding', () => {
        const toolbar = setup();
        expect(toolbar.style.left).toBe('auto');
        expect(toolbar.style.right).toMatch(/px$/);
    });
});
