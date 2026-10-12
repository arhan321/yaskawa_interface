<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RobotController extends Model
{
    protected $fillable = [
        'controller_code',
        'model',
        'ip_address',
        'firmware_version',
        'location',
        'connection_status',
        'last_seen_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    public function robots(): HasMany
    {
        return $this->hasMany(Robot::class);
    }
}
