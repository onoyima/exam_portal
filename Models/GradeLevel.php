<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'staff_type_id',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function staff_promotions()
    {
        return $this->hasMany(StaffPromotion::class);
    }
}
