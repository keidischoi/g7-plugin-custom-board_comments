<?php

declare(strict_types=1);

/**
 * 0.2.0 바꾸기 모드 순수 규칙 (Laravel 없이): php tests/replace.php
 * - 설정 replace_slugs · mirror_enabled
 * - 권한 판정 (ReplaceRules::access)
 * - 본문 낮추기 · 옮기기 글 · 답글 깊이
 * - 레이아웃 바꾸기 (sirsoft-basic board/show 를 펼친 실제 모양 fixtures/board_show_merged.json.gz)
 */

require __DIR__.'/bootstrap.php';

use Plugins\Custom\BoardComments\Support\LayoutSwap;
use Plugins\Custom\BoardComments\Support\ReplaceRules as R;
use Plugins\Custom\BoardComments\Support\SettingsRules as S;

// ── 설정
$d = S::defaults();
expect('기본: 바꾸기 게시판 없음', $d['replace_slugs'], '');
expect('기본: 똑같이 남기기 켜짐', $d['mirror_enabled'], true);
expect('정규화: 슬러그 목록', S::normalize(['replace_slugs' => 'Free, qa ,free'])['replace_slugs'], 'free, qa');
expect('정규화: * 는 모든 게시판', S::normalize(['replace_slugs' => 'free, *'])['replace_slugs'], '*');
expect('정규화: 남기기 끄기', S::normalize(['mirror_enabled' => '0'])['mirror_enabled'], false);
expect('정규화: 예전 설정 파일(새 키 없음)', S::normalize(['enabled' => true, 'board_slugs' => 'free'])['mirror_enabled'], true);
expectFalse('빈 목록이면 바꾸지 않음 (적용 게시판과 달리)', S::isReplaced('free', ''));
expectTrue('목록에 있으면 바꿈', S::isReplaced('FREE', 'free, qa'));
expectFalse('목록에 없으면 그대로', S::isReplaced('notice', 'free'));
expectTrue('* 는 모두', S::isReplaced('notice', '*'));
expectTrue('예전 설정처럼 보이는 키에 replace_slugs', S::extractSettings(['replace_slugs' => 'free']) === ['replace_slugs' => 'free']);

// ── 권한
$base = ['enabled' => true, 'replaced' => true, 'post_exists' => true, 'use_comment' => true, 'user_id' => 10, 'can_read' => true, 'can_write' => true];
expectTrue('읽기: 권한 있으면', R::access($base + ['action' => 'read']));
expectFalse('읽기: 권한 없으면', R::access(['can_read' => false, 'action' => 'read'] + $base));
expectTrue('읽기: 관리자', R::access(['can_read' => false, 'is_admin' => true, 'action' => 'read'] + $base));
expectFalse('바꾸기 게시판 아님', R::access(['replaced' => false, 'action' => 'read'] + $base));
expectFalse('플러그인 확장 꺼짐', R::access(['enabled' => false, 'action' => 'read'] + $base));
expectFalse('없는 글', R::access(['post_exists' => false, 'action' => 'read'] + $base));
expectFalse('지운 글 (관리자도)', R::access(['post_deleted' => true, 'is_admin' => true, 'action' => 'read'] + $base));
expectFalse('댓글 사용 꺼짐', R::access(['use_comment' => false, 'action' => 'read'] + $base));
expectFalse('비밀글: 못 보는 사람', R::access(['is_secret' => true, 'can_view_secret' => false, 'action' => 'read'] + $base));
expectTrue('비밀글: 볼 수 있는 사람', R::access(['is_secret' => true, 'can_view_secret' => true, 'action' => 'read'] + $base));
expectTrue('비밀글: 관리자', R::access(['is_secret' => true, 'is_admin' => true, 'action' => 'read'] + $base));
expectTrue('쓰기: 권한 있는 회원', R::access($base + ['action' => 'write']));
expectFalse('쓰기: 비회원', R::access(['user_id' => 0, 'action' => 'write'] + $base));
expectFalse('쓰기: 권한 없음', R::access(['can_write' => false, 'action' => 'write'] + $base));
expectFalse('쓰기: 블라인드 글 (관리자도)', R::access(['post_blinded' => true, 'is_admin' => true, 'action' => 'write'] + $base));
expectTrue('읽기: 블라인드 글도 권한대로', R::access(['post_blinded' => true, 'action' => 'read'] + $base));
expectFalse('관리: 일반 회원', R::access($base + ['action' => 'moderate']));
expectTrue('관리: 게시판 관리 권한', R::access(['can_moderate' => true, 'action' => 'moderate'] + $base));
expectTrue('관리: 사이트 관리자', R::access(['is_admin' => true, 'action' => 'moderate'] + $base));

