export type BoardShowRef = {
    slug: string;
    postId: number;
};

export function parseBoardShowPath(pathname: string): BoardShowRef | null {
    const match = pathname.match(/(?:^|\/)board\/([^/]+)\/(\d+)(?:\/|$)/);
    if (!match) {
        return null;
    }
    const slug = decodeURIComponent(match[1] ?? '').trim();
    const postId = Number(match[2]);
    if (!slug || !Number.isInteger(postId) || postId <= 0) {
        return null;
    }
    return { slug, postId };
}

export function isBoardPostApi(url: string): boolean {
    return /\/api\/modules\/sirsoft-board\/boards\/[^/?#]+\/posts\/\d+(?:[?#]|$)/.test(url);
}

export function unwrapApiData(payload: unknown): Record<string, unknown> | null {
    if (!payload || typeof payload !== 'object') {
        return null;
    }
    const root = payload as Record<string, unknown>;
    const nested = root.data;
    if (nested && typeof nested === 'object' && !Array.isArray(nested)) {
        const nestedRecord = nested as Record<string, unknown>;
        if (Array.isArray(nestedRecord.comments) || nestedRecord.id) {
            return nestedRecord;
        }
    }
    if (Array.isArray(root.comments) || root.id) {
        return root;
    }
    return null;
}
