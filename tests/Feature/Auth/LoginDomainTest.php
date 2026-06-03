<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_blocked_for_non_impdali_email(): void
    {
        $this->post('/login', [
            'email' => 'externo@gmail.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_works_for_impdali_email(): void
    {
        User::factory()->create(['email' => 'persona@impdali.cl']);

        $this->post('/login', [
            'email' => 'persona@impdali.cl',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }
}
