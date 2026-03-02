<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\CheckoutRestApi\Processor\Error;

use Generated\Shared\Transfer\RestCheckoutErrorTransfer;
use Generated\Shared\Transfer\RestErrorMessageTransfer;

interface RestCheckoutErrorMapperInterface
{
    public function mapRestCheckoutErrorTransferToRestErrorTransfer(
        RestCheckoutErrorTransfer $restCheckoutErrorTransfer,
        RestErrorMessageTransfer $restErrorMessageTransfer
    ): RestErrorMessageTransfer;

    public function mapLocalizedRestCheckoutErrorTransferToRestErrorTransfer(
        RestCheckoutErrorTransfer $restCheckoutErrorTransfer,
        RestErrorMessageTransfer $restErrorMessageTransfer,
        string $localeCode
    ): RestErrorMessageTransfer;
}
