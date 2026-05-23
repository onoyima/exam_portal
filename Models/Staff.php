<?php

namespace App\Models\ERP;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $connection = 'erp_mysql';

    protected $table = 'staff';

    const ROLE_STAFF = 3;
    const ROLE_GUEST = 4;
    const ROLE_MGT = 5;
    const ROLE_SERVICE = 6;

    protected $fillable = [
        'title', 'fname', 'mname', 'lname', 'email',
        'maiden_name', 'gender', 'dob', 'country_id',
        'state_id', 'lga_name', 'city', 'marital_status',
        'religion', 'phone', 'address', 'p_email',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function ucccUser()
    {
        return UcccUser::where('erp_user_id', $this->id)
            ->where('erp_model_type', 'App\Models\ERP\Staff')
            ->first();
    }

    public function staff_work_profile()
    {
        return $this->hasOne(StaffWorkProfile::class);
    }

    public function staff_assigned_roles()
    {
        return $this->hasMany(StaffAssignedRole::class);
    }

    public function role_users()
    {
        return $this->hasMany(RoleUser::class);
    }
}
