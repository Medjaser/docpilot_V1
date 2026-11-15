<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Numéro SIRET : 14 chiffres et clé de Luhn valide (exception La Poste). */
class Siret implements ValidationRule
{
    private const LA_POSTE_SIREN = '356000000';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('Le SIRET doit contenir 14 chiffres et être un numéro valide.');
        }
    }

    public static function isValid(string $siret): bool
    {
        if (! preg_match('/^\d{14}$/', $siret)) {
            return false;
        }

        // Les établissements de La Poste ne respectent pas Luhn : somme des chiffres multiple de 5
        if (str_starts_with($siret, self::LA_POSTE_SIREN)) {
            return array_sum(str_split($siret)) % 5 === 0;
        }

        $sum = 0;
        foreach (str_split(strrev($siret)) as $index => $digit) {
            $value = (int) $digit * ($index % 2 === 1 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return $sum % 10 === 0;
    }
}
