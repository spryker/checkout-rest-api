<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\CheckoutRestApi\Business\Checkout;

use Generated\Shared\Transfer\CheckoutDataResponseTransfer;
use Generated\Shared\Transfer\QuoteTransfer;

interface CheckoutDataReaderInterface
{
    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return \Generated\Shared\Transfer\CheckoutDataResponseTransfer
     */
    public function getCheckoutData(QuoteTransfer $quoteTransfer): CheckoutDataResponseTransfer;
}
