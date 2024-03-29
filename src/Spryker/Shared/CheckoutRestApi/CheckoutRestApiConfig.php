<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\CheckoutRestApi;

use Spryker\Shared\Kernel\AbstractBundleConfig;

class CheckoutRestApiConfig extends AbstractBundleConfig
{
    /**
     * @var string
     */
    public const ERROR_IDENTIFIER_CHECKOUT_DATA_INVALID = 'ERROR_IDENTIFIER_CHECKOUT_DATA_INVALID';

    /**
     * @var string
     */
    public const ERROR_IDENTIFIER_ORDER_NOT_PLACED = 'ERROR_IDENTIFIER_ORDER_NOT_PLACED';

    /**
     * @var string
     */
    public const ERROR_IDENTIFIER_CART_NOT_FOUND = 'ERROR_IDENTIFIER_CART_NOT_FOUND';

    /**
     * @var string
     */
    public const ERROR_IDENTIFIER_CART_IS_EMPTY = 'ERROR_IDENTIFIER_CART_IS_EMPTY';

    /**
     * @var string
     */
    public const ERROR_IDENTIFIER_UNABLE_TO_DELETE_CART = 'ERROR_IDENTIFIER_UNABLE_TO_DELETE_CART';
}
