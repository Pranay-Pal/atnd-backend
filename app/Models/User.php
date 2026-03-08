<?php

namespace App\Models;

use App\Scopes\TenantScope;
use App\Traits\FilterableByEntities;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use FilterableByEntities;

    protected $fillable = [
        'tenant_id',
        'name',
        'member_uid',
    ];



    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function faceEmbedding(): HasOne
    {
        return $this->hasOne(FaceEmbedding::class)->latestOfMany();
    }

    public function faceEmbeddings(): HasMany
    {
        return $this->hasMany(FaceEmbedding::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(TenantEntity::class, 'entity_user');
    }
}
