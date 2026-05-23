<?php

namespace App\Models\ERP;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $connection = 'erp_mysql';

    protected $table = 'users';

    const ROLE_ADMIN = 1;
    const ROLE_STUDENT = 2;
    const ROLE_APPLICANT = 7;

    protected $fillable = [
        'fname', 'mname', 'lname', 'name', 'email',
        'user_type', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function ucccUser()
    {
        return UcccUser::where('erp_user_id', $this->id)
            ->where('erp_model_type', 'App\Models\ERP\User')
            ->first();
    }

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }
}
