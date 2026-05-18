<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\CheckoutRestApi\Api\Storefront\Relationship;

use Generated\Api\Storefront\ShipmentTypesStorefrontResource;
use Spryker\ApiPlatform\Relationship\AbstractRelationshipResolver;
use Spryker\Service\Serializer\SerializerServiceInterface;

class CheckoutDataShipmentTypesRelationshipResolver extends AbstractRelationshipResolver
{
    protected const string RELATIONSHIP_DATA_PROPERTY = 'shipmentTypesRelationshipData';

    public function __construct(
        protected SerializerServiceInterface $serializer,
    ) {
    }

    /**
     * @return array<\Generated\Api\Storefront\ShipmentTypesStorefrontResource>
     */
    protected function resolveRelationship(): array
    {
        $resources = [];

        foreach ($this->getParentResources() as $parent) {
            if (!property_exists($parent, static::RELATIONSHIP_DATA_PROPERTY)) {
                continue;
            }

            foreach ($parent->{static::RELATIONSHIP_DATA_PROPERTY} ?? [] as $row) {
                $resources[] = $this->serializer->denormalize($row, ShipmentTypesStorefrontResource::class);
            }
        }

        return $resources;
    }
}
