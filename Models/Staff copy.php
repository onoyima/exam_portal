<?php

namespace App\Models;

use Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    const ROLE_STAFF = 3;
    const ROLE_GUEST = 4;
    const ROLE_MGT = 5;
    const ROLE_SERVICE = 6;

    protected $fillable = [
        'title',
        'fname',
        'mname',
        'lname',
        'email',
        'maiden_name',
        'gender',
        'dob',
        'country_id',
        'state_id',
        'lga_name',
        'city',
        'marital_status',
        'religion',
        'phone',
        'address',
        'p_email'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course_assigneds()
    {
        return $this->hasMany(CourseAssigned::class);
    }

    public function correction_request_approvals()
    {
        return $this->hasMany(CorrectionRequestApproval::class);
    }

    public function activity_logs()
    {
        return $this->hasMany(ActivityLog::class, 'causer_id', 'id');
    }

    public function result_approval_process()
    {
        return $this->hasMany(ResultApprovalProcess::class);
    }

    public function registration_approval()
    {
        return $this->hasMany(RegistrationApproval::class);
    }

    public function waiver_student()
    {
        return $this->hasMany(WaiverStudent::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function staff_work_profile()
    {
        //return $this->hasOne(model, foreign_id, primary_id);
        return $this->hasOne(StaffWorkProfile::class);
    }

    public function staff_leave_summaries()
    {
        return $this->hasMany(StaffLeaveSummary::class);
    }

    public function staff_assigned_roles()
    {
        return $this->hasMany(StaffAssignedRole::class);
    }

    public function role_users()
    {
        return $this->hasMany(RoleUser::class);
    }

    public function grade_levels()
    {
        return $this->hasMany(GradeLevel::class);
    }

    public function correction_requests()
    {
        return $this->hasMany(CorrectionRequest::class);
    }



    public function userType()
    {

        if($this->user_type == self::ROLE_STAFF)
        {
            return true;
        }
        if($this->user_type == self::ROLE_GUEST)
        {
            return true;
        }
        if($this->user_type == self::ROLE_MGT)
        {
            return true;
        }
        if($this->user_type == self::ROLE_SERVICE)
        {
            return true;
        }
        return false;
    }

}
