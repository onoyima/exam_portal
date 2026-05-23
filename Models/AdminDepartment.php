<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminDepartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'academic_department_id',
    ];

    public function staff_work_profiles()
    {
        return $this->hasMany(StaffWorkProfile::class);
    }

    public function staff_promotions()
    {
        return $this->hasMany(StaffPromotion::class);
    }
}

