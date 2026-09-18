import { describe, expect, it } from 'vitest';
import { ensureComposerControls, findComposer, insertIntoComposer } from './composer';
import { imageToken, stickerToken } from './stickers';
import { paintTokens } from './tokens';
import { DEFAULT_CONFIG } from './config';

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
        expect(document.querySelector('[data-cbc-media="image"]')?.getAttribute('src')).toContain('/media/9');
        expect(document.body.textContent).not.toContain('[[s:love]]');
        expect(imageToken(9)).toBe('[[i:9]]');
    });

    it('closes the sticker panel when focus moves away', () => {
        document.body.innerHTML = `
            <div data-cbc-section="1">
                <h3>댓글</h3>
                <textarea></textarea>
            </div>
        `;
        const toolbar = document.createElement('div');
        toolbar.innerHTML = '<button type="button" data-cbc-sticker="1">스티커</button>';
        document.body.appendChild(toolbar);
        const copy = {
            sticker: '스티커',
            image: '이미지',
            needComposer: '없음',
            uploadFail: '실패',
            login: '로그인',
        };
        ensureComposerControls(toolbar, document.querySelector('[data-cbc-section]'), DEFAULT_CONFIG, copy, async () => ({ token: '' }));
        toolbar.querySelector<HTMLButtonElement>('[data-cbc-sticker]')?.click();
        expect(document.querySelector('[data-cbc-stickers]')).not.toBeNull();
        document.body.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true, cancelable: true }));
        expect(document.querySelector('[data-cbc-stickers]')).toBeNull();
    });
});
