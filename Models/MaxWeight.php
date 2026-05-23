<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaxWeight extends Model
{
    use HasFactory;

    protected $fillable = [
        'weight',
        'status'
    ];
}
