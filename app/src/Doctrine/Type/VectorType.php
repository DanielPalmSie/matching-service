<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class VectorType extends Type
{
    public const NAME = 'vector';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if (isset($column['columnDefinition']) && is_string($column['columnDefinition']) && $column['columnDefinition'] !== '') {
            return $column['columnDefinition'];
        }

        return 'vector';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            /** @var list<float> $embedding */
            $embedding = array_map('floatval', array_values($value));

            return $this->formatVector($embedding);
        }

        if (is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(sprintf(
            'Could not convert PHP value of type %s to DBAL type "%s". Expected: array|string|null.',
            get_debug_type($value),
            self::NAME
        ));
    }

    /**
     * @return list<float>|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            /** @var list<float> $out */
            $out = array_map('floatval', array_values($value));
            return $out;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Could not convert database value of type %s to PHP for DBAL type "%s". Expected: string|array|null.',
                get_debug_type($value),
                self::NAME
            ));
        }

        $trimmed = trim($value);
        $trimmed = trim($trimmed, '[]');

        if ($trimmed === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $trimmed));

        /** @var list<float> $out */
        $out = array_map('floatval', $parts);

        return $out;
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
     * @param list<float> $embedding
     */
    private function formatVector(array $embedding): string
    {
        return '[' . implode(',', array_map(
            static fn (float $value) => sprintf('%.12f', $value),
            $embedding
        )) . ']';
    }
}
