<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_redirects_to_notes(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/notes');
    }
}
