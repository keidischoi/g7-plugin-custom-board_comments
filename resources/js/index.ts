import '../css/plugin.css';
import {
    appliesToBoard,
    normalizeConfig,
    PLUGIN_ID,
    readInlineConfig,
    type PluginConfig,
    type SortKind,
} from './config';
import {
    applySort,
    bindCommentRows,
    bindToolbarSort,
    commentRows,
    ensureToolbar,
    findCommentSection,
    hideToolbar,
    localeCopy,
    paintLikes,
    placeToolbar,
    syncSortButtons,
    type LikeSummary,
} from './enhance';
import { boardPostApiUrl, parseBoardShowPath, isBoardPostApi, unwrapApiData } from './url';
import { mutationNeedsCommentSync } from './observe';
import { ensureComposerControls } from './composer';
import { paintTokens } from './tokens';
import { pluginFetch } from './auth';
import type { BoardComment } from './sort';

type Runtime = {
    stop: () => void;
};

declare global {
    interface Window {
        __g7CustomBoardComments?: Runtime;
        G7Core?: {
            dispatch?: (detail: unknown) => void;
            api?: { getToken?: () => string | null };
        };
    }
}

const API_PREFIX = `/api/plugins/${PLUGIN_ID}`;

function asComments(raw: unknown): BoardComment[] {
    let list = raw;
    if (list && typeof list === 'object' && !Array.isArray(list) && Array.isArray((list as { data?: unknown }).data)) {
        list = (list as { data: unknown[] }).data;
    }
    if (!Array.isArray(list)) {
        return [];
    }
    return list.map((item) => {
        const row = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        return {
            id: Number(row.id) || 0,
            parent_id: row.parent_id == null ? null : Number(row.parent_id),
            depth: Number(row.depth) || 0,
            created_at: String(row.created_at ?? ''),
            content: row.content == null ? undefined : String(row.content),
            author: row.author && typeof row.author === 'object'
                ? { name: String((row.author as { name?: unknown }).name ?? '') }
                : null,
        };
    }).filter((comment) => comment.id > 0);
}

async function fetchJson(url: string, init?: RequestInit): Promise<unknown> {
    const response = await pluginFetch(url, init);
    const payload = await response.json().catch(() => null);
    if (!response.ok) {
        const message = payload && typeof payload === 'object' && 'message' in payload
            ? String((payload as { message: unknown }).message)
            : `HTTP ${response.status}`;
        throw new Error(message);
    }
    return payload;
}

function unwrapData(payload: unknown): Record<string, unknown> {
    if (payload && typeof payload === 'object' && 'data' in payload) {
        const data = (payload as { data: unknown }).data;
        if (data && typeof data === 'object') {
            return data as Record<string, unknown>;
        }
    }
    return (payload && typeof payload === 'object') ? payload as Record<string, unknown> : {};
}

function emptySummary(): LikeSummary {
    return { counts: {}, liked: [], best: [] };
}

function parseSummary(payload: unknown): LikeSummary {
    const data = unwrapData(payload);
    const countsRaw = data.counts && typeof data.counts === 'object' ? data.counts as Record<string, unknown> : {};
    const counts: Record<string, number> = {};
    for (const [id, value] of Object.entries(countsRaw)) {
        counts[id] = Number(value) || 0;
    }
    return {
        counts,
        liked: Array.isArray(data.liked) ? data.liked.map(Number) : [],
        best: Array.isArray(data.best) ? data.best.map(Number) : [],
    };
}

function requestUrl(input: RequestInfo | URL): string {
    if (typeof input === 'string') {
        return input;
    }
    if (input instanceof URL) {
        return input.toString();
    }
    return input.url;
}

