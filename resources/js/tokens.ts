import { PLUGIN_ID } from './config';
import { STICKERS, stickerById, stickerMotion } from './stickers';

export const STICKER_TOKEN = /\[\[s:([a-z0-9-]+)\]\]/g;
export const IMAGE_TOKEN = /\[\[i:(\d+)\]\]/g;

export function imageUrl(id: number): string {
    return `/api/plugins/${PLUGIN_ID}/media/${id}`;
}

export function paintTokens(root: ParentNode): void {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const texts: Text[] = [];
    let current = walker.nextNode();
    while (current) {
        if (
            current instanceof Text
            && current.parentElement
            && !current.parentElement.closest('[data-cbc-toolbar], [data-cbc-stickers], [data-cbc-like], [data-cbc-best], [data-cbc-media]')
            && (STICKER_TOKEN.test(current.textContent ?? '') || IMAGE_TOKEN.test(current.textContent ?? ''))
        ) {
            texts.push(current);
        }
        STICKER_TOKEN.lastIndex = 0;
        IMAGE_TOKEN.lastIndex = 0;
        current = walker.nextNode();
    }

    for (const node of texts) {
        replaceTextNode(node);
    }
}

function replaceTextNode(node: Text): void {
    const source = node.textContent ?? '';
    const parts = source.split(/(\[\[s:[a-z0-9-]+\]\]|\[\[i:\d+\]\])/g);
    if (parts.length === 1) {
        return;
    }
    const fragment = document.createDocumentFragment();
    for (const part of parts) {
        const sticker = part.match(/^\[\[s:([a-z0-9-]+)\]\]$/);
        const image = part.match(/^\[\[i:(\d+)\]\]$/);
        if (sticker) {
            fragment.appendChild(stickerNode(sticker[1] ?? ''));
        } else if (image) {
            fragment.appendChild(imageNode(Number(image[1])));
        } else if (part !== '') {
            fragment.appendChild(document.createTextNode(part));
        }
    }
    node.parentNode?.replaceChild(fragment, node);
}

export function stickerFaceHtml(emoji: string): string {
    return `<span class="cbc-sticker-face" aria-hidden="true">${emoji}</span>`;
}

export function createStickerFace(emoji: string): HTMLSpanElement {
    const face = document.createElement('span');
    face.className = 'cbc-sticker-face';
    face.setAttribute('aria-hidden', 'true');
    face.textContent = emoji;
    return face;
}

function stickerNode(id: string): HTMLElement {
    const sticker = stickerById(id);
    const span = document.createElement('span');
    span.setAttribute('data-cbc-media', 'sticker');
    span.className = 'cbc-sticker';
    span.setAttribute('data-cbc-motion', stickerMotion(id));
    span.title = sticker?.label.ko ?? id;
    span.appendChild(createStickerFace(sticker?.emoji ?? STICKERS[0]?.emoji ?? '😊'));
    return span;
}

function imageNode(id: number): HTMLImageElement {
    const img = document.createElement('img');
    img.setAttribute('data-cbc-media', 'image');
    img.className = 'cbc-image';
    img.alt = '';
    img.loading = 'lazy';
    img.src = imageUrl(id);
    img.style.cursor = 'zoom-in';
    img.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        const box = document.getElementById('cbc_image_lightbox') || (() => {
            const el = document.createElement('div');
            el.id = 'cbc_image_lightbox';
            el.style.cssText = 'position:fixed;inset:0;z-index:2147483646;background:rgba(0,0,0,.82);display:flex;align-items:center;justify-content:center;padding:24px;cursor:zoom-out';
            const big = document.createElement('img');
            big.style.cssText = 'max-width:min(96vw,1400px);max-height:92vh;object-fit:contain;border-radius:12px';
            el.appendChild(big);
            el.addEventListener('click', () => el.remove());
            document.body.appendChild(el);
            return el;
        })();
        const big = box.querySelector('img');
        if (big) big.setAttribute('src', img.src);
        box.style.display = 'flex';
        document.body.appendChild(box);
    });
    img.addEventListener('error', () => {
        img.replaceWith(Object.assign(document.createElement('span'), {
            className: 'cbc-image-missing',
            textContent: '이미지 없음',
        }));
    });
    return img;
}
