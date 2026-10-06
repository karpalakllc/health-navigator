<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (day, normalised term): how often it was searched that day.
 *
 * There is deliberately no user, session or time finer than the day — on this
 * platform people search symptoms, and the aggregate is all the admin
 * dashboard ever needed.
 */
class SearchTermDaily extends Model
{
    protected $table = 'search_term_daily';

    public $timestamps = false;

    protected $fillable = [
        'date',
        'term',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'count' => 'integer',
        ];
    }
}
