<?php

declare(strict_types=1);

namespace App\Services\Forge\Data;

use App\Services\Forge\Api\Support\JsonApiData;

class ForgeDeploymentData
{
    public function __construct(
        public int|string $id,
        public ?string $status,
    ) {
        //
    }

    public static function fromResource(array $resource): self
    {
        $attributes = JsonApiData::attributes($resource);

        return new self(
            id: JsonApiData::id($resource),
            status: $attributes['status'] ?? null,
        );
    }
}
