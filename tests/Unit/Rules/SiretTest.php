<?php

use App\Rules\Siret;

it('valide un SIRET dont la clé de Luhn est correcte', function (string $siret) {
    expect(Siret::isValid($siret))->toBeTrue();
})->with(['12345678900015', '98765432100023']);

it('accepte les établissements de La Poste (somme des chiffres multiple de 5)', function () {
    expect(Siret::isValid('35600000000010'))->toBeTrue()
        ->and(Siret::isValid('35600000000011'))->toBeFalse();
});

it('refuse un SIRET mal formé ou dont la clé est fausse', function (string $siret) {
    expect(Siret::isValid($siret))->toBeFalse();
})->with(['12345678900012', '1234567890001', '123456789000150', 'ABCDEFGHIJKLMN', '']);
