<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeder_does_nothing_in_production(): void
    {
        $this->app['env'] = 'production';

        (new DatabaseSeeder())->run();

        $this->assertSame(0, User::count());
    }
}