function boot(): void {
    window.__g7CustomBoardComments?.stop();

    let comments: BoardComment[] = [];
    let boardId = 0;
    let summary = emptySummary();
    let config = readInlineConfig();
    let sort: SortKind = config.defaultSort;
    let observer: MutationObserver | null = null;
    let fetchPatched = false;
    let xhrPatched = false;
    let historyPatched = false;
    const originalFetch = window.fetch.bind(window);
    const originalXhrOpen = XMLHttpRequest.prototype.open;
    const originalXhrSend = XMLHttpRequest.prototype.send;
    const originalPush = history.pushState.bind(history);
    const originalReplace = history.replaceState.bind(history);
    let stopped = false;
    let loadingPost = false;
    let postFetched = false;
    let likesLoaded = false;
    let syncing = false;

    const page = () => parseBoardShowPath(window.location.pathname);
    const copy = () => localeCopy(document.documentElement.lang || 'ko');

    const ingestPost = (payload: unknown): boolean => {
        const data = unwrapApiData(payload);
        if (!data) {
            return false;
        }
        comments = asComments(data.comments);
        boardId = Number(data.board_id ?? (data.board && typeof data.board === 'object' ? (data.board as { id?: unknown }).id : 0)) || 0;
        return true;
    };

    const sync = async (): Promise<void> => {
        if (stopped || syncing) {
            return;
        }
        syncing = true;
        try {
        const ref = page();
        document.documentElement.classList.toggle('cbc-styled', config.styleEnabled);
        document.documentElement.classList.toggle('cbc-stickers-animated', config.stickersAnimated);
        if (!ref || !config.enabled || !appliesToBoard(ref.slug, config.boardSlugs)) {
            hideToolbar();
            return;
        }

        if (!postFetched && comments.length === 0 && !loadingPost) {
            postFetched = true;
            loadingPost = true;
            try {
                ingestPost(await fetchJson(boardPostApiUrl(ref.slug, ref.postId)));
            } catch {
                // keep empty comments; toolbar can still render
            } finally {
                loadingPost = false;
            }
        }

        const section = findCommentSection();
        const toolbar = ensureToolbar(section, sort, copy());
        bindToolbarSort(toolbar, (next) => {
            sort = next;
            syncSortButtons(toolbar, sort);
            const liveSection = findCommentSection();
            const paint = (): void => {
                if (!liveSection || comments.length === 0) {
                    return;
                }
                bindCommentRows(liveSection, comments);
                applySort(liveSection, comments, sort, summary, config);
            };
            paint();
            window.requestAnimationFrame(paint);
            void sync();
        });
        ensureComposerControls(toolbar, section, config, copy(), async (file) => {
            const body = new FormData();
            body.append('file', file);
            body.append('post_id', String(ref.postId));
            const response = await pluginFetch(`${API_PREFIX}/media`, {
                method: 'POST',
                body,
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok) {
                const message = payload && typeof payload === 'object' && 'message' in payload
                    ? String((payload as { message: unknown }).message)
                    : copy().uploadFail;
                throw new Error(message);
            }
            const data = unwrapData(payload);
            return { token: String(data.token ?? '') };
        });

        if (!section || comments.length === 0) {
            if (section) {
                paintTokens(section);
            }
            return;
        }

        bindCommentRows(section, comments);
        if (!likesLoaded) {
            try {
                summary = parseSummary(await fetchJson(`${API_PREFIX}/posts/${ref.postId}/likes?slug=${encodeURIComponent(ref.slug)}`));
            } catch {
                summary = emptySummary();
            }
            likesLoaded = true;
        }
        applySort(section, comments, sort, summary, config);
        paintLikes(section, summary, config, copy());
        paintTokens(section);

        section.querySelectorAll<HTMLButtonElement>('[data-cbc-like]').forEach((button) => {
            button.onclick = async () => {
                const row = button.closest('[data-cbc-comment-id]');
                const commentId = Number(row?.getAttribute('data-cbc-comment-id'));
                if (!commentId) {
                    return;
                }
                try {
                    const result = unwrapData(await fetchJson(`${API_PREFIX}/comments/${commentId}/like`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            post_id: ref.postId,
                            board_id: boardId,
                        }),
                    }));
                    summary.counts[String(commentId)] = Number(result.count) || 0;
                    const liked = new Set(summary.liked);
                    if (result.liked) {
                        liked.add(commentId);
                    } else {
                        liked.delete(commentId);
                    }
                    summary.liked = [...liked];
                    await sync();
                } catch (error) {
                    window.alert(error instanceof Error ? error.message : copy().login);
                }
            };
        });
        } finally {
            syncing = false;
        }
    };

    let syncTimer: number | null = null;
    let retryTimer: number | null = null;
    let retries = 0;
    const scheduleSync = (): void => {
        if (stopped) {
            return;
        }
        if (syncTimer !== null) {
            return;
        }
        syncTimer = window.setTimeout(() => {
            syncTimer = null;
            void sync().catch(() => undefined);
        }, 50);
    };
    const commentsReady = (): boolean => {
        const toolbar = document.querySelector('[data-cbc-toolbar]');
        const section = findCommentSection();
        if (!toolbar || !section) {
            return false;
        }
        if (comments.length === 0) {
            return true;
        }
        return commentRows(section).some((row) => row.hasAttribute('data-cbc-comment-id'));
    };
    const retryUntilVisible = (): void => {
        retries = 0;
        if (retryTimer !== null) {
            window.clearInterval(retryTimer);
        }
        retryTimer = window.setInterval(() => {
            if (stopped) {
                return;
            }
            scheduleSync();
            retries += 1;
            if (commentsReady() || retries >= 48) {
                if (retryTimer !== null) {
                    window.clearInterval(retryTimer);
                    retryTimer = null;
                }
            }
        }, 250);
        scheduleSync();
    };

    window.fetch = async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
        const response = await originalFetch(input, init);
        try {
            if (isBoardPostApi(requestUrl(input))) {
                void response.clone().json().then((payload) => {
                    if (ingestPost(payload)) {
                        retryUntilVisible();
                    }
                }).catch(() => undefined);
            }
        } catch {
            // ignore parse errors
        }
        return response;
    };
    fetchPatched = true;

    XMLHttpRequest.prototype.open = function (this: XMLHttpRequest, ...args: unknown[]) {
        (this as XMLHttpRequest & { __cbcUrl?: string }).__cbcUrl = String(args[1]);
        return Reflect.apply(originalXhrOpen, this, args);
    };
    XMLHttpRequest.prototype.send = function (this: XMLHttpRequest, ...args: unknown[]) {
        this.addEventListener('load', () => {
            const url = (this as XMLHttpRequest & { __cbcUrl?: string }).__cbcUrl ?? '';
            if (!isBoardPostApi(url)) {
                return;
            }
            try {
                if (ingestPost(JSON.parse(this.responseText))) {
                    retryUntilVisible();
                }
            } catch {
                // ignore
            }
        });
        return originalXhrSend.apply(this, args as []);
    };
    xhrPatched = true;

    const onRoute = (): void => {
        comments = [];
        boardId = 0;
        summary = emptySummary();
        postFetched = false;
        likesLoaded = false;
        config = readInlineConfig();
        sort = config.defaultSort;
        retryUntilVisible();
    };
    window.addEventListener('popstate', onRoute);
    history.pushState = function (...args: Parameters<History['pushState']>) {
        const result = originalPush(...args);
        onRoute();
        return result;
    };
    history.replaceState = function (...args: Parameters<History['replaceState']>) {
        const result = originalReplace(...args);
        onRoute();
        return result;
    };
    historyPatched = true;

    const reposition = (): void => {
        const toolbar = document.querySelector<HTMLElement>('[data-cbc-toolbar]');
        if (!toolbar) {
            return;
        }
        placeToolbar(toolbar, findCommentSection());
    };
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);

    observer = new MutationObserver((mutations) => {
        if (mutationNeedsCommentSync(mutations)) {
            scheduleSync();
        }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
    document.documentElement.setAttribute('data-cbc-boot', '0.1.14');

    void (async () => {
        try {
            config = normalizeConfig(unwrapData(await fetchJson(`${API_PREFIX}/settings`)));
            sort = config.defaultSort;
        } catch {
            config = readInlineConfig();
        }
        retryUntilVisible();
    })();

    window.__g7CustomBoardComments = {
        stop: () => {
            stopped = true;
            observer?.disconnect();
            window.removeEventListener('popstate', onRoute);
            if (syncTimer !== null) {
                window.clearTimeout(syncTimer);
                syncTimer = null;
            }
            if (retryTimer !== null) {
                window.clearInterval(retryTimer);
                retryTimer = null;
            }
            if (fetchPatched) {
                window.fetch = originalFetch;
                fetchPatched = false;
            }
            if (xhrPatched) {
                XMLHttpRequest.prototype.open = originalXhrOpen;
                XMLHttpRequest.prototype.send = originalXhrSend;
                xhrPatched = false;
            }
            if (historyPatched) {
                history.pushState = originalPush;
                history.replaceState = originalReplace;
                historyPatched = false;
            }
            window.removeEventListener('scroll', reposition, true);
            window.removeEventListener('resize', reposition);
            hideToolbar();
            document.documentElement.removeAttribute('data-cbc-boot');
        },
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

window.G7Core?.dispatch?.({ handler: `${PLUGIN_ID}.refresh` });
