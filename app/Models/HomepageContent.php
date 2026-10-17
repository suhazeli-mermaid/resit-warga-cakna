<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomepageContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_key',
        'title',
        'subtitle',
        'body',
        'banner_image',
        'cta_text',
        'cta_link',
        'is_published',
        'extra_data',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'is_published' => 'boolean',
    ];
}