import type { PluginConfig, SortKind } from './config';
import { PLUGIN_ID } from './config';
import { bestIds, pinBest, sortTree, visibleComments, type BoardComment } from './sort';
import { stickerById } from './stickers';
import { hideComposerUi } from './composer';

export type LikeSummary = {
    counts: Record<string, number>;
    liked: number[];
    best: number[];
};

const COPY = {
    ko: {
        like: '추천',
        liked: '추천함',
        best: '베스트',
        latest: '최신순',
        oldest: '등록순',
        popular: '추천순',
        sortLabel: '댓글 정렬',
        sticker: '스티커',
        image: '이미지',
        needComposer: '댓글 입력창이 없습니다. 로그인해서 댓글을 쓸 수 있는 글에서 사용하세요.',
        uploadFail: '이미지를 올리지 못했습니다.',
        login: '추천하려면 로그인하세요.',
    },
    en: {
        like: 'Like',
        liked: 'Liked',
        best: 'Best',
        latest: 'Latest',
        oldest: 'Oldest',
        popular: 'Most liked',
        sortLabel: 'Comment sort',
        sticker: 'Sticker',
        image: 'Image',
        needComposer: 'No comment box on this page. Sign in on a post where you can write a comment.',
        uploadFail: 'Could not upload the image.',
        login: 'Sign in to like a comment.',
    },
};

export function localeCopy(lang = 'ko'): typeof COPY.ko {
    return lang.toLowerCase().startsWith('en') ? COPY.en : COPY.ko;
}

export function findCommentSection(root: ParentNode = document): Element | null {
    const existing = root.querySelector('[data-cbc-section]');
    if (existing) {
        return existing;
    }

    const headings = [...root.querySelectorAll('h2, h3, h4')];
    const heading = headings.find((node) => /댓글|comments?/i.test(node.textContent ?? ''));
    const section = heading?.closest('.bg-white, .rounded-lg, .shadow, [class*="comment"]')
        ?? heading?.parentElement;
    if (section instanceof HTMLElement) {
        section.setAttribute('data-cbc-section', '1');
        return section;
    }
    return null;
}

function classText(node: Element): string {
    return typeof node.className === 'string' ? node.className : '';
}

function isCommentRow(node: Element): node is HTMLElement {
    if (!(node instanceof HTMLElement)) {
        return false;
    }
    if (node.matches('h2, h3, h4, textarea, form, [data-cbc-toolbar], [data-cbc-stickers]')) {
        return false;
    }
    return classText(node).includes('border-b')
        || node.hasAttribute('data-cbc-comment-id')
        || Boolean(node.querySelector('.flex-1'));
}

export function commentList(section: Element): HTMLElement | null {
    const marked = section.querySelector<HTMLElement>('.space-y-4');
    if (marked) {
        return marked;
    }
    const row = section.querySelector<HTMLElement>('[data-cbc-comment-id]');
    return row?.parentElement ?? null;
}

export function commentRows(section: Element): HTMLElement[] {
    const lists = [...section.querySelectorAll('.space-y-4')];
    const hosts = lists.length > 0 ? lists : [commentList(section) ?? section];
    const rows: HTMLElement[] = [];
    for (const host of hosts) {
        for (const node of host.children) {
            if (isCommentRow(node)) {
                rows.push(node);
            }
        }
    }
    return rows;
}

