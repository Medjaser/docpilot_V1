<?php

use App\Livewire\Auth\ForgotPassword;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->sophie = User::where('email', 'sophie@batinova.fr')->sole();
});

it('affiche la page mot de passe oublié', function () {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertSee('Mot de passe oublié');
});

it('génère un lien de réinitialisation direct en mode dev local', function () {
    Livewire::test(ForgotPassword::class)
        ->set('email', 'sophie@batinova.fr')
        ->call('resetDirectly')
        ->assertRedirect();

    $tokenRecord = DB::table('password_reset_tokens')->where('email', 'sophie@batinova.fr')->first();
    expect($tokenRecord)->not->toBeNull();
});

it('envoie une notification de réinitialisation valide', function () {
    Notification::fake();

    Livewire::test(ForgotPassword::class)
        ->set('email', 'sophie@batinova.fr')
        ->call('sendResetLink')
        ->assertSet('sent', true);

    Notification::assertSentTo($this->sophie, ResetPassword::class);
});
