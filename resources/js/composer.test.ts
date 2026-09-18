import { describe, expect, it } from 'vitest';
import { findComposer, insertIntoComposer } from './composer';
import { imageToken, stickerToken } from './stickers';
import { paintTokens } from './tokens';

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
});
