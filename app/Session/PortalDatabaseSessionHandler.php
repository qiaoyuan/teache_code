<?php

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler;

class PortalDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function getDefaultPayload($data)
    {
        return [...parent::getDefaultPayload($data), 'portal' => config('session.portal', 'parent')];
    }

    protected function getQuery()
    {
        return parent::getQuery()->where('portal', config('session.portal', 'parent'));
    }
}
