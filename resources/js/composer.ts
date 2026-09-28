import type { PluginConfig } from './config';
import { stickerMotion, stickersForPack, stickerToken } from './stickers';
import { stickerFaceHtml } from './tokens';

export const STICKER_DISMISS_ARM_MS = 280;

type Copy = {
    sticker: string;
    image: string;
    needComposer: string;
    uploadFail: string;
    login: string;
};

export function findComposer(section: Element | null): HTMLElement | null {
    const roots: ParentNode[] = [];
    if (section) {
        roots.push(section);
        if (section.parentElement) {
            roots.push(section.parentElement);
        }
    }
    roots.push(document);

    for (const root of roots) {
        const nodes = [...root.querySelectorAll('textarea, [contenteditable="true"]')];
        const match = nodes.find((node) => {
            if (!(node instanceof HTMLElement)) {
                return false;
            }
            if (node.closest('[data-cbc-toolbar], [data-cbc-stickers]')) {
                return false;
            }
            const rect = node.getBoundingClientRect();
            const visible = rect.width > 60 && rect.height > 24;
            const inComments = Boolean(section && section.contains(node));
            return inComments || visible;
        });
        if (match instanceof HTMLElement) {
            return match;
        }
    }
    return null;
}

export function insertIntoComposer(field: HTMLElement, text: string): boolean {
    const padded = text.startsWith(' ') || text.startsWith('\n') ? text : ` ${text}`;
    if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? start;
        const next = `${field.value.slice(0, start)}${padded}${field.value.slice(end)}`;
        const proto = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(field), 'value');
        proto?.set?.call(field, next);
        field.dispatchEvent(new InputEvent('input', { bubbles: true, data: padded, inputType: 'insertText' }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        const pos = start + padded.length;
        try {
            field.setSelectionRange(pos, pos);
        } catch {
            // some inputs do not support selection
        }
        field.focus();
        return true;
    }
    if (field.isContentEditable) {
        field.focus();
        const ok = document.execCommand('insertText', false, padded);
        if (!ok) {
            field.append(padded);
            field.dispatchEvent(new InputEvent('input', { bubbles: true }));
        }
        return true;
    }
    return false;
}

export function hideComposerUi(): void {
    closeStickerPanel();
    document.querySelector('[data-cbc-file]')?.remove();
}

function closeStickerPanel(panel: Element | null = document.querySelector('[data-cbc-stickers]')): void {
    if (!(panel instanceof HTMLElement)) {
        return;
    }
    panel.dispatchEvent(new Event('cbc-close'));
    panel.remove();
}

export function ensureComposerControls(
    toolbar: HTMLElement,
    section: Element | null,
    config: PluginConfig,
    copy: Copy,
    uploadImage: (file: File) => Promise<{ token: string }>,
): void {
    bindActionButtons(toolbar, section, config, copy, uploadImage);
}

function bindActionButtons(
    toolbar: HTMLElement,
    section: Element | null,
    config: PluginConfig,
    copy: Copy,
    uploadImage: (file: File) => Promise<{ token: string }>,
): void {
    const stickerBtn = toolbar.querySelector<HTMLButtonElement>('[data-cbc-sticker]');
    const imageBtn = toolbar.querySelector<HTMLButtonElement>('[data-cbc-image]');
    if (stickerBtn) {
        stickerBtn.hidden = !config.stickersEnabled;
        stickerBtn.onclick = (event) => {
            event.preventDefault();
            event.stopPropagation();
            toggleStickerPanel(toolbar, section, copy, config);
        };
    }
    if (imageBtn) {
        imageBtn.hidden = !config.imagesEnabled;
        imageBtn.onclick = (event) => {
            event.preventDefault();
            event.stopPropagation();
            pickImage(section, copy, uploadImage);
        };
    }
}

