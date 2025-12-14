<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateDocument extends Model
{
    protected $fillable = [
        'name',
        'excel_file',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}