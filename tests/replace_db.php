<?php

/**
 * 0.2.0 바꾸기 모드 통합 검사 (Laravel 부품 + SQLite 메모리 DB + 진짜 custom-comments 컨트롤러):
 *   G7_VENDOR=/path/to/g7/vendor [CUSTOM_COMMENTS_DIR=../g7-plugin-custom-comments] php tests/replace_db.php
 *
 * vendor 나 custom-comments 소스가 없으면 건너뜁니다.
 * 공식 게시판 CommentService 는 같은 표 · 같은 훅을 쓰는 가짜(FakeBoard)로 대신합니다.
 */

namespace {
    $vendor = getenv('G7_VENDOR') ?: '';
    $ccDir = getenv('CUSTOM_COMMENTS_DIR') ?: dirname(__DIR__, 2).'/g7-plugin-custom-comments';
    if ($vendor === '' || ! is_file($vendor.'/autoload.php') || ! is_file($ccDir.'/src/Http/Controllers/CommentController.php')) {
        echo "SKIP — G7_VENDOR 또는 custom-comments 소스(CUSTOM_COMMENTS_DIR)가 없어 통합 검사를 건너뜁니다\n";
        exit(0);
    }
    require $vendor.'/autoload.php';
}

namespace App\Contracts\Extension {
    interface HookListenerInterface
    {
        public static function getSubscribedHooks(): array;

        public function handle(...$args): void;
    }
}

namespace App\Models {
    class User extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'users';

        public $timestamps = false;
    }
}

namespace App\Extension {
    /** 코어 HookManager 흉내: 등록한 리스너를 우선순위대로 바로 부름 */
    class HookManager
    {
        /** @var array<string, list<array{0:int,1:object,2:string,3:string}>> */
        public static array $hooks = [];

        public static array $fired = [];

        public static function subscribe(object $listener): void
        {
            foreach ($listener::getSubscribedHooks() as $hook => $cfg) {
                self::$hooks[$hook][] = [(int) ($cfg['priority'] ?? 10), $listener, (string) $cfg['method'], (string) ($cfg['type'] ?? 'action')];
                usort(self::$hooks[$hook], static fn ($a, $b) => $a[0] <=> $b[0]);
            }
        }

        public static function doAction(string $hook, ...$args): void
        {
            self::$fired[] = $hook;
            foreach (self::$hooks[$hook] ?? [] as [$p, $l, $m, $t]) {
                if ($t === 'action') {
                    $l->{$m}(...$args);
                }
            }
        }

        public static function applyFilters(string $hook, mixed $value, ...$args): mixed
        {
            foreach (self::$hooks[$hook] ?? [] as [$p, $l, $m, $t]) {
                if ($t === 'filter') {
                    $value = $l->{$m}($value, ...$args);
                }
            }

            return $value;
        }
    }
}

namespace {
    use App\Extension\HookManager;
    use Illuminate\Container\Container;
    use Illuminate\Database\Capsule\Manager as Capsule;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Facade;
    use Illuminate\Support\Facades\Schema as DbSchema;
    use Plugins\Custom\BoardComments\Contracts\BoardCommentGateway;
    use Plugins\Custom\BoardComments\Services\MirrorService;
    use Plugins\Custom\BoardComments\Services\Migrator;
    use Plugins\Custom\BoardComments\Support\BoardEnv;
    use Plugins\Custom\BoardComments\Support\BoardPermission;
    use Plugins\Custom\BoardComments\Support\LinkTable;

    $root = dirname(__DIR__);
    spl_autoload_register(static function (string $c) use ($root, $ccDir): void {
        foreach (['Plugins\\Custom\\BoardComments\\' => $root.'/src/', 'Plugins\\Custom\\Comments\\' => $ccDir.'/src/'] as $p => $dir) {
            if (str_starts_with($c, $p)) {
                $f = $dir.str_replace('\\', '/', substr($c, strlen($p))).'.php';
                if (is_file($f)) {
                    require $f;
                }
            }
        }
    });

