<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\User\Role;

class RootDemoteUserProcessor extends AbstractRootUserRoleMutationProcessor
{
    protected function sourceRole(): Role
    {
        return Role::Admin;
    }

    protected function targetRole(): Role
    {
        return Role::Player;
    }

    protected function logAction(): string
    {
        return 'root_demote_user';
    }

    protected function successToast(): string
    {
        return 'Demoted to Player';
    }
}
