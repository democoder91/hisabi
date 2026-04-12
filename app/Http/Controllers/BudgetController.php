<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Budget/Index');
    }
}