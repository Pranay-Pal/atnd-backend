<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Holds the current request's tenant in memory.
 * Set by ResolveTenant middleware; read by TenantScope.
 */
class TenantManager
{
    private ?Tenant $current = null;

    public function set(Tenant $tenant): void
    {
        $this->current = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->current;
    }

    public function id(): ?int
    {
        return $this->current?->id;
    }
}
