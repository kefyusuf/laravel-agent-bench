<?php

namespace App\Policies;

use App\Models\AuditReport;
use App\Models\User;

class AuditReportPolicy
{
    public function view(User $user, AuditReport $report): bool
    {
        return $user->is_admin;
    }

    public function approve(User $user, AuditReport $report): bool
    {
        return $user->is_admin;
    }
}
