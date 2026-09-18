import { PLUGIN_ID } from './config';
import { STICKERS, stickerById } from './stickers';

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

function stickerNode(id: string): HTMLElement {
    const sticker = stickerById(id);
    const span = document.createElement('span');
    span.setAttribute('data-cbc-media', 'sticker');
    span.className = 'cbc-sticker';
    span.textContent = sticker?.emoji ?? STICKERS[0]?.emoji ?? '😊';
    span.title = sticker?.label.ko ?? id;
    return span;
}

function imageNode(id: number): HTMLImageElement {
    const img = document.createElement('img');
    img.setAttribute('data-cbc-media', 'image');
    img.className = 'cbc-image';
    img.alt = '댓글 이미지';
    img.loading = 'lazy';
    img.src = imageUrl(id);
    return img;
}
