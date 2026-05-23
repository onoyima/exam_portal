<?php
//is_free 0=Not Available 1=Available 2=Taken and 3=Blocked
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VunaAccomodationHistory extends Model
{
    use HasFactory;

    protected $fillable = [

        'user_id',
        'student_id',
        'vu_session_id',
        'vuna_accomodation_id',
        'vuna_accomodation_category_id',
        'vuna_acc_cate_room_id',
        'vuna_acc_cate_flat_id',
        'vuna_acc_cate_bunk_id',
        'tuition_fee_id',
        'bunk',
        'bunk_position',
        'is_free',
        'status',

    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function vuna_accomodation()
    {
        return $this->belongsTo(VunaAccomodation::class);
    }

    public function vuna_accomodation_category()
    {
        return $this->belongsTo(VunaAccomodationCategory::class);
    }

    public function vuna_acc_cate_room()
    {
        return $this->belongsTo(VunaAccCateRoom::class);
    }

    public function vuna_acc_cate_flat()
    {
        return $this->belongsTo(VunaAccCateFlat::class);
    }

    public function vuna_acc_cate_bunk()
    {
        return $this->belongsTo(VunaAccCateBunk::class);
    }

    public function vu_session()
    {
        return $this->belongsTo(VuSession::class);
    }

    public function tuition_fee()
    {
        return $this->belongsTo(TuitionFee::class);
    }


}
