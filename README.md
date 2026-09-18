# g7-plugin-custom-board_comments

그누보드7 공식 게시판(`sirsoft-board`) 댓글에 **추천**, **베스트 댓글**, **정렬**을 붙이는 플러그인입니다. 테마와 게시판 모듈 파일은 수정하지 않습니다.

버전 **0.1.3**. 처음 연결 대상은 [자유게시판](https://3ds.liveon.synology.me/board/free) 입니다. 목록이 아니라 **글 제목을 연 화면**의 댓글 칸에 추천/정렬이 붙습니다.

0.1.2까지는 플러그인이 켜지면 사이트 부팅이 깨져 Laravel **Server Error**가 날 수 있습니다. 그 경우 플러그인을 제거한 뒤 **0.1.3**을 다시 설치하세요.

## 기능

- 댓글 추천 토글 (기본: 로그인 회원만, 설정에서 비회원 IP 1회 허용)
- 추천 수가 기준 이상이면 **베스트** 배지와 상단 고정
- 최신순 / 등록순 / 추천순
- 적용할 게시판 슬러그 (기본 `free`, 비우면 전체)
- 다크 테마에 맞춘 추천 버튼·베스트 배지
- 댓글 삭제 훅에서 추천 행 정리

이 기능은 자체 게시판을 소유하지 않고 기존 댓글 화면을 확장하므로 모듈이 아니라 플러그인입니다.

## 요구 사항

- 그누보드7 `>=7.0.0`
- PHP `^8.2`
- 모듈 `sirsoft-board` 활성화

## 설치

관리자 플러그인 목록에서 **수동 설치 → GitHub** 로 저장소 전체 URL을 넣습니다.

`https://github.com/keidischoi/g7-plugin-custom-board_comments`

또는 CLI:

```bash
php artisan plugin:install g7-plugin-custom-board_comments
php artisan plugin:activate g7-plugin-custom-board_comments
php artisan cache:clear
```

설치 후 **게시판 댓글 확장** 설정에서 추천·베스트를 조절하세요. 기본 적용 게시판은 `free`(자유게시판)입니다. 게시글 상세는 하드 리프레시가 필요합니다.

테이블: `custom_board_comment_likes` (플러그인 제거 시 dynamic tables로 정리).

## 공개 API

Prefix: `/api/plugins/g7-plugin-custom-board_comments`

| Method | Path | Auth | 설명 |
| --- | --- | --- | --- |
| GET | `/settings` | 없음 | 프론트에 공개되는 설정 |
| GET | `/posts/{postId}/likes?slug=` | 선택 | 게시글 댓글별 추천 수·내 추천·베스트 ID |
| POST | `/comments/{commentId}/like` | 선택 | 추천 토글. body: `board_id`, `post_id` |

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

## 테스트

G7 코어 없이 설정·추천·정렬 규칙과 레이아웃 JSON, 프론트 로직을 검증합니다.

```bash
php tests/run.php
npm test
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
| identifier | `g7-plugin-custom-board_comments` |
| vendor | `g7` |
| namespace | `Plugins\\G7\\Plugin\\Custom\\BoardComments` |
| github_url | `https://github.com/keidischoi/g7-plugin-custom-board_comments` |
| version | `0.1.3` |

## 라이선스

MIT
