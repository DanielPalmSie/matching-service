<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class VectorType extends Type
{
    public const NAME = 'vector';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VECTOR';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $this->formatVector($value);
        }

        if (is_string($value)) {
            return $value;
        }

        throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['array', 'string', 'null']);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return array_map('floatval', $value);
        }

        if (!is_string($value)) {
            throw ConversionException::conversionFailed($value, self::NAME);
        }

        $trimmed = trim($value);
        $trimmed = trim($trimmed, '[]');
        if ($trimmed === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $trimmed));

        return array_map('floatval', $parts);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    /**
     * @param array<int, float> $embedding
     */
    private function formatVector(array $embedding): string
    {
        return '[' . implode(',', array_map(static fn ($value) => sprintf('%.12f', $value), $embedding)) . ']';
    }
}
