<?php

namespace Plugins\Custom\BoardComments\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 댓글에 붙인 이미지 행.
 *
 * @property int $id
 * @property string $user_id
 * @property int $post_id
 * @property string $disk_name
 * @property string $mime
 * @property int $size
 */
class CommentMedia extends Model
{
    public $timestamps = false;

    protected $table = \Plugins\Custom\BoardComments\Support\MediaTable::NAME;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'post_id',
        'disk_name',
        'mime',
        'size',
        'created_at',
    ];
}
