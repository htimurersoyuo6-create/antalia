<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdoptionListing extends Model
{
 protected $connection = 'adoption';
 protected $fillable=['user_id','pet_name','species','breed','age_months','sex','vaccinated','city','district','description','photo_path','contact_phone','status','approved_at','adopted_at'];
 protected function casts(): array { return ['vaccinated'=>'boolean','approved_at'=>'datetime','adopted_at'=>'datetime']; }
 public function user(){
  $relation = $this->belongsTo(User::class);
  $relation->getQuery()->getModel()->setConnection(config('database.default'));
  return $relation;
 }
 public function getAgeLabelAttribute(): string { if(!$this->age_months) return 'Yaş belirtilmemiş'; return $this->age_months<12 ? $this->age_months.' aylık' : intdiv($this->age_months,12).' yaş'.($this->age_months%12 ? ' '.($this->age_months%12).' ay' : ''); }
}
