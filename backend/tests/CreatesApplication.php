<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Bulletproof test-DB isolation. Docker's env_file injects APP_ENV=local
        // and DB_DATABASE=dataforge as real OS env vars, which PHPUnit's <env>
        // (even force) does NOT reliably override — so without this, RefreshDatabase
        // runs migrate:fresh against the DEV database. This trait only runs under
        // the test suite, so unconditionally repoint pgsql at the test DB and purge
        // any connection already opened with the old name.
        config(['database.connections.pgsql.database' => 'dataforge_testing']);
        DB::purge('pgsql');

        return $app;
    }
}
