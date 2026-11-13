<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class ForumTopic extends Model {
    protected $fillable=['user_id','category','title','body','slug','status','views','publish_at'];

    protected $casts = ['publish_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $topic): void {
            if (filled($topic->slug)) return;

            $base = Str::slug($topic->title) ?: 'konu';
            $slug = $base;
            $suffix = 2;
            while (static::query()->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $suffix++;
            }
            $topic->slug = $slug;
        });
    }
    public function user(){return $this->belongsTo(User::class);}
    public function replies(){return $this->hasMany(ForumReply::class);}
    public function getRouteKeyName(){return 'slug';}
}