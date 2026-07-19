<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\User\Role;

class RootPromoteUserProcessor extends AbstractRootUserRoleMutationProcessor
{
    protected function sourceRole(): Role
    {
        return Role::Player;
    }

    protected function targetRole(): Role
    {
        return Role::Admin;
    }

    protected function logAction(): string
    {
        return 'root_promote_user';
    }

    protected function successToast(): string
    {
        return 'Promoted to Admin';
    }
}
