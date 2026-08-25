<?php

namespace NuxbeRemoteDirectory\Tests\Feature;

use NuxbeRemoteDirectory\Tests\TestCase;

class DirectorySearchThrottleTest extends TestCase
{
    public function test_throttles_the_search_route(): void
    {
        $this->withToken('test-token')->get('/api/remote-directory/search?q=wigwam')->assertOk();
        $this->withToken('test-token')->get('/api/remote-directory/search?q=wigwam')->assertOk();

        $this->withToken('test-token')
            ->get('/api/remote-directory/search?q=wigwam')
            ->assertStatus(429);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('remote-directory.throttle', '2,1');
    }
}
