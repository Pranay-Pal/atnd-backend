<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceEmbedding extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'embedding',
        'model_version',
        'device_id',
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

    /**
     * Decode the binary blob into a PHP array of 512 floats.
     *
     * @return float[]
     */
    public function decodeEmbedding(): array
    {
        $raw = $this->getRawOriginal('embedding');
        $count = strlen($raw) / 4; // float32 = 4 bytes
        return array_values(unpack("g{$count}", $raw));
    }
}
