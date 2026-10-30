<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits(): array
    {
        if (app()->environment('testing')) {
            config([
                'database.connections.concord_platform.password' =>
                    config('database.connections.mysql.password'),
            ]);
        }

        if (! RefreshDatabaseState::$migrated) {
            Schema::connection('concord_platform')
                ->dropIfExists('legacy_apps');
        }

        return parent::setUpTraits();
    }
}