<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Antalya ilceleri. districts tablosunda created_at/updated_at YOK,
 * bu yuzden $timestamps = false.
 */
class District extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function city() { return $this->belongsTo(City::class); }

    /** Ilcedeki tum oteller (ilce sayfalarinda ve ana sayfada kullanilir). */
    public function hotels() { return $this->hasMany(Hotel::class, 'district_id'); }

    /** Sadece aktif (yayinda) oteller. */
    public function aktifOteller()
    {
        return $this->hasMany(Hotel::class, 'district_id')->where('status', 'active');
    }
}