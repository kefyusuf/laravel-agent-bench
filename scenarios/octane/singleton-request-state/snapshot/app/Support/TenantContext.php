<?php

namespace App\Support;

use App\Models\Tenant;

class TenantContext
{
    public function __construct(public readonly Tenant $tenant)
    {
    }
}
