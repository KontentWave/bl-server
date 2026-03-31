<?php

namespace App\Contracts;

interface EscortPortalClient
{
    public function fetchAdHtml(string $phoneNumber): ?string;
}
