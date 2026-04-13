<?php

return [
    'currency' => env('BILLING_CURRENCY', 'EGP'),

    'credit_packages' => [
        'starter-100' => [
            'name' => '100 Credits',
            'credits' => 100,
            'amount_cents' => 20000,
        ],
        'growth-250' => [
            'name' => '250 Credits',
            'credits' => 250,
            'amount_cents' => 22000,
        ],
        'scale-500' => [
            'name' => '500 Credits',
            'credits' => 500,
            'amount_cents' => 4000,
        ],
    ],

    'subscription_plans' => [
        'starter-monthly' => [
            'name' => 'Starter Monthly',
            'amount_cents' => 1500,
            'renews_in_days' => 30,
        ],
        'pro-monthly' => [
            'name' => 'Pro Monthly',
            'amount_cents' => 2900,
            'renews_in_days' => 30,
        ],
    ],
];
