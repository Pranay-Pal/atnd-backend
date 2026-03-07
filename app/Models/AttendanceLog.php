<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'recorded_at',
        'device_id',
        'similarity',
        'synced',
        'metadata',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'similarity'  => 'float',
        'synced'      => 'boolean',
        'metadata'    => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
