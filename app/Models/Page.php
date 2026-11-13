<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** pages tablosu: slug + baslik + govde + meta aciklamasi. */
class Page extends Model
{
    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Slug ile tek kayit getirir; yoksa null. */
    public static function slugda(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }
}