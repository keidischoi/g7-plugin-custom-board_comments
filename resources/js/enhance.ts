import type { PluginConfig, SortKind } from './config';
import { PLUGIN_ID } from './config';
import { bestIds, pinBest, sortTree, visibleComments, type BoardComment } from './sort';

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
        login: '추천하려면 로그인하세요.',
    },
    en: {
        like: 'Like',
        liked: 'Liked',
        best: 'Best',
        latest: 'Latest',
        oldest: 'Oldest',
        popular: 'Most liked',
        login: 'Sign in to like a comment.',
    },
};

export function localeCopy(lang = 'ko'): typeof COPY.ko {
    return lang.toLowerCase().startsWith('en') ? COPY.en : COPY.ko;
}

export function commentRows(section: Element): HTMLElement[] {
    const list = section.querySelector('.space-y-4') ?? section;
    return [...list.children].filter((node): node is HTMLElement => (
        node instanceof HTMLElement
        && (node.className.includes('border-b') || node.hasAttribute('data-cbc-comment-id'))
    ));
}

export function expandedRootIds(section: Element, comments: BoardComment[]): number[] {
    const expanded: number[] = [];
    const rows = commentRows(section);
    const roots = comments.filter((comment) => (comment.depth ?? 0) === 0);
    for (const [index, root] of roots.entries()) {
        const row = rows[index];
        if (!row) {
            continue;
        }
        const toggle = [...row.querySelectorAll('button')].find((button) => /hide|숨기|chevron-up/i.test(button.textContent ?? ''));
        if (toggle) {
            expanded.push(root.id);
        }
    }
    return expanded;
}

export function bindCommentRows(section: Element, comments: BoardComment[]): void {
    const visible = visibleComments(comments, expandedRootIds(section, comments));
    const rows = commentRows(section);
    rows.forEach((row, index) => {
        const comment = visible[index];
        if (!comment) {
            return;
        }
        row.setAttribute('data-cbc-comment-id', String(comment.id));
        if ((comment.depth ?? 0) === 0) {
            row.setAttribute('data-cbc-root', '1');
        }
    });
}

export function ensureToolbar(section: Element, sort: SortKind, copy: typeof COPY.ko): HTMLElement {
    let toolbar = section.querySelector<HTMLElement>('[data-cbc-toolbar]');
    if (toolbar) {
        syncSortButtons(toolbar, sort);
        return toolbar;
    }

    toolbar = document.createElement('div');
    toolbar.setAttribute('data-cbc-toolbar', '1');
    toolbar.className = 'cbc-toolbar';
    toolbar.innerHTML = SORTS_HTML(copy, sort);

    const heading = section.querySelector('h3');
    if (heading) {
        heading.appendChild(toolbar);
    } else {
        section.prepend(toolbar);
    }
    return toolbar;
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

export function reorderRows(section: Element, ordered: BoardComment[]): void {
    const list = section.querySelector('.space-y-4');
    if (!(list instanceof HTMLElement)) {
        return;
    }
    for (const comment of ordered) {
        const row = list.querySelector(`[data-cbc-comment-id="${comment.id}"]`);
        if (row) {
            list.appendChild(row);
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
    return options.map(([value, label]) => (
        `<button type="button" class="cbc-sort${value === current ? ' is-active' : ''}" data-cbc-sort="${value}">${label}</button>`
    )).join('');
}

function syncSortButtons(toolbar: HTMLElement, sort: SortKind): void {
    for (const button of toolbar.querySelectorAll<HTMLElement>('[data-cbc-sort]')) {
        button.classList.toggle('is-active', button.getAttribute('data-cbc-sort') === sort);
    }
}

export function registerToggleAction(dispatch: (detail: unknown) => void): void {
    dispatch({ handler: `${PLUGIN_ID}.refresh` });
}
