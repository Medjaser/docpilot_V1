<?php

namespace App\Domain\Company;

use App\Enums\CompanyStatus;
use DomainException;

class InvalidCompanyTransition extends DomainException
{
    public static function between(CompanyStatus $from, CompanyStatus $to): self
    {
        return new self("Transition impossible : « {$from->label()} » vers « {$to->label()} ».");
    }
}
