export const PLUGIN_ID = 'g7-plugin-custom-board_comments';
export const SORTS = ['latest', 'oldest', 'popular'] as const;
export const STICKER_PACKS = ['12', '24', '48', '96', '192', '384', 'full'] as const;

export type SortKind = (typeof SORTS)[number];
export type StickerPack = (typeof STICKER_PACKS)[number];

export type PluginConfig = {
    enabled: boolean;
    allowGuestLikes: boolean;
    bestEnabled: boolean;
    bestThreshold: number;
    bestLimit: number;
    defaultSort: SortKind;
    boardSlugs: string[];
    styleEnabled: boolean;
    stickersEnabled: boolean;
    stickerPack: StickerPack;
    stickersAnimated: boolean;
    imagesEnabled: boolean;
};

export const DEFAULT_CONFIG: PluginConfig = {
    enabled: true,
    allowGuestLikes: false,
    bestEnabled: true,
    bestThreshold: 5,
    bestLimit: 3,
    defaultSort: 'latest',
    boardSlugs: ['free'],
    styleEnabled: true,
    stickersEnabled: true,
    stickerPack: 'full',
    stickersAnimated: true,
    imagesEnabled: true,
};

type G7Window = Window & {
    G7Config?: {
        plugins?: Record<string, unknown>;
    };
};

export function parseSlugs(raw: unknown): string[] {
    const parts = Array.isArray(raw)
        ? raw
        : String(raw ?? '').split(/[\s,]+/);
    const slugs: string[] = [];
    for (const part of parts) {
        const slug = String(part).trim().toLowerCase();
        if (!slug || slugs.includes(slug)) {
            continue;
        }
        if (!/^[a-z0-9][a-z0-9_-]{0,80}$/.test(slug)) {
            continue;
        }
        slugs.push(slug);
    }
    return slugs;
}

export function appliesToBoard(slug: string, slugs: string[]): boolean {
    return slugs.length === 0 || slugs.includes(slug.trim().toLowerCase());
}

function resolveSlugs(input: Record<string, unknown>): string[] {
    if (Object.prototype.hasOwnProperty.call(input, 'board_slugs')) {
        return parseSlugs(input.board_slugs);
    }
    if (Object.prototype.hasOwnProperty.call(input, 'boardSlugs')) {
        return parseSlugs(input.boardSlugs);
    }
    return [...DEFAULT_CONFIG.boardSlugs];
}

export function normalizeConfig(raw: unknown): PluginConfig {
    const input = (raw && typeof raw === 'object') ? raw as Record<string, unknown> : {};
    const sort = String(input.default_sort ?? input.defaultSort ?? DEFAULT_CONFIG.defaultSort);
    const pack = String(input.sticker_pack ?? input.stickerPack ?? DEFAULT_CONFIG.stickerPack);
    return {
        enabled: boolish(input.enabled, true),
        allowGuestLikes: boolish(input.allow_guest_likes ?? input.allowGuestLikes, false),
        bestEnabled: boolish(input.best_enabled ?? input.bestEnabled, true),
        bestThreshold: clampInt(input.best_threshold ?? input.bestThreshold, 1, 999, 5),
        bestLimit: clampInt(input.best_limit ?? input.bestLimit, 1, 20, 3),
        defaultSort: (SORTS as readonly string[]).includes(sort) ? sort as SortKind : 'latest',
        boardSlugs: resolveSlugs(input),
        styleEnabled: boolish(input.style_enabled ?? input.styleEnabled, true),
        stickersEnabled: boolish(input.stickers_enabled ?? input.stickersEnabled, true),
        stickerPack: normalizeStickerPack(pack),
        stickersAnimated: boolish(input.stickers_animated ?? input.stickersAnimated, true),
        imagesEnabled: boolish(input.images_enabled ?? input.imagesEnabled, true),
    };
}

export function readInlineConfig(win: G7Window = window): PluginConfig {
    return normalizeConfig(win.G7Config?.plugins?.[PLUGIN_ID]);
}

export function normalizeStickerPack(raw: unknown): StickerPack {
    const text = String(raw ?? '').trim().toLowerCase();
    if (text === 'simple') {
        return '48';
    }
    if (text === 'all') {
        return 'full';
    }
    if ((STICKER_PACKS as readonly string[]).includes(text)) {
        return text as StickerPack;
    }
    return 'full';
}

function boolish(value: unknown, fallback: boolean): boolean {
    if (value === undefined || value === null || value === '') {
        return fallback;
    }
    if (typeof value === 'boolean') {
        return value;
    }
    if (typeof value === 'number') {
        return value !== 0;
    }
    const text = String(value).trim().toLowerCase();
    return !['0', 'false', 'off', 'no'].includes(text);
}

function clampInt(value: unknown, min: number, max: number, fallback: number): number {
    const number = Number(value);
    if (!Number.isFinite(number)) {
        return fallback;
    }
    return Math.min(max, Math.max(min, Math.round(number)));
}
