<?php

namespace Plugins\Custom\BoardComments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Plugins\Custom\BoardComments\Support\BoardEnv;
use Plugins\Custom\BoardComments\Support\ReplaceRules;

/**
 * 0.2.0 게시글 상세의 데이터 소스 cbc_board_comments: 이 글의 댓글을 custom-comments 로 보일지 + 댓글 수.
 * 늘 200 으로 답합니다 (모르면 replace=false → 공식 댓글 그대로).
 */
class ModeController
{
    public function show(int $postId): JsonResponse
    {
        $data = ['replace' => false, 'mirror' => false, 'count' => null, 'target_type' => ReplaceRules::TARGET, 'post_id' => $postId];
        try {
            $post = BoardEnv::post($postId);
            $board = $post ? BoardEnv::board((int) $post->board_id) : null;
            if ($post && $board && BoardEnv::replaceActive((string) $board->slug)) {
                $data['replace'] = true;
                $data['mirror'] = BoardEnv::mirrorActive((string) $board->slug);
                $data['slug'] = (string) $board->slug;
                $data['count'] = (int) DB::table('digital_product_comments')
                    ->where('target_type', ReplaceRules::TARGET)
                    ->where('digital_product_id', $postId)
                    ->count();
            }
        } catch (\Throwable) {
            $data['replace'] = false;
        }

        return response()->json(['success' => true, 'data' => $data])
            ->header('Cache-Control', 'no-store');
    }
}
