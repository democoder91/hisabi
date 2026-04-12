<?php

namespace App\Domains\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountShare extends Model
{
    use SoftDeletes;

    protected $table = 'account_user';

    protected $guarded = [];
}