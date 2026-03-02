<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\CheckoutRestApi\Processor\Checkout;

use Generated\Shared\Transfer\RestCheckoutResponseAttributesTransfer;
use Generated\Shared\Transfer\RestCheckoutResponseTransfer;

class CheckoutResponseMapper implements CheckoutResponseMapperInterface
{
    /**
     * @var array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutResponseMapperPluginInterface>
     */
    protected $checkoutResponseMapperPlugins;

    /**
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutResponseMapperPluginInterface> $checkoutResponseMapperPlugins
     */
    public function __construct(array $checkoutResponseMapperPlugins)
    {
        $this->checkoutResponseMapperPlugins = $checkoutResponseMapperPlugins;
    }

    public function mapRestCheckoutResponseTransferToRestCheckoutResponseAttributesTransfer(
        RestCheckoutResponseTransfer $restCheckoutResponseTransfer,
        RestCheckoutResponseAttributesTransfer $restCheckoutResponseAttributesTransfer
    ): RestCheckoutResponseAttributesTransfer {
        $restCheckoutResponseAttributesTransfer->fromArray($restCheckoutResponseTransfer->toArray(), true);

        return $this->executeCheckoutResponseMapperPlugins(
            $restCheckoutResponseTransfer,
            $restCheckoutResponseAttributesTransfer,
        );
    }

    protected function executeCheckoutResponseMapperPlugins(
        RestCheckoutResponseTransfer $restCheckoutResponseTransfer,
        RestCheckoutResponseAttributesTransfer $restCheckoutResponseAttributesTransfer
    ): RestCheckoutResponseAttributesTransfer {
        foreach ($this->checkoutResponseMapperPlugins as $checkoutResponseMapperPlugin) {
            $restCheckoutResponseAttributesTransfer = $checkoutResponseMapperPlugin
                ->mapRestCheckoutResponseTransferToRestCheckoutResponseAttributesTransfer(
                    $restCheckoutResponseTransfer,
                    $restCheckoutResponseAttributesTransfer,
                );
        }

        return $restCheckoutResponseAttributesTransfer;
    }
}