function toggleStickerPanel(
    toolbar: HTMLElement,
    section: Element | null,
    copy: Copy,
    config: PluginConfig,
): void {
    const existing = document.querySelector<HTMLElement>('[data-cbc-stickers]');
    if (existing) {
        closeStickerPanel(existing);
        return;
    }
    const panel = document.createElement('div');
    panel.setAttribute('data-cbc-stickers', '1');
    panel.setAttribute('data-cbc-pack', config.stickerPack);
    panel.className = config.stickersAnimated ? 'cbc-stickers cbc-stickers--animated' : 'cbc-stickers';
    panel.tabIndex = -1;
    panel.id = 'cbc-sticker-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', copy.sticker);
    panel.style.position = 'fixed';
    panel.style.zIndex = '2147483001';
    panel.innerHTML = stickersForPack(config.stickerPack).map((sticker) => (
        `<div role="button" tabindex="0" class="cbc-sticker-pick" data-cbc-sticker-id="${sticker.id}" title="${sticker.label.ko}">`
        + `<span class="cbc-sticker-icon" data-cbc-motion="${stickerMotion(sticker.id)}">${stickerFaceHtml(sticker.emoji)}</span>`
        + `<span class="cbc-sticker-name">${sticker.label.ko}</span>`
        + `</div>`
    )).join('');
    document.body.appendChild(panel);
    placePanel(panel, toolbar, section);
    bindPanelReposition(panel, toolbar, section);
    bindStickerDismiss(panel);
    const stickerBtn = toolbar.querySelector<HTMLElement>('[data-cbc-sticker]');
    if (stickerBtn) {
        stickerBtn.setAttribute('aria-expanded', 'true');
        stickerBtn.setAttribute('aria-controls', panel.id);
        stickerBtn.classList.add('is-open');
        panel.addEventListener('cbc-close', () => {
            stickerBtn.setAttribute('aria-expanded', 'false');
            stickerBtn.removeAttribute('aria-controls');
            stickerBtn.classList.remove('is-open');
        }, { once: true });
    }
    panel.querySelectorAll<HTMLElement>('[data-cbc-sticker-id]').forEach((button) => {
        const pick = (): void => {
            const id = button.getAttribute('data-cbc-sticker-id') ?? '';
            const composer = findComposer(section);
            if (!composer || !insertIntoComposer(composer, stickerToken(id))) {
                window.alert(copy.needComposer);
                return;
            }
            closeStickerPanel(panel);
        };
        button.onclick = pick;
        button.onkeydown = (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                pick();
            }
        };
    });
}

function isStickerUi(target: EventTarget | null): boolean {
    if (!(target instanceof Node)) {
        return false;
    }
    const panel = document.querySelector('[data-cbc-stickers]');
    if (panel instanceof Node && panel.contains(target)) {
        return true;
    }
    return target instanceof Element && Boolean(target.closest('[data-cbc-sticker]'));
}

function bindStickerDismiss(panel: HTMLElement): void {
    const abort = new AbortController();
    const { signal } = abort;
    let armed = false;
    const armTimer = window.setTimeout(() => {
        armed = true;
    }, STICKER_DISMISS_ARM_MS);
    const close = (): void => {
        window.clearTimeout(armTimer);
        closeStickerPanel(panel);
    };
    panel.addEventListener('cbc-close', () => {
        window.clearTimeout(armTimer);
        abort.abort();
    }, { once: true });
    document.addEventListener('pointerdown', (event) => {
        if (isStickerUi(event.target)) {
            return;
        }
        close();
    }, { capture: true, signal });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    }, { capture: true, signal });
    document.addEventListener('focusin', (event) => {
        if (!armed) {
            return;
        }
        if (isStickerUi(event.target)) {
            return;
        }
        close();
    }, { capture: true, signal });
    panel.addEventListener('focusout', (event) => {
        if (!armed) {
            return;
        }
        if (isStickerUi(event.relatedTarget)) {
            return;
        }
        if (!(event.relatedTarget instanceof Node)) {
            return;
        }
        close();
    }, { signal });
}

/** 스티커 창이 화면 가장자리와 띄울 최소 간격(px). */
export const PANEL_MARGIN = 8;
/** 스티커 창이 커질 수 있는 최대 너비(px, 36rem). */
export const PANEL_MAX_WIDTH = 576;
/** 이 너비보다 좁은 화면에서는 댓글 영역 폭을 꽉 채웁니다. */
export const PANEL_NARROW_BREAKPOINT = 640;
const PANEL_GAP = 8;
const PANEL_MIN_HEIGHT = 160;
const PANEL_MAX_HEIGHT = 416;

type Box = { left: number; right: number; top: number; bottom: number };

export type PanelPlacement = {
    left: number;
    width: number;
    top: number | null;
    bottom: number | null;
    maxHeight: number;
};

/**
 * 스티커 창 자리를 계산합니다(순수 함수).
 *
 * - 창의 오른쪽 끝을 버튼(anchor) 오른쪽 끝에 맞추고 왼쪽으로 자랍니다.
 * - 가로는 댓글 카드(bounds)와 화면(viewport - 여백) 안으로 가둡니다.
 * - 좁은 화면에서는 댓글 영역 폭을 꽉 채웁니다.
 * - 아래 공간이 모자라고 위가 더 넓으면 버튼 위로 띄웁니다. 높이는 남은 공간까지만.
 */
