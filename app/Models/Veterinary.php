<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Veterinary extends Model
{
    protected $table = 'veterinaries';
    protected $guarded = [];
    protected $casts = [
        'opening_hours' => 'array',
        'services'      => 'array',
        'lat'           => 'float',
        'lng'           => 'float',
        'rating'        => 'float',
        'is_24h'        => 'boolean',
        'is_published'  => 'boolean',
        'featured'      => 'boolean',
    ];

    public function district() { return $this->belongsTo(District::class); }
    public function city()     { return $this->belongsTo(City::class); }

    public function scopePublished($q) { return $q->where('is_published', 1); }

    protected static function booted(): void
    {
        static::saving(function (self $v) {
            if (blank($v->slug)) {
                $temel = Str::slug((string) $v->name) ?: 'veteriner';
                $slug  = $temel;
                $i     = 2;
                while (static::query()->where('slug', $slug)
                    ->where('id', '!=', (int) ($v->id ?? 0))->exists()) {
                    $slug = $temel . '-' . $i++;
                }
                $v->slug = $slug;
            }
        });
    }

    public function telHref(): string
    {
        $d = preg_replace('/\D+/', '', (string) $this->phone);
        if ($d === '') return '';
        $d = ltrim($d, '0');
        if (strlen($d) === 10) $d = '90' . $d;
        return 'tel:+' . $d;
    }
}
