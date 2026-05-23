<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class ActivityLog extends SpatieActivity
{
    protected $table = 'activity_log';

    use HasFactory;

    protected $fillable = [
        'department_id',
        'admin_department_id',
        'log_name',
        'event',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'ip_address',
        'mac_address',
        'browser',
        'os',
        'device',
        'location',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'causer_id', 'id');
    }

    public function course_reg()
    {
        return $this->belongsTo(CourseReg::class, 'subject_id', 'id');
    }

}
