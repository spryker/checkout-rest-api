<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\CheckoutRestApi\Api\Storefront\Mapper;

use Generated\Api\Storefront\CheckoutStorefrontResource;
use Generated\Shared\Transfer\RestCheckoutResponseAttributesTransfer;
use Generated\Shared\Transfer\RestCheckoutResponseTransfer;
use Spryker\Glue\CheckoutRestApi\CheckoutRestApiConfig;

class CheckoutResourceMapper
{
    /**
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutResponseMapperPluginInterface> $checkoutResponseMapperPlugins
     */
    public function buildResponseAttributes(
        RestCheckoutResponseTransfer $restCheckoutResponseTransfer,
        array $checkoutResponseMapperPlugins,
    ): RestCheckoutResponseAttributesTransfer {
        $responseAttributesTransfer = (new RestCheckoutResponseAttributesTransfer())
            ->fromArray($restCheckoutResponseTransfer->toArray(), true);

        foreach ($checkoutResponseMapperPlugins as $plugin) {
            $responseAttributesTransfer = $plugin->mapRestCheckoutResponseTransferToRestCheckoutResponseAttributesTransfer(
                $restCheckoutResponseTransfer,
                $responseAttributesTransfer,
            );
        }

        return $responseAttributesTransfer;
    }

    public function mapResponseAttributesToResource(
        RestCheckoutResponseAttributesTransfer $responseAttributesTransfer,
        CheckoutStorefrontResource $resource,
    ): CheckoutStorefrontResource {
        $arrayData = $responseAttributesTransfer->toArray(true, true);

        foreach ($arrayData as $key => $value) {
            if (property_exists($resource, $key)) {
                $resource->{$key} = $value;
            }
        }

        // Singleton-resource pattern: identifier value equals the resource shortName so
        // the JSON:API IRI converter can produce a synthetic IRI. `IdNormalizer` strips
        // this synthetic id from the response, leaving `data.id = null` for BC.
        $resource->checkoutId = CheckoutRestApiConfig::RESOURCE_CHECKOUT;

        // Feeds the `orders` relationship via array URI-variable mapping. The framework
        // fans out one Orders include per element and emits `relationships.orders.data[]`.
        $resource->orderReferences = $resource->orderReference !== null ? [$resource->orderReference] : [];

        return $resource;
    }
}
