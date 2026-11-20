<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Schema;

class SchemaReady
{
    public static function hasVisitorsTable(): bool
    {
        try {
            return Schema::hasTable('visitors');
        } catch (\Throwable) {
            return false;
        }
    }
}
