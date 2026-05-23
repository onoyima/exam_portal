<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorrectionRequest extends Model
{
    use HasFactory;


    protected $fillable = [
        'student_id',
        'staff_id',
        'course_reg_id',
        'staff_type',
        'level',
        'Initial',
        'subject',
        'description',
        'old_ca_one',
        'old_ca_two',
        'old_ca_three',
        'old_examination',
        'new_ca_one',
        'new_ca_two',
        'new_ca_three',
        'new_examination',
        'academic_session_id',
        'vu_semester_id',
        'course_study_id',
        'department_id',
        'stage',

    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function course_reg()
    {
        //return $this->hasOne(model, foreign_id, primary_id);
        return $this->belongsTo(CourseReg::class);
    }

    public function academic_session()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function vu_semester()
    {
        return $this->belongsTo(VuSemester::class);
    }

}
