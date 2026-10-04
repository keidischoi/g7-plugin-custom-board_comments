/**
 * 0.2.0 바꾸기 모드: 게시글 상세의 상자([data-cbc-cc-post])에 custom-comments 댓글을 붙입니다.
 * 상자는 서버가 레이아웃에 더하고(LayoutSwap), 보일지는 데이터 소스(cbc_board_comments.replace)가 정합니다.
 */
export const TARGET_TYPE = 'board_post';
export const COMMENTS_SCRIPT = '/api/plugins/custom-comments/assets/js/plugin.iife.js';
export const MOUNT_SELECTOR = '[data-cbc-cc-post]';

type CommentsApi = {
    mountTarget?: (el: Element, type: string, id: number | string) => boolean;
};

type CommentsWindow = Window & {
    CustomComments?: CommentsApi;
    CustomDigitalComments?: CommentsApi;
};

export function commentsApi(win: CommentsWindow = window as CommentsWindow): CommentsApi | null {
    const api = win.CustomComments ?? win.CustomDigitalComments ?? null;
    return api && typeof api.mountTarget === 'function' ? api : null;
}

export function mountPostId(el: Element): number {
    const raw = el.getAttribute('data-cbc-cc-post') ?? '';
    const id = Number(raw);
    return Number.isInteger(id) && id > 0 ? id : 0;
}

/** 아직 붙지 않은 상자들 */
export function pendingMounts(root: ParentNode = document): Element[] {
    return Array.from(root.querySelectorAll(MOUNT_SELECTOR)).filter((el) => {
        if (mountPostId(el) <= 0) {
            return false;
        }
        // custom-comments 가 붙으면 data-cdc-owned=1. 같은 상자인데 글 번호가 바뀌면(SPA 이동) 다시 붙임
        const owned = el.getAttribute('data-cdc-owned') === '1';
        const same = el.getAttribute('data-cdc-target-id') === String(mountPostId(el));
        return !(owned && same);
    });
}

let loading: Promise<boolean> | null = null;

export function ensureCommentsScript(doc: Document = document, win: CommentsWindow = window as CommentsWindow): Promise<boolean> {
    if (commentsApi(win)) {
        return Promise.resolve(true);
    }
    if (loading) {
        return loading;
    }
    loading = new Promise<boolean>((resolve) => {
        const done = (): void => resolve(Boolean(commentsApi(win)));
        const existing = doc.querySelector<HTMLScriptElement>(`script[src^="${COMMENTS_SCRIPT}"]`);
        if (existing) {
            // 전역 로딩으로 이미 붙어 있으면 잠시 기다림
            let tries = 0;
            const timer = win.setInterval(() => {
                tries += 1;
                if (commentsApi(win) || tries > 40) {
                    win.clearInterval(timer);
                    done();
                }
            }, 100);
            return;
        }
        const script = doc.createElement('script');
        script.src = COMMENTS_SCRIPT;
        script.async = true;
        script.onload = done;
        script.onerror = () => resolve(false);
        doc.head.appendChild(script);
    }).finally(() => {
        loading = null;
    });
    return loading;
}

export function mountOne(el: Element, win: CommentsWindow = window as CommentsWindow): boolean {
    const api = commentsApi(win);
    const id = mountPostId(el);
    if (!api || !api.mountTarget || id <= 0) {
        return false;
    }
    if (el.getAttribute('data-cdc-owned') === '1' && el.getAttribute('data-cdc-target-id') !== String(id)) {
        el.removeAttribute('data-cdc-owned');
    }
    try {
        return api.mountTarget(el, TARGET_TYPE, id) !== false;
    } catch {
        return false;
    }
}

export async function mountAll(doc: Document = document, win: CommentsWindow = window as CommentsWindow): Promise<number> {
    const boxes = pendingMounts(doc);
    if (boxes.length === 0) {
        return 0;
    }
    const ready = await ensureCommentsScript(doc, win);
    if (!ready) {
        boxes.forEach((el) => {
            if (!el.querySelector('[data-cbc-cc-fallback]')) {
                const note = doc.createElement('div');
                note.setAttribute('data-cbc-cc-fallback', '1');
                note.className = 'cbc-cc-fallback';
                note.textContent = '댓글을 불러오지 못했습니다. 새로고침해 주세요.';
                el.appendChild(note);
            }
        });
        return 0;
    }
    let n = 0;
    for (const el of pendingMounts(doc)) {
        if (mountOne(el, win)) {
            n += 1;
        }
    }
    return n;
}

export function hasMountBox(doc: Document = document): boolean {
    return doc.querySelector(MOUNT_SELECTOR) !== null;
}
