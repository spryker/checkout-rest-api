<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\CheckoutRestApi\Processor\Customer;

use Generated\Shared\Transfer\RestCheckoutRequestAttributesTransfer;
use Generated\Shared\Transfer\RestCustomerTransfer;
use Spryker\Glue\GlueApplication\Rest\Request\Data\RestRequestInterface;

class CustomerMapper implements CustomerMapperInterface
{
    public function mapRestCustomerTransferFromRestCheckoutRequest(
        RestRequestInterface $restRequest,
        RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer
    ): RestCustomerTransfer {
        $restCustomerTransfer = new RestCustomerTransfer();

        if (!$restRequest->getRestUser()) {
            return $restCustomerTransfer;
        }

        if ($restCheckoutRequestAttributesTransfer->getCustomer()) {
            $restCustomerTransfer->fromArray(
                $restCheckoutRequestAttributesTransfer->getCustomer()->toArray(),
                true,
            );
        }

        $restCustomerTransfer->setCustomerReference($restRequest->getRestUser()->getNaturalIdentifier());

        if ($restRequest->getRestUser()->getSurrogateIdentifier()) {
            return $restCustomerTransfer->setIdCustomer((int)$restRequest->getRestUser()->getSurrogateIdentifier());
        }

        return $restCustomerTransfer->setIdCustomer(null);
    }
}
