<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel's auth manager keeps the last resolved user on the shared app
     * instance. Forget it before attaching a new bearer token so feature tests
     * can switch manager/crew (or store A/store B) on consecutive requests.
     */
    public function withToken(string $token, string $type = 'Bearer')
    {
        $this->app['auth']->forgetGuards();

        return parent::withToken($token, $type);
    }
}
