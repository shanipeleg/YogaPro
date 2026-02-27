<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BodyStatePreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'zones',
    ];

    protected $casts = [
        'zones' => 'array',
    ];
}
