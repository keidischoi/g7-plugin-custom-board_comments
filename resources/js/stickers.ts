export type Sticker = {
    id: string;
    emoji: string;
    label: { ko: string; en: string };
};

export const STICKERS: Sticker[] = [
    { id: 'love', emoji: '😍', label: { ko: '사랑', en: 'Love' } },
    { id: 'heart', emoji: '❤️', label: { ko: '하트', en: 'Heart' } },
    { id: 'like', emoji: '👍', label: { ko: '좋아요', en: 'Like' } },
    { id: 'ok', emoji: '😊', label: { ko: '미소', en: 'Smile' } },
    { id: 'lol', emoji: '😂', label: { ko: '웃겨', en: 'LOL' } },
    { id: 'wow', emoji: '😮', label: { ko: '놀람', en: 'Wow' } },
    { id: 'sad', emoji: '😢', label: { ko: '슬픔', en: 'Sad' } },
    { id: 'angry', emoji: '😡', label: { ko: '화남', en: 'Angry' } },
    { id: 'thanks', emoji: '🙏', label: { ko: '감사', en: 'Thanks' } },
    { id: 'party', emoji: '🎉', label: { ko: '축하', en: 'Party' } },
    { id: 'fire', emoji: '🔥', label: { ko: '대박', en: 'Fire' } },
    { id: 'best', emoji: '🏆', label: { ko: '최고', en: 'Best' } },
];

export function stickerById(id: string): Sticker | undefined {
    return STICKERS.find((item) => item.id === id);
}

export function stickerToken(id: string): string {
    return `[[s:${id}]]`;
}

export function imageToken(id: number): string {
    return `[[i:${id}]]`;
}
