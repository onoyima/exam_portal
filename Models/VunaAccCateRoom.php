<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VunaAccCateRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'vuna_accomodation_category_id',
        'vu_session_id',
        'room',
        'room_no',
    ];

    public function vuna_accomodation_category()
    {
        return $this->belongsTo(VunaAccomodationCategory::class);
    }

    public function vuna_acc_cate_flats()
    {
        return $this->hasMany(VunaAccCateFlat::class);
    }

    public function vuna_acc_cate_bunks()
    {
        return $this->hasMany(VunaAccCateBunk::class);
    }

    public function vuna_accomodation_histories()
    {
        return $this->hasMany(VunaAccomodationHistory::class);
    }

}
