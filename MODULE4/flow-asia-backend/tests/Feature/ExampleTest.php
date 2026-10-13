<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_root_opens_the_module_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/m4');
    }
}
