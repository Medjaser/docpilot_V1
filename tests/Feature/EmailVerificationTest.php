<?php

use App\Livewire\Auth\RegisterCompany;
use App\Livewire\Auth\VerifyEmail as VerifyEmailComponent;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->sophie = User::where('email', 'sophie@batinova.fr')->sole();
    $this->batinova = Company::where('name', 'BatiNova Rénovation')->sole();
});

it('envoie la notification VerifyEmail au bon utilisateur lors de l\'inscription', function () {
    Notification::fake();

    Livewire::test(RegisterCompany::class)
        ->set('company_name', 'Nouvelle Construction')
        ->set('siret', '11223344556677')
        ->set('industry', 'BTP')
        ->set('city', 'Lyon')
        ->set('phone', '04 78 00 00 00')
        ->call('nextStep')
        ->set('first_name', 'Alice')
        ->set('last_name', 'Martin')
        ->set('email', 'alice.martin@construction-test.fr')
        ->set('password', 'Secret123!')
        ->set('password_confirmation', 'Secret123!')
        ->set('terms', true)
        ->call('register')
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'alice.martin@construction-test.fr')->first();
    expect($user)->not->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('permet le renvoi de l\'e-mail de vérification avec rate limiting', function () {
    Notification::fake();

    $this->sophie->forceFill(['email_verified_at' => null])->save();

    Livewire::actingAs($this->sophie)
        ->test(VerifyEmailComponent::class)
        ->call('resend')
        ->assertSet('resent', true)
        ->assertSet('feedbackType', 'success');

    Notification::assertSentTo($this->sophie, VerifyEmail::class);

    // Deuxième appel immédiat : bloqué par le rate limiter
    Livewire::actingAs($this->sophie)
        ->test(VerifyEmailComponent::class)
        ->call('resend')
        ->assertSet('feedbackType', 'warning');
});

it('redirige vers /verify-email lors de l\'accès à /app sans e-mail vérifié', function () {
    $this->sophie->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($this->sophie)
        ->get('/app/dashboard')
        ->assertRedirect(route('verification.notice'));
});

it('autorise l\'accès à /app quand l\'e-mail est vérifié', function () {
    expect($this->sophie->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($this->sophie)
        ->get('/app/dashboard')
        ->assertOk();
});

it('n\'affiche pas le bouton dev en environnement production', function () {
    $this->sophie->forceFill(['email_verified_at' => null])->save();

    app()->detectEnvironment(fn () => 'production');

    Livewire::actingAs($this->sophie)
        ->test(VerifyEmailComponent::class)
        ->assertDontSee('Mode Dev Local');
});