function rowHaystack(row: HTMLElement): string {
    return (row.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function commentNeedle(comment: BoardComment): string {
    return String(comment.content ?? '')
        .replace(/\[\[s:([a-z0-9-]+)\]\]/g, (_full, id: string) => stickerById(id)?.emoji ?? '')
        .replace(/\[\[i:\d+\]\]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

export function expandedRootIds(section: Element, comments: BoardComment[]): number[] {
    const expanded: number[] = [];
    for (const row of commentRows(section)) {
        const id = Number(row.getAttribute('data-cbc-comment-id'));
        const root = comments.find((comment) => comment.id === id && (comment.depth ?? 0) === 0);
        if (!root) {
            continue;
        }
        const toggle = [...row.querySelectorAll('button')].find((button) => /hide|숨기|chevron-up/i.test(button.textContent ?? ''));
        if (toggle) {
            expanded.push(root.id);
        }
    }
    return expanded;
}

function markRow(row: HTMLElement, comment: BoardComment): void {
    row.setAttribute('data-cbc-comment-id', String(comment.id));
    if ((comment.depth ?? 0) === 0) {
        row.setAttribute('data-cbc-root', '1');
    } else {
        row.removeAttribute('data-cbc-root');
    }
}

export function bindCommentRows(section: Element, comments: BoardComment[]): void {
    const visible = visibleComments(comments, expandedRootIds(section, comments));
    const rows = commentRows(section);
    const used = new Set<number>();

    for (const row of rows) {
        const existing = Number(row.getAttribute('data-cbc-comment-id'));
        const comment = visible.find((item) => item.id === existing);
        if (!comment) {
            continue;
        }
        used.add(comment.id);
        markRow(row, comment);
    }

    const leftoverComments = visible.filter((item) => !used.has(item.id));
    const leftoverRows = rows.filter((row) => {
        const existing = Number(row.getAttribute('data-cbc-comment-id'));
        return !existing || !used.has(existing);
    });

    const byContent = [...leftoverComments]
        .map((comment) => ({ comment, needle: commentNeedle(comment) }))
        .filter((item) => item.needle !== '')
        .sort((left, right) => right.needle.length - left.needle.length);

    for (const { comment, needle } of byContent) {
        const row = leftoverRows.find((candidate) => (
            !used.has(Number(candidate.getAttribute('data-cbc-comment-id')))
            && rowHaystack(candidate).includes(needle)
        ));
        if (!row) {
            continue;
        }
        used.add(comment.id);
        markRow(row, comment);
    }

    const stillComments = leftoverComments.filter((item) => !used.has(item.id));
    const stillRows = leftoverRows.filter((row) => {
        const existing = Number(row.getAttribute('data-cbc-comment-id'));
        return !existing || !used.has(existing);
    });
    stillRows.forEach((row, index) => {
        const comment = stillComments[index];
        if (!comment) {
            return;
        }
        markRow(row, comment);
    });
}

const OVERLAY_Z = '2147483000';

export function ensureToolbar(section: Element | null, sort: SortKind, copy: typeof COPY.ko): HTMLElement {
    let toolbar = document.querySelector<HTMLElement>('[data-cbc-toolbar]');
    if (!toolbar) {
        toolbar = document.createElement('div');
        toolbar.setAttribute('data-cbc-toolbar', '1');
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', copy.sortLabel);
        toolbar.className = 'cbc-toolbar cbc-toolbar--overlay';
        toolbar.style.position = 'fixed';
        toolbar.style.zIndex = OVERLAY_Z;
        toolbar.style.display = 'flex';
        document.body.appendChild(toolbar);
        toolbar.innerHTML = SORTS_HTML(copy, sort);
    } else {
        syncSortButtons(toolbar, sort);
        if (toolbar.parentElement !== document.body) {
            document.body.appendChild(toolbar);
        }
    }
    placeToolbar(toolbar, section);
    return toolbar;
}

export function hideToolbar(): void {
    document.querySelector('[data-cbc-toolbar]')?.remove();
    hideComposerUi();
}

export function placeToolbar(toolbar: HTMLElement, section: Element | null): void {
    const heading = section?.querySelector('h3, h2, h4');
    toolbar.style.position = 'fixed';
    toolbar.style.zIndex = OVERLAY_Z;
    toolbar.style.display = 'flex';
    if (heading instanceof HTMLElement) {
        const rect = heading.getBoundingClientRect();
        const width = toolbar.offsetWidth || 240;
        toolbar.style.top = `${Math.max(8, rect.bottom + 6)}px`;
        toolbar.style.left = `${Math.max(8, rect.right - width)}px`;
        toolbar.style.right = 'auto';
        toolbar.style.bottom = 'auto';
        toolbar.dataset.cbcAnchored = '1';
        return;
    }
    toolbar.style.top = 'auto';
    toolbar.style.left = 'auto';
    toolbar.style.right = '1.25rem';
    toolbar.style.bottom = '1.25rem';
    toolbar.dataset.cbcAnchored = '0';
}

export function paintLikes(
    section: Element,
    summary: LikeSummary,
    config: PluginConfig,
    copy: typeof COPY.ko,
): void {
    const liked = new Set(summary.liked.map(Number));
    const best = new Set((summary.best ?? []).map(Number));
    for (const row of section.querySelectorAll<HTMLElement>('[data-cbc-comment-id]')) {
        const id = Number(row.getAttribute('data-cbc-comment-id'));
        const count = summary.counts[String(id)] ?? 0;
        let button = row.querySelector<HTMLButtonElement>('[data-cbc-like]');
        if (!button) {
            button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('data-cbc-like', '1');
            button.className = 'cbc-like';
            const host = row.querySelector('.flex-1') ?? row;
            host.appendChild(button);
        }
        const isLiked = liked.has(id);
        button.setAttribute('aria-pressed', isLiked ? 'true' : 'false');
        button.classList.toggle('is-liked', isLiked);
        button.textContent = `${isLiked ? copy.liked : copy.like} ${count}`;

        let badge = row.querySelector<HTMLElement>('[data-cbc-best]');
        if (config.bestEnabled && best.has(id)) {
            if (!badge) {
                badge = document.createElement('span');
                badge.setAttribute('data-cbc-best', '1');
                badge.className = 'cbc-best';
                badge.textContent = copy.best;
                const meta = row.querySelector('.flex.items-center.gap-1') ?? row;
                meta.appendChild(badge);
            }
        } else {
            badge?.remove();
        }
    }
}

function flexStack(el: HTMLElement): void {
    el.classList.add('cbc-comment-stack');
}

function applyRank(nodes: HTMLElement[], rank: Map<string, number>, idOf: (node: HTMLElement) => string | null): void {
    for (const node of nodes) {
        const n = rank.get(idOf(node) ?? '');
        node.style.order = n ? String(n) : '';
    }
}

export function reorderRows(section: Element, ordered: BoardComment[]): void {
    const rows = commentRows(section);
    if (rows.length === 0) {
        return;
    }
    const rank = new Map(ordered.map((comment, index) => [String(comment.id), index + 1]));
    const parent = rows[0]?.parentElement;
    if (parent && rows.every((row) => row.parentElement === parent)) {
        flexStack(parent);
        applyRank(rows, rank, (row) => row.getAttribute('data-cbc-comment-id'));
        for (const comment of ordered) {
            const row = parent.querySelector(`[data-cbc-comment-id="${comment.id}"]`);
            if (row) {
                parent.appendChild(row);
            }
        }
        return;
    }
    const wrappers = rows
        .map((row) => row.parentElement)
        .filter((node): node is HTMLElement => node instanceof HTMLElement);
    const host = wrappers[0]?.parentElement;
    if (!host || wrappers.some((wrap) => wrap.parentElement !== host)) {
        return;
    }
    flexStack(host);
    applyRank(wrappers, rank, (wrap) => wrap.querySelector('[data-cbc-comment-id]')?.getAttribute('data-cbc-comment-id') ?? null);
    for (const comment of ordered) {
        const row = section.querySelector(`[data-cbc-comment-id="${comment.id}"]`);
        const wrap = row?.parentElement;
        if (wrap && wrap.parentElement === host) {
            host.appendChild(wrap);
        }
    }
}

export function applySort(
    section: Element,
    comments: BoardComment[],
    sort: SortKind,
    summary: LikeSummary,
    config: PluginConfig,
): BoardComment[] {
    const sorted = sortTree(comments, sort, summary.counts);
    const best = config.bestEnabled
        ? (summary.best.length > 0
            ? summary.best
            : bestIds(summary.counts, config.bestThreshold, config.bestLimit))
        : [];
    const ordered = config.bestEnabled ? pinBest(sorted, best) : sorted;
    const expanded = expandedRootIds(section, comments);
    reorderRows(section, visibleComments(ordered, expanded));
    return ordered;
}

function SORTS_HTML(copy: typeof COPY.ko, current: SortKind): string {
    const options: Array<[SortKind, string]> = [
        ['latest', copy.latest],
        ['oldest', copy.oldest],
        ['popular', copy.popular],
    ];
    const buttons = options.map(([value, label]) => (
        `<button type="button" class="cbc-sort${value === current ? ' is-active' : ''}" data-cbc-sort="${value}">${label}</button>`
    )).join('');
    const extras = [
        `<button type="button" class="cbc-sort cbc-composer-btn" data-cbc-sticker="1">${copy.sticker}</button>`,
        `<button type="button" class="cbc-sort cbc-composer-btn" data-cbc-image="1">${copy.image}</button>`,
    ].join('');
    return `<span class="cbc-toolbar-label">${copy.sortLabel}</span>${buttons}<span class="cbc-toolbar-gap"></span>${extras}`;
}

export function syncSortButtons(toolbar: HTMLElement, sort: SortKind): void {
    for (const button of toolbar.querySelectorAll<HTMLElement>('[data-cbc-sort]')) {
        button.classList.toggle('is-active', button.getAttribute('data-cbc-sort') === sort);
    }
}

export function bindToolbarSort(toolbar: HTMLElement, onSort: (sort: SortKind) => void): void {
    if (toolbar.dataset.cbcSortBound === '1') {
        return;
    }
    toolbar.dataset.cbcSortBound = '1';
    toolbar.addEventListener('click', (event) => {
        const button = (event.target as Element | null)?.closest?.('[data-cbc-sort]');
        if (!(button instanceof HTMLElement) || !toolbar.contains(button)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        onSort((button.getAttribute('data-cbc-sort') as SortKind) || 'latest');
    }, true);
}

export function registerToggleAction(dispatch: (detail: unknown) => void): void {
    dispatch({ handler: `${PLUGIN_ID}.refresh` });
}
