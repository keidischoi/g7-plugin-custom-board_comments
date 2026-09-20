<?php

namespace Plugins\Custom\BoardComments\Support;

final class CommentEnhanceRules
{
    /**
     * @param  array<int|string, int>  $counts
     * @return list<int>
     */
    public static function bestIds(array $counts, int $threshold, int $limit): array
    {
        $eligible = [];
        foreach ($counts as $id => $count) {
            $commentId = (int) $id;
            $likes = (int) $count;
            if ($commentId > 0 && $likes >= $threshold) {
                $eligible[$commentId] = $likes;
            }
        }

        arsort($eligible);

        return array_slice(array_map('intval', array_keys($eligible)), 0, max(0, $limit));
    }

    /**
     * @param  list<array<string, mixed>>  $comments
     * @param  array<int|string, int>  $counts
     * @return list<array<string, mixed>>
     */
    public static function sortTree(array $comments, string $sort, array $counts): array
    {
        $byParent = [];
        foreach ($comments as $comment) {
            $parentId = self::parentId($comment);
            $byParent[$parentId][] = $comment;
        }

        $walk = function (int $parentId) use (&$walk, &$byParent, $sort, $counts): array {
            $children = $byParent[$parentId] ?? [];
            usort($children, static function (array $left, array $right) use ($sort, $counts): int {
                if ($sort === 'popular') {
                    $leftLikes = (int) ($counts[$left['id'] ?? 0] ?? 0);
                    $rightLikes = (int) ($counts[$right['id'] ?? 0] ?? 0);
                    if ($leftLikes !== $rightLikes) {
                        return $rightLikes <=> $leftLikes;
                    }
                }

                $leftAt = (string) ($left['created_at'] ?? '');
                $rightAt = (string) ($right['created_at'] ?? '');
                if ($sort === 'oldest') {
                    return $leftAt <=> $rightAt ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
                }

                return $rightAt <=> $leftAt ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
            });

            $out = [];
            foreach ($children as $child) {
                $out[] = $child;
                foreach ($walk((int) ($child['id'] ?? 0)) as $descendant) {
                    $out[] = $descendant;
                }
            }

            return $out;
        };

        return $walk(0);
    }

    /**
     * @param  list<array<string, mixed>>  $sorted
     * @param  list<int>  $bestIds
     * @return list<array<string, mixed>>
     */
    public static function pinBest(array $sorted, array $bestIds): array
    {
        if ($bestIds === []) {
            return $sorted;
        }

        $threads = self::threads($sorted);
        $order = array_flip($bestIds);
        $pinned = [];
        $rest = [];

        foreach ($threads as $thread) {
            $rootId = (int) ($thread[0]['id'] ?? 0);
            if (isset($order[$rootId])) {
                $pinned[] = $thread;
            } else {
                $rest[] = $thread;
            }
        }

        usort($pinned, static function (array $left, array $right) use ($order): int {
            $leftRank = $order[$left[0]['id'] ?? 0] ?? 999;
            $rightRank = $order[$right[0]['id'] ?? 0] ?? 999;

            return $leftRank <=> $rightRank;
        });

        $out = [];
        foreach (array_merge($pinned, $rest) as $thread) {
            foreach ($thread as $comment) {
                $out[] = $comment;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $comments
     * @param  list<int>  $expandedRootIds
     * @return list<array<string, mixed>>
     */
    public static function visibleComments(array $comments, array $expandedRootIds): array
    {
        $expanded = array_flip($expandedRootIds);
        $rootOf = [];
        foreach ($comments as $comment) {
            $id = (int) ($comment['id'] ?? 0);
            $depth = (int) ($comment['depth'] ?? 0);
            if ($depth === 0) {
                $rootOf[$id] = $id;
            } else {
                $parent = self::parentId($comment);
                $rootOf[$id] = $rootOf[$parent] ?? $parent;
            }
        }

        $visible = [];
        foreach ($comments as $comment) {
            $id = (int) ($comment['id'] ?? 0);
            $depth = (int) ($comment['depth'] ?? 0);
            $rootId = $rootOf[$id] ?? $id;
            if ($depth === 0 || isset($expanded[$rootId])) {
                $visible[] = $comment;
            }
        }

        return $visible;
    }

    /**
     * @param  list<array<string, mixed>>  $sorted
     * @return list<list<array<string, mixed>>>
     */
    private static function threads(array $sorted): array
    {
        $threads = [];
        $current = null;

        foreach ($sorted as $comment) {
            $depth = (int) ($comment['depth'] ?? 0);
            if ($depth === 0) {
                if ($current !== null) {
                    $threads[] = $current;
                }
                $current = [$comment];
            } else {
                if ($current === null) {
                    $current = [$comment];
                } else {
                    $current[] = $comment;
                }
            }
        }

        if ($current !== null) {
            $threads[] = $current;
        }

        return $threads;
    }

    /**
     * @param  array<string, mixed>  $comment
     */
    private static function parentId(array $comment): int
    {
        $parent = $comment['parent_id'] ?? 0;

        return $parent === null || $parent === '' ? 0 : (int) $parent;
    }
}
