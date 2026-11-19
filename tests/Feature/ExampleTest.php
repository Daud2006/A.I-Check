<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_guests_can_open_the_faq_and_registration_documents(): void
    {
        $termsUrl = route('legal.terms');
        $privacyUrl = route('legal.privacy');

        $this->get(route('auth'))
            ->assertOk()
            ->assertSee('href="'.$termsUrl.'" target="_blank"', false)
            ->assertSee('href="'.$privacyUrl.'" target="_blank"', false);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Panduan & FAQ')
            ->assertSee('Apa arti kategori hasil?');

        $this->get($termsUrl)->assertOk()->assertSee('Syarat Penggunaan');
        $this->get($privacyUrl)->assertOk()->assertSee('Kebijakan Privasi');
    }
}
