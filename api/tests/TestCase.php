<?php

namespace Tests;

use App\Tenancy\TenantContext;
use Database\Seeders\BaseDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** Plans, roles and permissions: seeded once per run, before the test transactions. */
    protected bool $seed = true;

    protected string $seeder = BaseDataSeeder::class;

    /**
     * Runs before traits like RefreshDatabase (which drops every table):
     * never let the suite touch a non-test database.
     */
    protected function setUpTraits()
    {
        $database = config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with((string) $database, '_test')) {
            throw new RuntimeException("Tests must run against a *_test database, got [{$database}].");
        }

        return parent::setUpTraits();
    }

    /**
     * Simulate a brand-new HTTP request: in production every request starts
     * with no authenticated user and no clinic in context.
     */
    protected function freshRequest(): static
    {
        $this->app['auth']->forgetGuards();
        $this->app->make(TenantContext::class)->forget();

        return $this;
    }
}
