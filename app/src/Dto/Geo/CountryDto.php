<?php

declare(strict_types=1);

namespace App\Dto\Geo;

readonly class CountryDto
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }

    /**
     * @return array{code: string, name: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
