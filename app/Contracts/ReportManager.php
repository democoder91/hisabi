<?php

namespace App\Contracts;

interface ReportManager 
{
    public function generate($startDate = null, $endDate = null, $user = null);
}