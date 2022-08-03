<?php

namespace LiveIntent\LaravelCommon\Tests\Fixtures\App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    use SoftDeletes;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['title', 'body', 'user_id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'meta' => 'array'
    ];

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasOne
     */
    public function meta()
    {
        return $this->hasOne(PostMeta::class);
    }

    /**
     * @return HasOne
     */
    public function image()
    {
        return $this->hasOne(PostImage::class);
    }

    /**
     * @param Builder $query
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    public function scopePublished(Builder $query)
    {
        return $query->where('publish_at', '<', Carbon::now());
    }

    public function scopePublishedAt(Builder $query, string $dateTime)
    {
        return $query->where('publish_at', $dateTime);
    }

    /**
     * @param Builder $query
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    public function scopeWithMeta(Builder $query)
    {
        return $query->whereNotNull('meta');
    }
}
