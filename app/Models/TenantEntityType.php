<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantEntityType extends Model
{
    protected $fillable = ['tenant_id', 'name', 'is_required'];

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Scopes\TenantScope());
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function entities()
    {
        return $this->hasMany(TenantEntity::class, 'tenant_entity_type_id');
    }
}
