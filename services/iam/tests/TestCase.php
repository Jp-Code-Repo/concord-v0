<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits(): array
    {
        if (! RefreshDatabaseState::$migrated) {
            Schema::connection('concord_platform')
                ->dropIfExists('legacy_apps');
        }

        return parent::setUpTraits();
    }
}