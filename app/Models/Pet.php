<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    protected $guarded = [];
    protected $casts = ['vaccinated' => 'boolean', 'vaccination_expires_at' => 'date'];
    public const SPECIES = ['dog' => 'Köpek', 'cat' => 'Kedi', 'other' => 'Diğer'];
    public const SIZES = ['small' => 'Küçük', 'medium' => 'Orta', 'large' => 'Büyük'];
    public function owner() { return $this->belongsTo(User::class, 'user_id'); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function medicalDocuments() { return $this->hasMany(PetMedicalDocument::class); }
    public function dailyReports() { return $this->hasMany(DailyReport::class); }
    public function tasks() { return $this->hasMany(PetTask::class); }
    public function belongings() { return $this->hasMany(ReservationBelonging::class); }
    public function vaccinations() { return $this->hasMany(PetVaccination::class); }
    public function getSpeciesLabelAttribute(): string { return self::SPECIES[$this->species] ?? $this->species; }
    public function getSizeLabelAttribute(): string { return self::SIZES[$this->size] ?? $this->size; }
}
