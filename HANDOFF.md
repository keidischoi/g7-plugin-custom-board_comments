# HANDOFF — custom-board_comments

마지막 갱신: 2026-10-04 (0.2.0)

## 지금 상태

- 0.1.x: 공식 `sirsoft-board` 댓글 칸에 추천 · 베스트 · 정렬 · 스티커 · 이미지 (프론트 `dist/js/plugin.iife.js` + `/posts/{id}/likes`, `/media`)
- 0.2.0: **바꾸기 모드** + **똑같이 남기기** + **옮기기 명령** (CHANGELOG · README "바꾸기 모드" 참고). 기본은 꺼짐(`replace_slugs` 빈 값) → 0.1.25 와 같은 동작
- 짝 플러그인: custom-comments **1.0.1** (target_comment.after_update/after_delete 훅, target_comments.presented 필터 추가)

## 설계 (0.2.0)

| 파일 | 역할 |
| --- | --- |
| `src/Support/SettingsRules.php` | `replace_slugs`(빈 값 = 끔, `*` = 모두) · `mirror_enabled`(기본 true) |
| `src/Support/ReplaceRules.php` | 권한 판정 · 본문 낮추기(스티커/이미지 → 글) · 옮길 글 정리 · 답글 깊이 |
| `src/Support/LayoutSwap.php` | 글 상세 레이아웃에서 공식 댓글 칸 `if`(`use_comment && can_read_comments`) 를 찾아 상자 Div + 데이터 소스 `cbc_board_comments` 추가. 못 찾으면 그대로 |
| `src/Support/BoardEnv.php` | 설정 · custom-comments 활성/버전 · 글/게시판 조회 · `replaceActive` / `mirrorActive` |
| `src/Support/BoardPermission.php` | 게시판 트레이트 `ChecksBoardPermission` · `SecretContentGate` 호출 (테스트는 `$override`) |
| `src/Support/LinkTable.php` | `custom_board_comment_links` (첫 사용 때 생성) |
| `src/Services/MirrorService.php` | custom → 공식 (등록/수정/삭제), 공식 → custom (짝 있는 댓글 삭제 · 블라인드 · 복원), 글 삭제 · 복원. `$busy` 로 되돌이 막음 |
| `src/Services/G7BoardCommentGateway.php` | 게시판 `CommentService` 호출 (테스트는 `MirrorService::useGateway`) |
| `src/Services/Migrator.php` + `src/Console/Commands/MigrateCommand.php` | `custom-board_comments:migrate` |
| `src/Listeners/*` | access 필터 · 레이아웃 필터(우선순위 90) · 남기기/글 훅 + presented 필터(비회원 이름) |
| `src/Http/Controllers/ModeController.php` | `GET /posts/{id}/mode` → `{replace, mirror, count}` (늘 200, no-store) |
| `resources/js/replace.ts` | 상자에 `CustomComments.mountTarget(el,'board_post',id)`. 상자가 있으면 0.1.x 꾸미기는 하지 않음 |
| `src/Providers/BoardCommentsServiceProvider.php` | 콘솔 명령 등록만 |

되돌이 방지: 남기기로 생긴 공식 댓글 / 옮긴 원래 댓글 모두 짝 표에 `board_comment_id` 가 있음 → 옮기기는 건너뜀, 다시 남기지 않음. 게시판 훅은 `MirrorService::$busy` 동안 무시.

## 검사

```bash
php tests/run.php                        # PHP 전부 + vitest
G7_VENDOR=/workspace/g7vendor/vendor php tests/replace_db.php   # 통합 (custom-comments 소스는 ../g7-plugin-custom-comments)
npm run build && git checkout dist/js/plugin.iife.v021.js       # 빌드가 예전 번들 파일을 지움 → 되돌릴 것
```

실제 G7 · NAS 에서는 아직 확인하지 않음 (가짜 게시판 서비스 + SQLite + 진짜 custom-comments 컨트롤러로만 확인).

## 다음 할 일 / 확인할 것

- NAS 에서: `free` 게시판 켠 뒤 글 상세 · 댓글 등록 · 알림 · 관리자 댓글 화면 · 댓글 수 확인, `migrate --dry-run` 수 확인
- 비회원 비밀글 비밀번호 열람자는 custom 댓글이 막힐 수 있음 (토큰 헤더가 custom-comments 요청에 안 붙음)
- 목록 화면 댓글 수는 공식 댓글 수 (남기기 켬 기준으로 맞음)
- 게시판 통째 삭제는 처리하지 않음
- 테마 구조가 바뀌면 `LayoutSwap` 이 `skip:no-comment-section` → 공식 댓글 그대로 (안전). fixture: `tests/fixtures/board_show_merged.json.gz`
