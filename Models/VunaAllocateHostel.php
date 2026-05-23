<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VunaAllocateHostel extends Model
{
    use HasFactory;

    protected $fillable = [
        'vuna_accomodation_category_id',
        'course_study_id',
        'level',
    ];

    public function vuna_accomodation_category()
    {
        return $this->belongsTo(VunaAccomodationCategory::class);
    }
}
