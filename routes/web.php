<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\CompanyLogoController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\RegisterCompany;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\Auth\AcceptInvitation;

use App\Livewire\App\Dashboard;
use App\Livewire\App\Subcontractors\Index as SubcontractorsIndex;
use App\Livewire\App\Subcontractors\Form as SubcontractorsForm;
use App\Livewire\App\Subcontractors\Show as SubcontractorsShow;
use App\Livewire\App\Documents\PendingIndex;
use App\Livewire\App\Documents\Review as DocumentReview;
use App\Livewire\App\Deadlines\Index as DeadlinesIndex;
use App\Livewire\App\Templates\Index as TemplatesIndex;
use App\Livewire\App\DocumentTypes\Index as DocumentTypesIndex;
use App\Livewire\App\Users\Index as UsersIndex;
use App\Livewire\App\Settings\Index as SettingsIndex;
use App\Livewire\App\Settings\Subscription as SettingsSubscription;
use App\Livewire\App\Notifications\Index as NotificationsIndex;
use App\Livewire\App\Audit\Index as AuditIndex;

use App\Livewire\Portal\Upload as PortalUpload;

use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Companies\Index as AdminCompaniesIndex;

// Redirection accueil vers tableau de bord
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Demo UI Showcase (Uniquement en local)
if (app()->environment('local')) {
    Route::get('/dev/ui', function () {
        return view('dev.ui');
    })->name('dev.ui');
}

/*
|--------------------------------------------------------------------------
| Authentification
|--------------------------------------------------------------------------
*/
Route::get('/login', Login::class)->name('login');
Route::get('/register', RegisterCompany::class)->name('register');
Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
Route::get('/verify-email', VerifyEmail::class)->name('verification.notice');
Route::get('/invitation/{token}', AcceptInvitation::class)->name('invitation.accept');

Route::match(['get', 'post'], '/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = $request->user();

    if (! $user || ! hash_equals((string) $id, (string) $user->getKey())) {
        abort(403);
    }

    if (! hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
        abort(403);
    }

    if (! app()->environment('local') && ! $request->hasValidSignature()) {
        abort(403, 'Invalid signature.');
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new \Illuminate\Auth\Events\Verified($user));
    }

    return redirect()->route('dashboard');
})->middleware('auth')->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Espace entreprise
|--------------------------------------------------------------------------
*/
Route::prefix('app')->middleware(['auth', 'verified', 'company', 'company.active'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    
    // Sous-traitants
    Route::get('/subcontractors', SubcontractorsIndex::class)->name('subcontractors.index');
    Route::get('/subcontractors/create', SubcontractorsForm::class)->name('subcontractors.create');
    Route::get('/subcontractors/{id}/edit', SubcontractorsForm::class)->whereNumber('id')->name('subcontractors.edit');
    Route::get('/subcontractors/{id}', SubcontractorsShow::class)->whereNumber('id')->name('subcontractors.show');

    // Documents
    Route::get('/documents/pending', PendingIndex::class)->name('documents.pending');
    Route::get('/documents/{id}/review', DocumentReview::class)->whereNumber('id')->name('documents.review');
    Route::get('/documents/{id}/download', \App\Http\Controllers\DocumentDownloadController::class)->whereNumber('id')->name('documents.download');

    // Échéances & Modèles
    Route::get('/deadlines', DeadlinesIndex::class)->name('deadlines.index');
    Route::get('/templates', TemplatesIndex::class)->name('templates.index');
    Route::get('/document-types', DocumentTypesIndex::class)->name('document-types.index');

    // Administration interne & Paramètres
    Route::get('/users', UsersIndex::class)->name('users.index');
    Route::get('/profile', \App\Livewire\App\Profile::class)->name('profile');
    Route::get('/settings/subscription', SettingsSubscription::class)->name('subscription');
    Route::get('/settings/{section?}', SettingsIndex::class)
        ->whereIn('section', SettingsIndex::SECTIONS)
        ->name('settings.index');
    Route::get('/notifications', NotificationsIndex::class)->name('notifications.index');
    Route::get('/audit', AuditIndex::class)->name('audit.index');
});

/*
|--------------------------------------------------------------------------
| Portail externe sous-traitant
|--------------------------------------------------------------------------
*/
Route::get('/depot/{token}', PortalUpload::class)
    ->middleware('throttle:portal')
    ->name('portal.upload');
Route::get('/branding/logo/{uuid}', CompanyLogoController::class)
    ->whereUuid('uuid')
    ->name('company.logo');

/*
|--------------------------------------------------------------------------
| Administration plateforme SuperAdmin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'platform.admin'])->group(function () {
    Route::get('/dashboard', AdminDashboard::class)->name('admin.dashboard');
    Route::get('/companies', AdminCompaniesIndex::class)->name('admin.companies');
});
