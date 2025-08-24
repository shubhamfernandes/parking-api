<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Europe/London']);
        Carbon::setTestNow(Carbon::create(2025, 8, 21, 9, 0, 0, 'Europe/London'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // unfreeze
        parent::tearDown();
    }
}
