import type { SortKind } from './config';

export type BoardComment = {
    id: number;
    parent_id: number | null;
    depth: number;
    created_at: string;
    content?: string;
    author?: { name?: string | null } | null;
};

export function bestIds(
    counts: Record<string, number>,
    threshold: number,
    limit: number,
): number[] {
    return Object.entries(counts)
        .map(([id, count]) => [Number(id), Number(count)] as const)
        .filter(([id, count]) => id > 0 && count >= threshold)
        .sort((a, b) => b[1] - a[1] || b[0] - a[0])
        .slice(0, Math.max(0, limit))
        .map(([id]) => id);
}

export function sortTree(
    comments: BoardComment[],
    sort: SortKind,
    counts: Record<string, number>,
): BoardComment[] {
    const byParent = new Map<number, BoardComment[]>();
    for (const comment of comments) {
        const parentId = comment.parent_id ?? 0;
        const list = byParent.get(parentId) ?? [];
        list.push(comment);
        byParent.set(parentId, list);
    }

    const walk = (parentId: number): BoardComment[] => {
        const children = [...(byParent.get(parentId) ?? [])];
        children.sort((left, right) => {
            if (sort === 'popular') {
                const leftLikes = counts[String(left.id)] ?? 0;
                const rightLikes = counts[String(right.id)] ?? 0;
                if (leftLikes !== rightLikes) {
                    return rightLikes - leftLikes;
                }
            }
            if (sort === 'oldest') {
                return compareStamp(left, right);
            }
            return compareStamp(right, left);
        });
        const out: BoardComment[] = [];
        for (const child of children) {
            out.push(child);
            out.push(...walk(child.id));
        }
        return out;
    };

    return walk(0);
}

export function pinBest(sorted: BoardComment[], ids: number[]): BoardComment[] {
    if (ids.length === 0) {
        return sorted;
    }
    const threads = groupThreads(sorted);
    const order = new Map(ids.map((id, index) => [id, index]));
    const pinned: BoardComment[][] = [];
    const rest: BoardComment[][] = [];
    for (const thread of threads) {
        const rootId = thread[0]?.id ?? 0;
        if (order.has(rootId)) {
            pinned.push(thread);
        } else {
            rest.push(thread);
        }
    }
    pinned.sort((left, right) => (order.get(left[0]?.id ?? 0) ?? 999) - (order.get(right[0]?.id ?? 0) ?? 999));
    return [...pinned, ...rest].flat();
}

export function visibleComments(comments: BoardComment[], expandedRootIds: number[]): BoardComment[] {
    const expanded = new Set(expandedRootIds);
    const rootOf = new Map<number, number>();
    for (const comment of comments) {
        if ((comment.depth ?? 0) === 0) {
            rootOf.set(comment.id, comment.id);
        } else {
            const parent = comment.parent_id ?? 0;
            rootOf.set(comment.id, rootOf.get(parent) ?? parent);
        }
    }
    return comments.filter((comment) => {
        const rootId = rootOf.get(comment.id) ?? comment.id;
        return (comment.depth ?? 0) === 0 || expanded.has(rootId);
    });
}

function groupThreads(sorted: BoardComment[]): BoardComment[][] {
    const threads: BoardComment[][] = [];
    let current: BoardComment[] | null = null;
    for (const comment of sorted) {
        if ((comment.depth ?? 0) === 0) {
            if (current) {
                threads.push(current);
            }
            current = [comment];
        } else if (current) {
            current.push(comment);
        } else {
            current = [comment];
        }
    }
    if (current) {
        threads.push(current);
    }
    return threads;
}

function compareStamp(left: BoardComment, right: BoardComment): number {
    if (left.created_at !== right.created_at) {
        return left.created_at < right.created_at ? -1 : 1;
    }
    return left.id - right.id;
}
