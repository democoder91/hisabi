<?php

namespace App\Http\Queries\Transaction\GetTransactionsQuery;

class GetTransactionsQuery
{
    public int $perPage;

    public function __construct(int $perPage)
    {
        $this->perPage = $perPage;
    }
}

