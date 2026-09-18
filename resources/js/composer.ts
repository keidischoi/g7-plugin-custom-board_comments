import type { PluginConfig } from './config';
import { stickerMotion, stickersForPack, stickerToken } from './stickers';

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
    panel.className = config.stickersAnimated ? 'cbc-stickers cbc-stickers--animated' : 'cbc-stickers';
    panel.tabIndex = -1;
    panel.style.position = 'fixed';
    panel.style.zIndex = '2147483001';
    panel.innerHTML = stickersForPack(config.stickerPack).map((sticker) => (
        `<div role="button" tabindex="0" class="cbc-sticker-pick" data-cbc-sticker-id="${sticker.id}" title="${sticker.label.ko}">`
        + `<span class="cbc-sticker-icon" data-cbc-motion="${stickerMotion(sticker.id)}">${sticker.emoji}</span>`
        + `<span class="cbc-sticker-name">${sticker.label.ko}</span>`
        + `</div>`
    )).join('');
    document.body.appendChild(panel);
    placePanel(panel, toolbar);
    bindStickerDismiss(panel);
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

function placePanel(panel: HTMLElement, toolbar: HTMLElement): void {
    const rect = toolbar.getBoundingClientRect();
    panel.style.top = `${Math.max(8, rect.bottom + 8)}px`;
    panel.style.left = `${Math.max(8, Math.min(window.innerWidth - 420, rect.left))}px`;
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
