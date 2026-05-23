<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RemCred extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'api_key',
        'service_type_id'
    ];

}
