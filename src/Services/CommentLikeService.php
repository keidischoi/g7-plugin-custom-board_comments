<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Services;

use App\Services\PluginSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Plugins\G7\Plugin\Custom\BoardComments\Models\CommentLike;
use Plugins\G7\Plugin\Custom\BoardComments\Plugin;
use Plugins\G7\Plugin\Custom\BoardComments\Support\CommentEnhanceRules;
use Plugins\G7\Plugin\Custom\BoardComments\Support\LikeRules;
use Plugins\G7\Plugin\Custom\BoardComments\Support\SettingsRules;
use RuntimeException;

class CommentLikeService
{
    public function __construct(
        private ?PluginSettingsService $pluginSettings = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        return SettingsRules::normalize($this->rawSettings());
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $postId, ?int $userId, ?string $guestHash, ?string $slug = null): array
    {
        $settings = $this->publicSettings();
        if (! $settings['enabled'] || ($slug !== null && $slug !== '' && ! SettingsRules::appliesToBoard($slug, $settings['board_slugs']))) {
            return [
                'enabled' => false,
                'counts' => (object) [],
                'liked' => [],
                'best' => [],
                'settings' => $settings,
            ];
        }

        if (! $this->tableReady()) {
            return [
                'enabled' => true,
                'counts' => (object) [],
                'liked' => [],
                'best' => [],
                'settings' => $settings,
            ];
        }

        $counts = $this->countsForPost($postId);
        $actor = LikeRules::actor($userId, $guestHash, (bool) $settings['allow_guest_likes']);
        $liked = $actor === null ? [] : $this->likedCommentIds($postId, $actor);
        $best = $settings['best_enabled']
            ? CommentEnhanceRules::bestIds($counts, (int) $settings['best_threshold'], (int) $settings['best_limit'])
            : [];

        return [
            'enabled' => true,
            'counts' => $this->objectCounts($counts),
            'liked' => $liked,
            'best' => $best,
            'settings' => $settings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toggle(int $boardId, int $postId, int $commentId, ?int $userId, ?string $guestHash, ?string $ip = null): array
    {
        $settings = $this->publicSettings();
        if (! $settings['enabled']) {
            throw new RuntimeException('댓글 확장이 꺼져 있습니다.', 403);
        }

        $actor = LikeRules::actor($userId, $guestHash, (bool) $settings['allow_guest_likes']);
        if ($actor === null) {
            throw new RuntimeException('추천하려면 로그인하세요.', 401);
        }

        if (! $this->tableReady()) {
            throw new RuntimeException('추천 테이블이 아직 없습니다. 플러그인을 다시 설치하세요.', 503);
        }

        $existing = CommentLike::query()
            ->where('comment_id', $commentId)
            ->where('user_id', $actor['user_id'])
            ->where('guest_hash', $actor['guest_hash'])
            ->first();

        $result = LikeRules::toggle($existing !== null);
        if ($existing !== null) {
            $existing->delete();
        } else {
            CommentLike::query()->create([
                'board_id' => $boardId,
                'post_id' => $postId,
                'comment_id' => $commentId,
                'user_id' => $actor['user_id'],
                'guest_hash' => $actor['guest_hash'],
                'ip_address' => $ip,
                'created_at' => Carbon::now(),
            ]);
        }

        $count = (int) CommentLike::query()->where('comment_id', $commentId)->count();

        return [
            'liked' => $result['liked'],
            'count' => $count,
            'comment_id' => $commentId,
        ];
    }

    public function forgetComment(int $commentId): void
    {
        if (! $this->tableReady()) {
            return;
        }

        CommentLike::query()->where('comment_id', $commentId)->delete();
    }

    /**
     * @return array<int, int>
     */
    private function countsForPost(int $postId): array
    {
        $rows = CommentLike::query()
            ->selectRaw('comment_id, COUNT(*) as likes_count')
            ->where('post_id', $postId)
            ->groupBy('comment_id')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->comment_id] = (int) $row->likes_count;
        }

        return $counts;
    }

    /**
     * @param  array{user_id: int, guest_hash: string}  $actor
     * @return list<int>
     */
    private function likedCommentIds(int $postId, array $actor): array
    {
        return CommentLike::query()
            ->where('post_id', $postId)
            ->where('user_id', $actor['user_id'])
            ->where('guest_hash', $actor['guest_hash'])
            ->pluck('comment_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rawSettings(): ?array
    {
        if ($this->pluginSettings === null) {
            return null;
        }

        if (method_exists($this->pluginSettings, 'getAllActiveSettings')) {
            $active = $this->pluginSettings->getAllActiveSettings();
            $loaded = is_array($active[Plugin::IDENTIFIER] ?? null) ? $active[Plugin::IDENTIFIER] : [];

            return is_array($loaded) ? $loaded : null;
        }

        $loaded = $this->pluginSettings->get(Plugin::IDENTIFIER);

        return is_array($loaded) ? $loaded : null;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('custom_board_comment_likes');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<int, int>  $counts
     * @return \stdClass
     */
    private function objectCounts(array $counts): \stdClass
    {
        $object = new \stdClass();
        foreach ($counts as $id => $count) {
            $object->{(string) $id} = $count;
        }

        return $object;
    }
}
