<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TuitionFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id',
        'department_id',
        'description',
        'trans_code',
        'adm_year',
        'vu_session_id',
        'processed_session',
        'admissions_type_id',
        'vuna_accomodation_history_id',
        'vuna_tuition_fee_id',
        'tui_fee',
        'acc_fee',
        'total',
        'balance',
        'payment_plan',
        'rrr_tuition_fee_id',
        'debt_total',
        'debt_percent',
        'debt_balance',
        'debt_payment_plan',
        'amount',
        'payment_reference',
        'status_code',
        'transaction_id',
        'rrr',
        'processor_id',
        'paid_amount',
        'payment_status',
        'is_room_taken',
        'transaction_time',
        'verify'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applicant_bio_data()
    {
        return $this->belongsTo(ApplicantBioData::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function student_academic()
    {
        return $this->belongsTo(StudentAcademic::class, 'student_id', 'student_id');
    }

    public function vuna_accomodation_category()
    {
        return $this->belongsTo(VunaAccomodationCategory::class);
    }

    public function vuna_tuition_fee()
    {
        return $this->belongsTo(VunaTuitionFee::class);
    }

    public function outstanding_fee()
    {
        return $this->hasOne(OutstandingFee::class);
    }

    public function vuna_accomodation_history()
    {
        return $this->hasOne(VunaAccomodationHistory::class);
    }

    public function admissions_type()
    {
        return $this->belongsTo(AdmissionsType::class);
    }

    public function vu_session()
    {
        return $this->belongsTo(VuSession::class);
    }

}

