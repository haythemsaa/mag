<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfractionAttachment extends Model
{
    protected $fillable = [
        'infraction_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'type',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function infraction(): BelongsTo
    {
        return $this->belongsTo(Infraction::class);
    }
}
