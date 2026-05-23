<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VunaAccomodation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'gender',
        'room_capacity',
        'price',
    ];

    public function vuna_accomodation_categories()
    {
        return $this->hasMany(VunaAccomodationCategory::class);
    }

    public function vuna_accomodation_histories()
    {
        return $this->hasMany(VunaAccomodationHistory::class);
    }

    public function vuna_acc_cate_bunks()
    {
        return $this->hasMany(VunaAccCateBunk::class);
    }
}
