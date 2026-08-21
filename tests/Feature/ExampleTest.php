<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path redirects guests to the SPA origin (or `/` when SPA_URL is unset).
     */
    public function test_the_application_redirects_guests_to_spa(): void
    {
        config(['services.spa.url' => 'https://spa.test']);

        $this->get('/')->assertRedirect('https://spa.test');
    }
}
