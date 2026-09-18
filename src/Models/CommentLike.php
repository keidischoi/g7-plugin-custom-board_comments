<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 댓글 추천 행.
 *
 * @property int $id
 * @property int $board_id
 * @property int $post_id
 * @property int $comment_id
 * @property int $user_id
 * @property string $guest_hash
 * @property string|null $ip_address
 */
class CommentLike extends Model
{
    public $timestamps = false;

    protected $table = \Plugins\G7\Plugin\Custom\BoardComments\Support\LikeTable::NAME;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'board_id',
        'post_id',
        'comment_id',
        'user_id',
        'guest_hash',
        'ip_address',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'board_id' => 'integer',
            'post_id' => 'integer',
            'comment_id' => 'integer',
            'user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
