<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\CheckoutRestApi\Api\Storefront\Relationship;

use Generated\Api\Storefront\ServicePointsStorefrontResource;
use Spryker\ApiPlatform\Relationship\AbstractRelationshipResolver;
use Spryker\Service\Serializer\SerializerServiceInterface;

class CheckoutDataServicePointsRelationshipResolver extends AbstractRelationshipResolver
{
    protected const string RELATIONSHIP_DATA_PROPERTY = 'servicePointsRelationshipData';

    public function __construct(
        protected SerializerServiceInterface $serializer,
    ) {
    }

    /**
     * @return array<\Generated\Api\Storefront\ServicePointsStorefrontResource>
     */
    protected function resolveRelationship(): array
    {
        $resources = [];

        foreach ($this->getParentResources() as $parent) {
            if (!property_exists($parent, static::RELATIONSHIP_DATA_PROPERTY)) {
                continue;
            }

            foreach ($parent->{static::RELATIONSHIP_DATA_PROPERTY} ?? [] as $row) {
                $resources[] = $this->serializer->denormalize($row, ServicePointsStorefrontResource::class);
            }
        }

        return $resources;
    }
}