    $tmp = sys_get_temp_dir().'/cbc-replace-'.getmypid();
    @mkdir($tmp.'/storage/app', 0777, true);
    $app = new Illuminate\Foundation\Application($tmp);
    $app->useStoragePath($tmp.'/storage');
    Container::setInstance($app);
    Facade::setFacadeApplication($app);
    $app->instance('config', new Illuminate\Config\Repository(['app' => ['timezone' => 'Asia/Seoul', 'url' => 'https://site.test'], 'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]]]));
    $app->singleton('cache', static fn ($a) => new Illuminate\Cache\CacheManager($a));
    $app->singleton('files', static fn () => new Illuminate\Filesystem\Filesystem());
    $capsule = new Capsule($app);
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $app->instance('db', $capsule->getDatabaseManager());
    $app->singleton('db.schema', static fn () => $capsule->getConnection()->getSchemaBuilder());
    $app->instance(Illuminate\Contracts\Routing\ResponseFactory::class, new class extends Illuminate\Routing\ResponseFactory
    {
        public function __construct() {}
    });

    $fails = 0;
    $count = 0;
    function check(bool $ok, string $label): void
    {
        global $fails, $count;
        $count++;
        echo ($ok ? 'ok  ' : 'FAIL  ').$label."\n";
        if (! $ok) {
            $fails++;
        }
    }

    // ── 표: 사용자 · 플러그인 · 게시판 · 글 · 공식 댓글
    DbSchema::create('users', static function ($t): void {
        $t->id();
        $t->string('name')->nullable();
        $t->string('nickname')->nullable();
        $t->boolean('is_admin')->default(false);
    });
    DB::table('users')->insert([['id' => 10, 'name' => 'hong', 'nickname' => '홍길동', 'is_admin' => false], ['id' => 11, 'name' => 'lim', 'nickname' => '임꺽정', 'is_admin' => false], ['id' => 1, 'name' => 'admin', 'nickname' => '관리자', 'is_admin' => true]]);
    DbSchema::create('plugins', static function ($t): void {
        $t->id();
        $t->string('identifier');
        $t->string('status');
        $t->string('version');
    });
    DB::table('plugins')->insert(['identifier' => 'custom-comments', 'status' => 'active', 'version' => '1.0.1']);
    DbSchema::create('boards', static function ($t): void {
        $t->id();
        $t->string('slug');
        $t->boolean('use_comment')->default(true);
        $t->integer('max_comment_depth')->default(10);
    });
    DB::table('boards')->insert([['id' => 1, 'slug' => 'free', 'use_comment' => true, 'max_comment_depth' => 1], ['id' => 2, 'slug' => 'notice', 'use_comment' => true, 'max_comment_depth' => 10]]);
    DbSchema::create('board_posts', static function ($t): void {
        $t->id();
        $t->unsignedBigInteger('board_id');
        $t->unsignedBigInteger('user_id')->nullable();
        $t->boolean('is_secret')->default(false);
        $t->string('status')->default('published');
        $t->softDeletes();
    });
    DB::table('board_posts')->insert([
        ['id' => 100, 'board_id' => 1, 'user_id' => 10, 'is_secret' => false, 'status' => 'published'],
        ['id' => 101, 'board_id' => 1, 'user_id' => 11, 'is_secret' => true, 'status' => 'published'],
        ['id' => 102, 'board_id' => 1, 'user_id' => 11, 'is_secret' => false, 'status' => 'blinded'],
        ['id' => 200, 'board_id' => 2, 'user_id' => 10, 'is_secret' => false, 'status' => 'published'],
        ['id' => 103, 'board_id' => 1, 'user_id' => 10, 'is_secret' => false, 'status' => 'published'],
    ]);
    DbSchema::create('board_comments', static function ($t): void {
        $t->id();
        $t->unsignedBigInteger('board_id');
        $t->unsignedBigInteger('post_id');
        $t->unsignedBigInteger('user_id')->nullable();
        $t->unsignedBigInteger('parent_id')->nullable();
        $t->string('author_name')->nullable();
        $t->text('content');
        $t->boolean('is_secret')->default(false);
        $t->string('status')->default('published');
        $t->integer('depth')->default(0);
        $t->string('ip_address')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Plugins\Custom\Comments\Models\DigitalComment::ensureSchema();

    /** 게시판 CommentService 흉내: 같은 표, 같은 깊이 검사, 같은 훅 */
    final class FakeBoard implements BoardCommentGateway
    {
        public array $calls = [];

        public function create(string $slug, array $data): int
        {
            $this->calls[] = ['create', $data];
            $board = DB::table('boards')->where('slug', $slug)->first();
            $depth = 0;
            if (! empty($data['parent_id'])) {
                $p = DB::table('board_comments')->where('id', $data['parent_id'])->first();
                $depth = (int) $p->depth + 1;
                if ($depth > (int) $board->max_comment_depth) {
                    throw new RuntimeException('CommentDepthExceededException');
                }
            }
            $name = $data['user_id'] ? DB::table('users')->where('id', $data['user_id'])->value('nickname') : null;
            $id = (int) DB::table('board_comments')->insertGetId([
                'board_id' => $board->id, 'post_id' => $data['post_id'], 'user_id' => $data['user_id'], 'parent_id' => $data['parent_id'],
                'author_name' => $name, 'content' => $data['content'], 'is_secret' => $data['is_secret'] ? 1 : 0, 'depth' => $depth,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            HookManager::doAction('sirsoft-board.comment.after_create', (object) ['id' => $id], $slug);

            return $id;
        }

        public function update(string $slug, int $commentId, int $postId, string $content): void
        {
            $this->calls[] = ['update', $commentId, $content];
            DB::table('board_comments')->where('id', $commentId)->update(['content' => $content]);
            HookManager::doAction('sirsoft-board.comment.after_update', (object) ['id' => $commentId], $slug);
        }

        public function delete(string $slug, int $commentId, int $postId, string $trigger): void
        {
            $this->calls[] = ['delete', $commentId, $trigger];
            self::softDelete($commentId, $slug);
        }

        /** 게시판 쪽에서 (관리자 화면 등) 지울 때도 같은 길 */
        public static function softDelete(int $commentId, string $slug): void
        {
            DB::table('board_comments')->where('id', $commentId)->update(['status' => 'deleted', 'deleted_at' => date('Y-m-d H:i:s')]);
            HookManager::doAction('sirsoft-board.comment.after_delete', (object) ['id' => $commentId], $slug);
        }

        public static function restore(int $commentId, string $slug): void
        {
            DB::table('board_comments')->where('id', $commentId)->update(['status' => 'published', 'deleted_at' => null]);
            HookManager::doAction('sirsoft-board.comment.after_restore', (object) ['id' => $commentId], $slug);
        }
    }
    $fake = new FakeBoard();
    MirrorService::useGateway($fake);

    $settings = ['enabled' => true, 'replace_slugs' => 'free', 'mirror_enabled' => true];
    BoardEnv::$settingsResolver = static function () use (&$settings): array {
        return $settings;
    };
    $setSettings = static function (array $s) use (&$settings): void {
        $settings = $s + $settings;
        BoardEnv::reset();
    };
    // 게시판 권한: 회원 10·11 은 읽기·쓰기, 비회원은 읽기만 / 비밀글은 작성자만
    $actor = 0;
    BoardPermission::$override = static function (string $kind, string $arg) use (&$actor): bool {
        if ($kind === 'secret') {
            return false;
        }
        if (str_ends_with($arg, '.comments.read')) {
            return ! str_starts_with($arg, 'sirsoft-board.notice.') || $actor > 0;
        }
        if (str_ends_with($arg, '.comments.write') && ! str_contains($arg, '.admin.')) {
            return $actor > 0;
        }

        return false;
    };

    foreach ([new Plugins\Custom\BoardComments\Listeners\CommentAccessListener(), new Plugins\Custom\BoardComments\Listeners\MirrorListener(), new Plugins\Custom\Comments\Listeners\TargetCleanupListener()] as $l) {
        HookManager::subscribe($l);
    }

    $ctl = new Plugins\Custom\Comments\Http\Controllers\CommentController();
    $req = static function (array $data, int $uid, string $method = 'POST') use (&$actor): Request {
        $actor = $uid;
        BoardEnv::reset();
        $r = Request::create('/x', $method, $data);
        $user = $uid ? App\Models\User::query()->find($uid) : null;
        $r->setUserResolver(static fn () => $user);

        return $r;
    };
    $json = static fn ($res): array => json_decode($res->getContent(), true) ?: [];
    $post = static fn (int $postId, string $body, int $uid = 10, int $parent = 0) => $ctl->targetStore($req(['body' => $body, 'parent_id' => $parent], $uid), 'board_post', $postId);
    $board = static fn (int $id) => DB::table('board_comments')->where('id', $id)->first();
    $link = static fn (int $cid) => DB::table(LinkTable::NAME)->where('custom_comment_id', $cid)->first();

    // ── 1. 권한 (custom-comments.target.access)
    $r = $ctl->targetIndex($req([], 0, 'GET'), 'board_post', 100);
    check($r->getStatusCode() === 200, '비회원 읽기: 게시판 읽기 권한대로 200');
    check($post(100, '비회원 글', 0)->getStatusCode() === 401, '비회원 쓰기: 401');
    check($ctl->targetIndex($req([], 10, 'GET'), 'board_post', 200)->getStatusCode() === 403, '바꾸기 아닌 게시판(notice): 403');
    check($ctl->targetIndex($req([], 10, 'GET'), 'board_post', 101)->getStatusCode() === 403, '비밀글: 작성자 아니면 403');
    check($ctl->targetIndex($req([], 11, 'GET'), 'board_post', 101)->getStatusCode() === 200, '비밀글: 작성자 200');
    check($ctl->targetIndex($req([], 1, 'GET'), 'board_post', 101)->getStatusCode() === 200, '비밀글: 사이트 관리자 200');
    check($post(102, '블라인드 글에 쓰기', 10)->getStatusCode() === 403, '블라인드 글: 쓰기 403');
    check($ctl->targetIndex($req([], 10, 'GET'), 'board_post', 9999)->getStatusCode() !== 200, '없는 글: 막힘');
    DB::table('boards')->where('id', 1)->update(['use_comment' => false]);
    check($ctl->targetIndex($req([], 10, 'GET'), 'board_post', 100)->getStatusCode() === 403, '댓글 사용 꺼진 게시판: 403');
    DB::table('boards')->where('id', 1)->update(['use_comment' => true]);
    DB::table('plugins')->update(['status' => 'inactive']);
    BoardEnv::reset();
    $mode = $json((new Plugins\Custom\BoardComments\Http\Controllers\ModeController())->show(100))['data'];
    check($mode['replace'] === false, 'custom-comments 꺼짐: 바꾸지 않음 (mode replace=false)');
    DB::table('plugins')->update(['status' => 'active']);

    // ── 2. 남기기: 등록 · 답글 · 수정 · 삭제
    $fake->calls = [];
    $j = $json($post(100, '첫 댓글 [[sticker:noto/smile.gif]]'));
    $c1 = (int) ($j['data']['id'] ?? 0);
    $l1 = $link($c1);
    check($c1 > 0 && $l1 && $l1->origin === 'mirror' && $l1->state === 'active', '등록 → 짝 표 mirror/active');
    $b1 = $board((int) $l1->board_comment_id);
    check($b1 && (int) $b1->post_id === 100 && (int) $b1->user_id === 10 && $b1->parent_id === null, '공식 댓글 생김 (같은 글 · 작성자)');
    check($b1 && $b1->content === '첫 댓글 [스티커]', '스티커 → [스티커] 글자');
    $c2 = (int) ($json($post(100, '답글', 11, $c1))['data']['id'] ?? 0);
    $b2 = $board((int) $link($c2)->board_comment_id);
    check($b2 && (int) $b2->parent_id === (int) $b1->id && (int) $b2->depth === 1, '답글 → 공식 답글 (부모 짝 맞춤)');
    $c3 = (int) ($json($post(100, '답글의 답글', 10, $c2))['data']['id'] ?? 0);
    $b3 = $board((int) $link($c3)->board_comment_id);
    check($b3 && (int) $b3->parent_id === (int) $b1->id, '깊이 한도(1) 넘는 답글 → 한도 안 조상에 붙임');
    check(count(array_filter($fake->calls, static fn ($c) => $c[0] === 'create')) === 3, '공식 댓글 만들기 3번 (중복 없음)');
    // 같은 훅이 두 번 와도 하나만
    HookManager::doAction('custom-comments.target_comment.after_create', $c1, 'board_post', 100, 10, 0);
    check(DB::table('board_comments')->count() === 3, '같은 훅 다시 와도 중복 없음');

    $r = $ctl->targetUpdate($req(['body' => '고친 댓글 [[img:digital-comments/t/board_post/100/a.jpg]]'], 10, 'PUT'), 'board_post', 100, $c1);
    check($r->getStatusCode() === 200, '수정 200');
    check($board((int) $b1->id)->content === '고친 댓글 [이미지] https://site.test/storage/digital-comments/t/board_post/100/a.jpg', '수정 → 공식 댓글 내용 · 이미지는 주소');

    // 지우기: custom-comments 는 바로 아래 답글까지 함께 지움 (화면의 답글은 한 단계)
    $ctl->targetDestroy($req([], 10, 'DELETE'), 'board_post', 100, $c3);
    check($board((int) $b3->id)->deleted_at !== null, '답글의 답글 지움 → 공식 댓글 지움');
    $fake->calls = [];
    $r = $ctl->targetDestroy($req([], 10, 'DELETE'), 'board_post', 100, $c1);
    check($r->getStatusCode() === 200, '삭제 200');
    check(DB::table('digital_product_comments')->where('digital_product_id', 100)->count() === 0, 'custom 댓글 · 답글 모두 지움');
    check(DB::table('board_comments')->whereNull('deleted_at')->count() === 0, '공식 댓글 3개도 지움 (소프트)');
    check(DB::table('board_comments')->whereNotNull('deleted_at')->count() === 3, '공식 댓글은 남아 있음 (deleted_at)');
    check($link($c1)->state === 'custom_deleted' && $link($c3)->state === 'custom_deleted', '짝 표 custom_deleted');
    check(($fake->calls[0][0] ?? '') === 'delete' && (int) ($fake->calls[0][1] ?? 0) === (int) $b2->id, '답글부터 지움');
    check(count($fake->calls) === 2, '되돌이 없음 (게시판 after_delete 가 custom 을 다시 지우지 않음)');

    // ── 3. 남기기 끄기 (기본 켜짐 · 끄면 아무것도 안 함 · 이미 남긴 것은 그대로)
    $k1 = (int) ($json($post(100, '켜진 동안'))['data']['id'] ?? 0);
    $kb = (int) $link($k1)->board_comment_id;
    $setSettings(['mirror_enabled' => false]);
    $nBoard = DB::table('board_comments')->count();
    $nLink = DB::table(LinkTable::NAME)->count();
    $k2 = (int) ($json($post(100, '꺼진 동안'))['data']['id'] ?? 0);
    check($k2 > 0 && DB::table('board_comments')->count() === $nBoard && DB::table(LinkTable::NAME)->count() === $nLink, '끄면 새 댓글 안 남김');
    $ctl->targetUpdate($req(['body' => '꺼진 동안 고침'], 10, 'PUT'), 'board_post', 100, $k1);
    check($board($kb)->content === '켜진 동안', '끄면 수정도 안 남김 (공식 댓글 그대로)');
    $ctl->targetDestroy($req([], 10, 'DELETE'), 'board_post', 100, $k1);
    check($board($kb)->deleted_at === null && $link($k1)->state === 'active', '끄면 삭제도 안 남김 (공식 댓글 · 짝 그대로)');
    FakeBoard::softDelete($kb, 'free');
    check($link($k1)->state === 'active', '끄면 게시판 쪽 삭제도 거꾸로 안 감');
    FakeBoard::restore($kb, 'free');
    $mode = $json((new Plugins\Custom\BoardComments\Http\Controllers\ModeController())->show(100))['data'];
    check($mode['replace'] === true && $mode['mirror'] === false && $mode['count'] === 1, 'mode: replace 켜짐 · mirror 꺼짐 · 수 1');
    $setSettings(['mirror_enabled' => true]);
    DB::table('digital_product_comments')->where('id', $k2)->delete();

    // ── 4. 게시판 쪽 삭제 · 복원 → custom 댓글
    $m1 = (int) ($json($post(103, '관리자가 지울 댓글', 11))['data']['id'] ?? 0);
    $mb = (int) $link($m1)->board_comment_id;
    FakeBoard::softDelete($mb, 'free');
    check(! DB::table('digital_product_comments')->where('id', $m1)->exists(), '게시판에서 지우면 custom 댓글도 지움');
    $ml = DB::table(LinkTable::NAME)->where('board_comment_id', $mb)->first();
    check($ml && $ml->state === 'board_deleted', '짝 표 board_deleted (보관)');
    FakeBoard::restore($mb, 'free');
    $ml = DB::table(LinkTable::NAME)->where('board_comment_id', $mb)->first();
    $row = DB::table('digital_product_comments')->where('id', $ml->custom_comment_id)->first();
    check($ml->state === 'active' && $row && $row->body === '관리자가 지울 댓글' && (int) $row->user_id === 11, '게시판에서 복원하면 custom 댓글 되살림');

    // ── 5. 옮기기 (migrate): 미리 보기 · 실제 · 다시 해도 같음
    DB::table('board_comments')->insert([
        ['id' => 501, 'board_id' => 1, 'post_id' => 103, 'user_id' => 10, 'parent_id' => null, 'author_name' => '홍길동', 'content' => '<p>예전 댓글</p>', 'is_secret' => 0, 'status' => 'published', 'depth' => 0, 'created_at' => '2026-01-02 03:04:05', 'updated_at' => '2026-01-02 03:04:05', 'deleted_at' => null],
        ['id' => 502, 'board_id' => 1, 'post_id' => 103, 'user_id' => null, 'parent_id' => 501, 'author_name' => '손님', 'content' => '비회원 답글', 'is_secret' => 0, 'status' => 'published', 'depth' => 1, 'created_at' => '2026-01-02 04:00:00', 'updated_at' => '2026-01-02 04:00:00', 'deleted_at' => null],
        ['id' => 503, 'board_id' => 1, 'post_id' => 103, 'user_id' => 11, 'parent_id' => null, 'author_name' => '임꺽정', 'content' => '비밀 댓글', 'is_secret' => 1, 'status' => 'published', 'depth' => 0, 'created_at' => '2026-01-02 05:00:00', 'updated_at' => '2026-01-02 05:00:00', 'deleted_at' => null],
        ['id' => 504, 'board_id' => 1, 'post_id' => 103, 'user_id' => 11, 'parent_id' => null, 'author_name' => '임꺽정', 'content' => '블라인드', 'is_secret' => 0, 'status' => 'blinded', 'depth' => 0, 'created_at' => '2026-01-02 06:00:00', 'updated_at' => '2026-01-02 06:00:00', 'deleted_at' => null],
        ['id' => 505, 'board_id' => 2, 'post_id' => 200, 'user_id' => 10, 'parent_id' => null, 'author_name' => '홍길동', 'content' => '다른 게시판', 'is_secret' => 0, 'status' => 'published', 'depth' => 0, 'created_at' => '2026-01-02 06:00:00', 'updated_at' => '2026-01-02 06:00:00', 'deleted_at' => null],
    ]);
    $nCustom = DB::table('digital_product_comments')->count();
    $boardBefore = DB::table('board_comments')->orderBy('id')->get()->map(static fn ($r) => (array) $r)->all();
    $slugs = Migrator::resolveSlugs([]);
    check($slugs === ['free'], '--board 없으면 설정의 바꾸기 게시판');
    $dry = (new Migrator())->run($slugs, true);
    check(DB::table('digital_product_comments')->count() === $nCustom, '미리 보기: 아무것도 안 씀');
    check($dry['migrated'] === 2 && $dry['skipped_secret'] === 1 && $dry['skipped_status'] === 1 && $dry['already'] >= 1, '미리 보기 수: 옮길 2 · 비밀 1 · 블라인드 건너뜀 · 남긴 댓글은 이미 짝 있음');
    $real = (new Migrator())->run($slugs, false);
    check($real['migrated'] === 2 && $real['errors'] === 0, '실제: 2개 옮김');
    $a = DB::table(LinkTable::NAME)->where('board_comment_id', 501)->first();
    $b = DB::table(LinkTable::NAME)->where('board_comment_id', 502)->first();
    $ra = DB::table('digital_product_comments')->where('id', $a->custom_comment_id)->first();
    $rb = DB::table('digital_product_comments')->where('id', $b->custom_comment_id)->first();
    check($ra->body === '예전 댓글' && (int) $ra->user_id === 10 && (string) $ra->created_at === '2026-01-02 03:04:05', '작성자 · 시간 · 본문(HTML 걷음) 그대로');
    check((int) $rb->parent_id === (int) $ra->id && (int) $rb->user_id === 0 && $b->author_name === '손님', '답글 부모 · 비회원 이름 보관');
    check(DB::table('board_comments')->orderBy('id')->get()->map(static fn ($r) => (array) $r)->all() === $boardBefore, '원래 공식 댓글은 그대로 (지우지 않음)');
    $again = (new Migrator())->run($slugs, false);
    check($again['migrated'] === 0 && DB::table('digital_product_comments')->count() === $nCustom + 2, '다시 돌려도 중복 없음');
    $tree = $json($ctl->targetIndex($req([], 0, 'GET'), 'board_post', 103))['data'] ?? [];
    $blob = json_encode($tree, JSON_UNESCAPED_UNICODE);
    check(str_contains($blob, '"author_name":"손님"'), '목록: 옮긴 비회원 댓글에 원래 이름');
    // 옮긴 댓글을 고치면 새 공식 댓글이 아니라 원래 댓글을 고침 (되돌이 · 중복 없음)
    $nb = DB::table('board_comments')->count();
    $ctl->targetUpdate($req(['body' => '예전 댓글 고침'], 10, 'PUT'), 'board_post', 103, (int) $ra->id);
    check(DB::table('board_comments')->count() === $nb && $board(501)->content === '예전 댓글 고침', '옮긴 댓글 수정 → 원래 공식 댓글 수정');
    // 옮긴 댓글에 답글 → 공식 답글은 원래 댓글 밑
    $rep = (int) ($json($post(103, '옮긴 댓글에 답글', 11, (int) $ra->id))['data']['id'] ?? 0);
    check((int) $board((int) $link($rep)->board_comment_id)->parent_id === 501, '옮긴 댓글에 단 답글 → 원래 공식 댓글 밑');

    // ── 6. 글 삭제 · 복원
    $n103 = DB::table('digital_product_comments')->where('target_type', 'board_post')->where('digital_product_id', 103)->count();
    $bodies = DB::table('digital_product_comments')->where('digital_product_id', 103)->orderBy('id')->pluck('body')->all();
    DB::table('board_posts')->where('id', 103)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    HookManager::$fired = [];
    HookManager::doAction('sirsoft-board.post.after_delete', (object) ['id' => 103], 'free', []);
    check(in_array('custom-comments.target.deleted', HookManager::$fired, true), '글 삭제 → custom-comments.target.deleted');
    check(DB::table('digital_product_comments')->where('digital_product_id', 103)->count() === 0, 'custom 댓글 정리됨');
    check(DB::table(LinkTable::NAME)->where('post_id', 103)->where('state', 'purged')->count() === $n103, '짝 표에 보관 (purged)');
    DB::table('board_posts')->where('id', 103)->update(['deleted_at' => null]);
    BoardEnv::reset();
    HookManager::doAction('sirsoft-board.post.after_restore', (object) ['id' => 103], 'free');
    $after = DB::table('digital_product_comments')->where('digital_product_id', 103)->orderBy('id')->get();
    check($after->pluck('body')->all() === $bodies, '글 복원 → custom 댓글 되살림 (같은 순서 · 본문)');
    $ra2 = DB::table('digital_product_comments')->where('id', DB::table(LinkTable::NAME)->where('board_comment_id', 501)->value('custom_comment_id'))->first();
    $rb2 = DB::table('digital_product_comments')->where('id', DB::table(LinkTable::NAME)->where('board_comment_id', 502)->value('custom_comment_id'))->first();
    check($ra2 && $rb2 && (int) $rb2->parent_id === (int) $ra2->id, '되살린 답글 부모 새 번호로');
    check(DB::table(LinkTable::NAME)->where('post_id', 103)->where('state', 'purged')->count() === 0, '짝 표 다시 active');

    // ── 7. 바꾸기 아닌 게시판 · 다른 대상은 건드리지 않음
    $setSettings(['replace_slugs' => '']);
    check($ctl->targetIndex($req([], 10, 'GET'), 'board_post', 100)->getStatusCode() === 403, '바꾸기 목록 비우면 board_post 댓글 API 막힘');
    $mode = $json((new Plugins\Custom\BoardComments\Http\Controllers\ModeController())->show(100))['data'];
    check($mode['replace'] === false, '바꾸기 목록 비우면 공식 댓글 그대로');

    echo "\n".($fails === 0 ? "OK ({$count} checks)" : "{$fails}/{$count} FAILED")."\n";
    exit($fails === 0 ? 0 : 1);
}
