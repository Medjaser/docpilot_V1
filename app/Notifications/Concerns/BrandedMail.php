<?php

namespace App\Notifications\Concerns;

use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;

/** E-mail à l'image de l'entreprise : son logo (URL publique par UUID) dans l'en-tête. */
trait BrandedMail
{
    protected function brandedMail(Company $company): MailMessage
    {
        return (new MailMessage)->markdown('mail.company-notification', [
            'companyName' => $company->trade_name ?: $company->name,
            'companyLogoUrl' => $company->logoUrl(),
        ]);
    }
}
