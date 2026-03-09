<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantEntity extends Model
{
    protected $fillable = ['tenant_entity_type_id', 'name'];

    public function type()
    {
        return $this->belongsTo(TenantEntityType::class, 'tenant_entity_type_id');
    }

}
