<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TuitionPercentage extends Model
{
    use HasFactory;
    protected $fillable = [
        'vu_session_id',
        'percentage',
        'category',
    ];

    public function vu_session()
    {
        return $this->belongsTo(VuSession::class);
    }
}
