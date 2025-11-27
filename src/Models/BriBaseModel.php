<?php

namespace ESolution\BriPayments\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BriBaseModel extends Model
{
    /**
     * Override connection agar selalu pakai DB utama sesuai ENV.
     */
    public function getConnectionName()
    {
        return env('DB_CONNECTION', 'mysql');
    }
}
