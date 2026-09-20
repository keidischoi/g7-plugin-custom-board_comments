<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Services;

use App\Services\PluginSettingsService;
use Illuminate\Support\Carbon;
use Plugins\G7\Plugin\Custom\BoardComments\Models\CommentLike;
use Plugins\G7\Plugin\Custom\BoardComments\Support\CommentEnhanceRules;
use Plugins\G7\Plugin\Custom\BoardComments\Support\LikeRules;
use Plugins\G7\Plugin\Custom\BoardComments\Support\LikeTable;
use Plugins\G7\Plugin\Custom\BoardComments\Support\SettingsRules;
use Plugins\G7\Plugin\Custom\BoardComments\Support\SettingsStore;
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
        $path = SettingsStore::path();
        if (is_file($path)) {
            return SettingsStore::get();
        }
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

        $chunks = [];
        $ids = [SettingsRules::PLUGIN_ID, 'custom-board_comments', 'g7-plugin-custom-board_comments'];
        if (method_exists($this->pluginSettings, 'get')) {
            foreach ($ids as $id) {
                try { $chunks[] = $this->pluginSettings->get($id); } catch (\Throwable $e) {}
            }
        }
        if (method_exists($this->pluginSettings, 'getSettings')) {
            foreach ($ids as $id) {
                try { $chunks[] = $this->pluginSettings->getSettings($id); } catch (\Throwable $e) {}
            }
        }
        if (method_exists($this->pluginSettings, 'getAllActiveSettings')) {
            $chunks[] = $this->pluginSettings->getAllActiveSettings();
        }
        foreach ([
            storage_path('app/plugins/g7-plugin-custom-board_comments/settings.json'),
            storage_path('app/plugins/custom-board_comments/settings.json'),
        ] as $path) {
            if (is_file($path)) {
                try { $chunks[] = json_decode((string) file_get_contents($path), true); } catch (\Throwable $e) {}
            }
        }

        return SettingsRules::firstSettings($chunks);
    }

    private function tableReady(): bool
    {
        return LikeTable::ensure();
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