export function computePanelPlacement(
    anchor: Box,
    bounds: Box | null,
    viewport: { width: number; height: number },
): PanelPlacement {
    const minX = Math.max(PANEL_MARGIN, bounds ? bounds.left : PANEL_MARGIN);
    const maxX = Math.min(viewport.width - PANEL_MARGIN, bounds ? bounds.right : viewport.width - PANEL_MARGIN);
    // 카드가 화면 밖으로 밀려나 폭이 너무 좁아지면 화면 기준으로 되돌립니다.
    const usable = maxX - minX >= 200
        ? { minX, maxX }
        : { minX: PANEL_MARGIN, maxX: viewport.width - PANEL_MARGIN };
    const room = Math.max(0, usable.maxX - usable.minX);

    let left: number;
    let width: number;
    if (viewport.width < PANEL_NARROW_BREAKPOINT) {
        width = room;
        left = usable.minX;
    } else {
        width = Math.min(PANEL_MAX_WIDTH, room);
        const right = Math.min(usable.maxX, Math.max(usable.minX + width, anchor.right));
        left = Math.max(usable.minX, right - width);
    }

    const below = viewport.height - PANEL_MARGIN - (anchor.bottom + PANEL_GAP);
    const above = anchor.top - PANEL_GAP - PANEL_MARGIN;
    if (below < PANEL_MIN_HEIGHT * 1.5 && above > below) {
        return {
            left,
            width,
            top: null,
            bottom: viewport.height - (anchor.top - PANEL_GAP),
            maxHeight: Math.min(PANEL_MAX_HEIGHT, Math.max(120, above)),
        };
    }
    return {
        left,
        width,
        top: Math.max(PANEL_MARGIN, anchor.bottom + PANEL_GAP),
        bottom: null,
        maxHeight: Math.min(PANEL_MAX_HEIGHT, Math.max(120, below)),
    };
}

function viewportSize(): { width: number; height: number } {
    return {
        width: document.documentElement.clientWidth || window.innerWidth,
        height: window.innerHeight || document.documentElement.clientHeight,
    };
}

function placePanel(panel: HTMLElement, toolbar: HTMLElement, section: Element | null): void {
    const button = toolbar.querySelector<HTMLElement>('[data-cbc-sticker]');
    const anchorEl = button && !button.hidden ? button : toolbar;
    const anchor = anchorEl.getBoundingClientRect();
    const toolbarRect = toolbar.getBoundingClientRect();
    const card = section instanceof HTMLElement && section.isConnected ? section.getBoundingClientRect() : null;
    const place = computePanelPlacement(
        { left: anchor.left, right: anchor.right, top: toolbarRect.top, bottom: toolbarRect.bottom },
        card && card.width > 0 ? card : null,
        viewportSize(),
    );
    panel.style.left = `${Math.round(place.left)}px`;
    panel.style.right = 'auto';
    panel.style.width = `${Math.round(place.width)}px`;
    panel.style.maxHeight = `${Math.round(place.maxHeight)}px`;
    panel.style.top = place.top === null ? 'auto' : `${Math.round(place.top)}px`;
    panel.style.bottom = place.bottom === null ? 'auto' : `${Math.round(place.bottom)}px`;
    panel.dataset.cbcSide = place.top === null ? 'above' : 'below';
}

/** 창 크기가 바뀌거나 스크롤하면 스티커 창 자리를 다시 잡습니다(프레임당 한 번). */
function bindPanelReposition(panel: HTMLElement, toolbar: HTMLElement, section: Element | null): void {
    const abort = new AbortController();
    const { signal } = abort;
    let frame: number | null = null;
    const schedule = (event?: Event): void => {
        // 스티커 창 안쪽 스크롤은 자리와 상관없습니다.
        if (event && event.target instanceof Node && panel.contains(event.target)) {
            return;
        }
        if (frame !== null) {
            return;
        }
        frame = window.requestAnimationFrame(() => {
            frame = null;
            if (panel.isConnected) {
                placePanel(panel, toolbar, section);
            }
        });
    };
    window.addEventListener('resize', schedule, { signal });
    window.addEventListener('scroll', schedule, { capture: true, passive: true, signal });
    window.visualViewport?.addEventListener('resize', schedule, { signal });
    panel.addEventListener('cbc-close', () => {
        if (frame !== null) {
            window.cancelAnimationFrame(frame);
            frame = null;
        }
        abort.abort();
    }, { once: true });
}

function pickImage(
    section: Element | null,
    copy: Copy,
    uploadImage: (file: File) => Promise<{ token: string }>,
): void {
    const composer = findComposer(section);
    if (!composer) {
        window.alert(copy.needComposer);
        return;
    }
    let input = document.querySelector<HTMLInputElement>('[data-cbc-file]');
    if (!input) {
        input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/jpeg,image/png,image/gif,image/webp';
        input.setAttribute('data-cbc-file', '1');
        input.hidden = true;
        document.body.appendChild(input);
    }
    input.onchange = async () => {
        const file = input?.files?.[0];
        input.value = '';
        if (!file) {
            return;
        }
        try {
            const result = await uploadImage(file);
            if (!insertIntoComposer(composer, result.token)) {
                window.alert(copy.needComposer);
            }
        } catch (error) {
            window.alert(error instanceof Error ? error.message : copy.uploadFail);
        }
    };
    input.click();
}
