<?php

use App\Enums\CompanyStatus;

it('autorise les transitions prévues', function (CompanyStatus $from, CompanyStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    'essai → active' => [CompanyStatus::Trial, CompanyStatus::Active],
    'essai → suspendue' => [CompanyStatus::Trial, CompanyStatus::Suspended],
    'essai → résiliée' => [CompanyStatus::Trial, CompanyStatus::Terminated],
    'active → suspendue' => [CompanyStatus::Active, CompanyStatus::Suspended],
    'active → résiliée' => [CompanyStatus::Active, CompanyStatus::Terminated],
    'suspendue → active' => [CompanyStatus::Suspended, CompanyStatus::Active],
    'suspendue → résiliée' => [CompanyStatus::Suspended, CompanyStatus::Terminated],
    'résiliée → active' => [CompanyStatus::Terminated, CompanyStatus::Active],
    'résiliée → archivée' => [CompanyStatus::Terminated, CompanyStatus::Archived],
]);

it('refuse les autres transitions', function (CompanyStatus $from, CompanyStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'active → essai' => [CompanyStatus::Active, CompanyStatus::Trial],
    'active → archivée' => [CompanyStatus::Active, CompanyStatus::Archived],
    'suspendue → archivée' => [CompanyStatus::Suspended, CompanyStatus::Archived],
    'archivée → active' => [CompanyStatus::Archived, CompanyStatus::Active],
    'archivée → résiliée' => [CompanyStatus::Archived, CompanyStatus::Terminated],
    'active → active' => [CompanyStatus::Active, CompanyStatus::Active],
]);

it('ne permet l\'écriture qu\'en essai ou active', function () {
    expect(CompanyStatus::Trial->isWritable())->toBeTrue()
        ->and(CompanyStatus::Active->isWritable())->toBeTrue()
        ->and(CompanyStatus::Suspended->isWritable())->toBeFalse()
        ->and(CompanyStatus::Suspended->canAccessApp())->toBeTrue()
        ->and(CompanyStatus::Terminated->canAccessApp())->toBeFalse();
});
