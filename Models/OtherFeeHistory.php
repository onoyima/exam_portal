<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherFeeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'student_id',
        'other_fee_id',
        'description',
        'amount',
        'qty',
        'initiated_date'
    ];

    public function rrr_other_fee()
    {
        return $this->hasOne(RrrOtherFee::class);
    }

    public function other_fee_tran()
    {
        return $this->hasOne(OtherFeeTran::class);
    }
}
