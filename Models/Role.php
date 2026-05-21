<?php

namespace App\Models\ERP;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $connection = 'erp_mysql';

    protected $table = 'roles';

    protected $fillable = ['fullname', 'name', 'role_category'];

    public function role_users()
    {
        return $this->hasMany(RoleUser::class);
    }

    public function staff_assigned_role()
    {
        return $this->hasMany(StaffAssignedRole::class);
    }
}
