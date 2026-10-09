<?php

namespace Tests\Unit\Domain;

use App\Domain\Documents\DocumentStatusResolver;
use App\Enums\DocumentStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentStatusResolverTest extends TestCase
{
    private const TODAY = '2026-08-01';

    #[DataProvider('cases')]
    public function test_it_resolves_temporal_status(DocumentStatus $current, ?string $expiration, DocumentStatus $expected): void
    {
        $resolver = new DocumentStatusResolver(expiringSoonDays: 30);

        $result = $resolver->resolve(
            $current,
            $expiration ? new DateTimeImmutable($expiration) : null,
            new DateTimeImmutable(self::TODAY),
        );

        $this->assertSame($expected, $result);
    }

    public static function cases(): array
    {
        return [
            'plus de 30 jours → valide' => [DocumentStatus::Valid, '2026-12-31', DocumentStatus::Valid],
            'exactement 30 jours → bientôt expiré' => [DocumentStatus::Valid, '2026-08-31', DocumentStatus::ExpiringSoon],
            'expire aujourd\'hui → bientôt expiré' => [DocumentStatus::Valid, '2026-08-01', DocumentStatus::ExpiringSoon],
            'date dépassée → expiré' => [DocumentStatus::Valid, '2026-07-31', DocumentStatus::Expired],
            'renouvelé → redevient valide' => [DocumentStatus::Expired, '2027-08-01', DocumentStatus::Valid],
            'sans date d\'expiration → inchangé' => [DocumentStatus::Valid, null, DocumentStatus::Valid],
            'rejeté → inchangé' => [DocumentStatus::Rejected, '2026-07-01', DocumentStatus::Rejected],
            'en attente → inchangé' => [DocumentStatus::PendingValidation, '2026-07-01', DocumentStatus::PendingValidation],
        ];
    }
}