// ── 본문 낮추기
$url = static fn (string $p): string => 'https://site.test/storage/'.$p;
expect('스티커 코드 → [스티커]', R::degradeBody('좋아요 [[sticker:noto/smile.gif]]', $url), '좋아요 [스티커]');
expect('이미지 코드 → [이미지] 주소', R::degradeBody('[[img:digital-comments/t/board_post/3/a.jpg]] 봐요', $url), '[이미지] https://site.test/storage/digital-comments/t/board_post/3/a.jpg 봐요');
expect('짧은 코드 s: i:', R::degradeBody('[[s:p/x.png]][[i:http://x.test/a.png]]', $url), '[스티커][이미지] http://x.test/a.png');
expect('채팅 스티커만', R::degradeBody('', $url, true), '[스티커]');
expect('글 + 채팅 스티커', R::degradeBody('안녕', $url, true), '안녕 [스티커]');
expect('빈 글', R::degradeBody('  ', $url), '[스티커]');
expect('옮기기: 글 그대로', R::boardTextToBody("  첫 줄\n둘째 줄 "), "첫 줄\n둘째 줄");
expect('옮기기: HTML 걷어 냄', R::boardTextToBody('<p>a &amp; b</p><p>c<br>d</p>'), "a & b\nc\nd");
expect('답글 깊이: 한도 안이면 부모', R::pickBoardParent([['id' => 5, 'depth' => 0]], 10), 5);
expect('답글 깊이: 넘으면 위로', R::pickBoardParent([['id' => 7, 'depth' => 1], ['id' => 5, 'depth' => 0]], 1), 5);
expect('답글 깊이: 0 이면 최상위', R::pickBoardParent([['id' => 5, 'depth' => 0]], 0), 0);

// ── 레이아웃
$layout = json_decode((string) gzdecode((string) file_get_contents(__DIR__.'/fixtures/board_show_merged.json.gz')), true);
expectTrue('fixture: board/show 병합 모양', is_array($layout) && isset($layout['components']));
expectTrue('게시글 상세로 알아봄', LayoutSwap::isBoardShow($layout));
[$out, $res] = LayoutSwap::apply($layout);
expect('공식 댓글 칸 3곳 (basic · gallery · card) 바꿈', $res, 'swapped:3');
$blob = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
expect('상자 3개', substr_count($blob, '"id":"'.LayoutSwap::MOUNT_ID.'_'), 3);
expect('상자 번호 data-cbc-cc-post', substr_count($blob, '"data-cbc-cc-post":"{{post?.data?.id ?? route.id}}"'), 3);
expect('공식 칸은 replace 가 아닐 때만', substr_count($blob, '&& !(cbc_board_comments?.data?.replace === true)}}'), 3);
expect('상자는 replace 일 때만', substr_count($blob, '&& (cbc_board_comments?.data?.replace === true)}}'), 3);
expectTrue('권한 없음 안내 칸은 그대로', str_contains($blob, '"{{post?.data?.board?.use_comment && !post?.data?.abilities?.can_read_comments && (!post?.data?.is_secret || post?.data?.content !== null)}}"'));
expect('제목 옆 댓글 수 바꿈', substr_count($blob, 'cbc_board_comments.data.count : post.data.comment_count'), 3);
$ds = array_values(array_filter($out['data_sources'], static fn ($x) => ($x['id'] ?? '') === LayoutSwap::DATA_SOURCE));
expect('데이터 소스 하나', count($ds), 1);
expect('데이터 소스 주소', $ds[0]['endpoint'] ?? '', '/api/plugins/custom-board_comments/posts/{{route.id}}/mode');
expect('데이터 소스 실패 → replace false', $ds[0]['fallback']['data']['replace'] ?? null, false);
expect('두 번 해도 한 번만', LayoutSwap::apply($out)[1], 'skip:already');
expect('편집기 응답은 그대로', LayoutSwap::apply($layout + ['__editor' => ['original' => []]])[1], 'skip:editor');
$other = $layout;
$other['data_sources'] = [['id' => 'x', 'endpoint' => '/api/modules/custom-wiki/pages/{{route.id}}']];
expect('다른 화면은 그대로', LayoutSwap::apply($other)[1], 'skip:not-board-show');
// 테마 구조가 달라 댓글 칸을 못 찾으면 그대로 (안전)
$changed = $layout;
$changed['components'] = json_decode(str_replace('can_read_comments', 'can_view_comments', json_encode($layout['components'])), true);
[$same, $why] = LayoutSwap::apply($changed);
expect('댓글 칸 못 찾으면 그대로', $why, 'skip:no-comment-section');
expectTrue('못 찾으면 레이아웃 변화 없음', $same === $changed);
// 조건식이 {{ }} 하나가 아니면 그 칸은 건드리지 않음
$weird = ['data_sources' => $layout['data_sources'], 'components' => [['type' => 'basic', 'name' => 'Div', 'if' => 'x {{post?.data?.board?.use_comment && post?.data?.abilities?.can_read_comments}}', 'children' => [['text' => '{{post?.data?.comments}}']]]]];
expect('이상한 조건식은 건너뜀', LayoutSwap::apply($weird)[1], 'skip:no-comment-section');

finish();
