<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\CheckoutRestApi\Business\Checkout\Quote;

use Generated\Shared\Transfer\CustomerTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Generated\Shared\Transfer\RestCheckoutRequestAttributesTransfer;
use Spryker\Zed\CheckoutRestApi\Dependency\Facade\CheckoutRestApiToCartsRestApiFacadeInterface;

class QuoteReader implements QuoteReaderInterface
{
    /**
     * @var \Spryker\Zed\CheckoutRestApi\Dependency\Facade\CheckoutRestApiToCartsRestApiFacadeInterface
     */
    protected $cartsRestApiFacade;

    public function __construct(CheckoutRestApiToCartsRestApiFacadeInterface $cartsRestApiFacade)
    {
        $this->cartsRestApiFacade = $cartsRestApiFacade;
    }

    public function findCustomerQuoteByUuid(RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer): ?QuoteTransfer
    {
        if (
            !$restCheckoutRequestAttributesTransfer->getCustomer()
            || !$restCheckoutRequestAttributesTransfer->getCustomer()->getCustomerReference()
        ) {
            return null;
        }

        $quoteTransfer = $this->createQuoteTransfer($restCheckoutRequestAttributesTransfer);

        $quoteResponseTransfer = $this->cartsRestApiFacade->findQuoteByUuid($quoteTransfer);

        if (!$quoteResponseTransfer->getIsSuccessful()) {
            return null;
        }

        return $quoteResponseTransfer->getQuoteTransfer();
    }

    protected function createQuoteTransfer(
        RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer
    ): QuoteTransfer {
        $customerTransfer = (new CustomerTransfer())->fromArray($restCheckoutRequestAttributesTransfer->getCustomer()->modifiedToArray(), true);

        return (new QuoteTransfer())
            ->setUuid($restCheckoutRequestAttributesTransfer->getIdCart())
            ->setCustomerReference($restCheckoutRequestAttributesTransfer->getCustomer()->getCustomerReference())
            ->setCustomer($customerTransfer);
    }
}
