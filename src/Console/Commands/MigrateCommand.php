<?php

namespace Plugins\Custom\BoardComments\Console\Commands;

use Illuminate\Console\Command;
use Plugins\Custom\BoardComments\Services\Migrator;

/**
 * 공식 게시판 댓글을 custom-comments 댓글(board_post)로 옮깁니다 (원본은 그대로, 여러 번 돌려도 안전).
 */
class MigrateCommand extends Command
{
    protected $signature = 'custom-board_comments:migrate
        {--board=* : 게시판 슬러그 (여러 개 가능, 쉼표 가능). 없으면 설정의 「바꾸기 게시판」}
        {--post= : 이 글 번호만}
        {--dry-run : 옮기지 않고 몇 개인지만 셈}
        {--include-secret : 비밀 댓글도 옮김 (custom-comments 에는 비밀 댓글이 없어 글을 볼 수 있는 모두에게 보입니다)}';

    protected $description = '공식 게시판 댓글 → custom-comments 댓글로 옮기기 (원본 그대로 · 짝 표로 중복 없음)';

    public function handle(): int
    {
        $slugs = Migrator::resolveSlugs((array) $this->option('board'));
        if ($slugs === []) {
            $this->error('옮길 게시판이 없습니다. --board=free 처럼 주거나 설정의 「바꾸기 게시판」을 채우세요.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $post = $this->option('post') !== null ? (int) $this->option('post') : null;
        try {
            $stats = (new Migrator)->run($slugs, $dry, $post ?: null, (bool) $this->option('include-secret'), fn (string $l) => $this->line($l));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->newLine();
        $this->line(($dry ? '[미리 보기] ' : '').'결과:');
        $labels = [
            'boards' => '게시판', 'seen' => '본 공식 댓글', 'migrated' => $dry ? '옮길 댓글' : '옮긴 댓글',
            'already' => '이미 짝 있음(건너뜀)', 'skipped_status' => '지움 · 블라인드(건너뜀)', 'skipped_secret' => '비밀 댓글(건너뜀)',
            'skipped_post' => '지운 글의 댓글(건너뜀)', 'skipped_empty' => '빈 글(건너뜀)', 'orphan_replies' => '부모 없이 최상위로 옮길 답글', 'errors' => '실패',
        ];
        foreach ($labels as $k => $label) {
            $this->line(sprintf('  %-28s %d', $label, $stats[$k] ?? 0));
        }

        return ($stats['errors'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
