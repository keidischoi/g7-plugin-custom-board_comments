# custom-board_comments

그누보드7 공식 게시판(`sirsoft-board`) 댓글에 **추천**, **베스트 댓글**, **정렬**을 붙이는 플러그인입니다. 테마와 게시판 모듈 파일은 수정하지 않습니다.

버전 **0.2.0**. 관리자에 보이는 이름은 **게시판 댓글**입니다. 설치 폴더는 `plugins/custom-board_comments`입니다. GitHub 저장소 이름은 `g7-plugin-custom-board_comments` 그대로입니다. 처음 연결 대상은 [자유게시판](https://3ds.liveon.synology.me/board/free) 입니다. 목록이 아니라 **글 제목을 연 화면**(`/board/free/{번호}`)에 댓글 정렬·스티커·이미지 버튼이 보입니다.

0.1.3 이전 버전은 설치·활성화 때 사이트 전체가 Laravel **Server Error**가 나거나, 글 상세에 정렬 바가 안 보일 수 있습니다. 그 경우 플러그인을 완전히 제거한 뒤 최신 버전을 다시 설치하세요. GitHub 저장소 이름으로 설치된 `plugins/g7-plugin-custom-board_comments`도 제거한 뒤 `plugins/custom-board_comments`로 다시 설치하세요.

## 기능

- 댓글 추천 토글 (기본: 로그인 회원만, 설정에서 비회원 IP 1회 허용)
- 추천 수가 기준 이상이면 **베스트** 배지와 상단 고정
- 최신순 / 등록순 / 추천순
- 댓글 스티커 삽입 (설정에서 12·24·48·96개 배수 / 전체, 움직이는 스티커)
- 스티커 창은 바깥을 누르거나 포커스를 잃으면 닫힘
- 댓글 이미지 삽입 (로그인 회원, 2MB, jpg/png/gif/webp)
- 적용할 게시판 슬러그 (기본 `free`, 비우면 전체)
- 다크 테마에 맞춘 추천 버튼·베스트 배지

이 기능은 자체 게시판을 소유하지 않고 기존 댓글 화면을 확장하므로 모듈이 아니라 플러그인입니다.

## 요구 사항

- 그누보드7 `>=7.0.0`
- 공식 `sirsoft-board` 댓글이 있는 게시글 상세 (없으면 정렬 바만 표시)
- PHP `^8.2`

설치는 게시판 모듈이 꺼져 있어도 실패하지 않습니다. 댓글 칸에 기능을 붙이려면 `sirsoft-board`를 켜 두세요.

## 설치

관리자 플러그인 목록에서 **수동 설치 → GitHub** 로 저장소 전체 URL을 넣습니다.

`https://github.com/keidischoi/g7-plugin-custom-board_comments`

또는 플러그인 디렉터리를 `plugins/custom-board_comments`에 배치한 다음 실행합니다.

```bash
php artisan plugin:install custom-board_comments
php artisan plugin:activate custom-board_comments
php artisan cache:clear
```

설치 후 **게시판 댓글** 설정에서 추천·베스트를 조절하세요. 기본 적용 게시판은 `free`(자유게시판)입니다. 게시글 상세는 하드 리프레시가 필요합니다.

테이블: `custom_board_comment_likes` (첫 추천/조회 때 생성, 플러그인 제거 시 dynamic tables로 정리).

## 공개 API

Prefix: `/api/plugins/custom-board_comments`

| Method | Path | Auth | 설명 |
| --- | --- | --- | --- |
| GET | `/settings` | 없음 | 프론트에 공개되는 설정 |
| GET | `/posts/{postId}/likes?slug=` | 선택 | 게시글 댓글별 추천 수·내 추천·베스트 ID |
| POST | `/media` | 회원 | 댓글 이미지 업로드. multipart `file`, `post_id` |
| GET | `/media/{id}` | 없음 | 올린 이미지 표시 |
| GET | `/posts/{postId}/mode` | 없음 | 0.2.0 바꾸기 여부 · 남기기 여부 · custom 댓글 수 (늘 200) |
| GET | `/admin/settings` | 관리자 (`core.plugins.read`) | 관리자 설정 조회 |
| PUT/POST | `/admin/settings` | 관리자 (`core.plugins.update`) | 관리자 설정 저장 |

비회원 추천이 꺼져 있으면 비로그인 POST는 401입니다.

## 설정

| 키 | 기본 | 설명 |
| --- | --- | --- |
| `enabled` | true | 확장 사용 |
| `allow_guest_likes` | false | 비회원 IP 추천 |
| `best_enabled` | true | 베스트 댓글 |
| `best_threshold` | 5 | 베스트가 되는 최소 추천 수 |
| `best_limit` | 3 | 상단 고정 개수 |
| `default_sort` | latest | latest / oldest / popular |
| `board_slugs` | `free` | 적용 게시판. 비우면 전체 |
| `style_enabled` | true | 추천 버튼·배지 CSS |
| `stickers_enabled` | true | 스티커 삽입 |
| `sticker_pack` | `full` | 12 / 24 / 48 / 96 / 192 / 384 / full |
| `stickers_animated` | true | 스티커가 뛰고 흔들림 |
| `images_enabled` | true | 이미지 삽입 |
| `toolbar_collapsed` | true | 떠 있는 정렬·스티커·이미지 막대를 스티커만 보이게 접어 둠. 마우스·포커스·탭으로 펼침 |
| `replace_slugs` | (빈 값) | 0.2.0 바꾸기 게시판. 쉼표 목록, `*` = 모든 게시판, 비우면 끔 |
| `mirror_enabled` | true | 0.2.0 바꾸기 게시판의 custom 댓글을 공식 게시판 댓글에 똑같이 남김 |

## 바꾸기 모드 (0.2.0)

고른 게시판의 글 상세에서 공식 댓글 칸 대신 [custom-comments](https://github.com/keidischoi/g7-plugin-custom-comments) 댓글(이모지 · 스티커 · 이미지 · 좋아요)을 보입니다. 게시판 모듈 · 테마 · 코어는 고치지 않습니다.

필요: custom-comments **1.0.1 이상** 활성 (1.0.0 이면 남기기는 등록만).

### 켜는 순서

1. 관리자 → 플러그인 → **게시판 댓글** 설정 → **바꾸기 게시판** 에 `free` (쉼표로 여러 개, `*` 는 모두) → **게시판 댓글에 똑같이 남기기** 는 기본 켬 → 저장
2. 예전 댓글 옮기기 — 먼저 미리 보기:
   ```bash
   php artisan custom-board_comments:migrate --board=free --dry-run
   php artisan custom-board_comments:migrate --board=free
   ```
3. 글 상세 하드 리프레시

끄려면 바꾸기 게시판을 비우면 됩니다 (공식 댓글 칸이 바로 돌아옴. 남기기를 켜 두었으면 그동안의 댓글도 공식 댓글에 있음).

### 동작

- 권한: `custom-comments.target.access` 필터에 `board_post` 로 답함. 읽기 `sirsoft-board.{slug}.comments.read`, 쓰기 `…comments.write` (로그인 회원만), 관리 = 사이트 관리자 · 게시판 관리 권한. 비밀글은 작성자 · 볼 수 있는 사람만, 블라인드 글은 쓰기 막힘, 삭제 글 · `use_comment` 꺼짐은 모두 막힘
- 화면: `core.layout_extension.after_apply` 로 글 상세 레이아웃의 공식 댓글 칸 옆에 상자(`data-cbc-cc-post`)를 두고 데이터 소스 `cbc_board_comments`(`GET /posts/{id}/mode`) 가 `replace=true` 일 때만 공식 칸을 숨기고 상자를 보임. 테마 구조를 못 찾으면 레이아웃을 건드리지 않음. custom-comments 가 꺼지면 `replace=false`
- 남기기(`mirror_enabled`): custom 댓글 등록 · 수정 · 삭제 → 공식 `CommentService` (알림 · 댓글 수 · 관리자 · 검색). 게시판 관리자 삭제 · 블라인드 · 복원 → 짝 있는 custom 댓글에 적용. 짝 표 `custom_board_comment_links`
- 글 삭제 → custom 댓글 정리(`custom-comments.target.deleted`), 스냅숏 · 이미지 보관 → 글 복원 때 되살림
- 옮기기: 작성자 · 시간 · 답글 부모 유지, 비회원 이름은 짝 표에 보관해 목록에 표시. 비밀 · 삭제 · 블라인드 댓글은 건너뜀 (`--include-secret` 으로 비밀 포함). 원래 댓글은 지우지 않음. 여러 번 돌려도 같음

### 한계

- custom 댓글은 로그인 회원만 씀 (비회원 댓글 쓰기 없음, 옮긴 비회원 댓글은 수정 불가)
- custom-comments 에는 비밀 댓글이 없음 → 옮기기에서 기본 제외
- 공식 댓글 쪽에서 쓴 댓글은 custom 으로 오지 않음 (바꾸기 게시판에선 공식 칸이 숨겨져 있으므로 보통 없음). 좋아요는 남기지 않음, 글 삭제 · 복원 때 좋아요는 사라짐
- 스티커 · 이미지는 공식 댓글에 `[스티커]`, `[이미지] 주소` 글로 남음
- 목록의 댓글 수는 공식 댓글 수 (남기기를 끄면 custom 댓글 수와 달라짐). 제목 옆 수는 새로 고침 때 바뀜
- 레이아웃 바꾸기는 sirsoft-basic 글 상세 구조에 맞춤 (다른 테마 · 구조가 바뀌면 바꾸지 않음)

## 테스트

G7 코어 없이 설정·추천·정렬 규칙과 레이아웃 JSON, 프론트 로직을 검증합니다.

```bash
php tests/run.php
npm test
# 바꾸기 모드 통합 검사 (Laravel vendor + custom-comments 소스 필요, 없으면 건너뜀)
G7_VENDOR=/path/to/g7/vendor CUSTOM_COMMENTS_DIR=../g7-plugin-custom-comments php tests/replace_db.php
```

프론트 번들:

```bash
npm install
npm run build
```

배포물에는 `plugin.json`이 선언한 다음 파일이 포함되어야 합니다.

- `dist/js/plugin.iife.js`
- `dist/css/plugin.css`

## 식별자

| 항목 | 값 |
|------|-----|
| identifier | `custom-board_comments` |
| vendor | `custom` |
| namespace | `Plugins\\Custom\\BoardComments` |
| 설치 폴더 | `plugins/custom-board_comments` |
| github_url | `https://github.com/keidischoi/g7-plugin-custom-board_comments` |
| version | `0.2.0` |

## 라이선스

MIT
