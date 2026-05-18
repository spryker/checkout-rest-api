<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\CheckoutRestApi\Api\Storefront\Exception;

use Generated\Shared\Transfer\RestCheckoutErrorTransfer;
use Generated\Shared\Transfer\RestErrorMessageTransfer;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\Client\GlossaryStorage\GlossaryStorageClientInterface;
use Spryker\Glue\CheckoutRestApi\CheckoutRestApiConfig;
use Symfony\Component\HttpFoundation\Response;

class CheckoutExceptionFactory
{
    protected const string KEY_STATUS = 'status';

    protected const string KEY_CODE = 'code';

    protected const string KEY_DETAIL = 'detail';

    public function __construct(
        protected CheckoutRestApiConfig $checkoutRestApiConfig = new CheckoutRestApiConfig(),
        protected ?GlossaryStorageClientInterface $glossaryStorageClient = null,
    ) {
    }

    public function createInvalidPaymentException(string $methodName, string $providerName): GlueApiException
    {
        return new GlueApiException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            CheckoutRestApiConfig::RESPONSE_CODE_INVALID_PAYMENT,
            sprintf(
                CheckoutRestApiConfig::RESPONSE_DETAILS_INVALID_PAYMENT,
                $methodName,
                $providerName,
            ),
        );
    }

    public function createCustomerDataMissingException(): GlueApiException
    {
        return new GlueApiException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            CheckoutRestApiConfig::RESPONSE_CODE_CUSTOMER_DATA_MISSING,
            CheckoutRestApiConfig::RESPONSE_DETAILS_CUSTOMER_DATA_MISSING,
        );
    }

    public function createExceptionFromRestErrorMessage(RestErrorMessageTransfer $restErrorMessageTransfer): GlueApiException
    {
        return new GlueApiException(
            (int)($restErrorMessageTransfer->getStatus() ?? Response::HTTP_UNPROCESSABLE_ENTITY),
            (string)($restErrorMessageTransfer->getCode() ?? CheckoutRestApiConfig::RESPONSE_CODE_CHECKOUT_DATA_INVALID),
            (string)($restErrorMessageTransfer->getDetail() ?? CheckoutRestApiConfig::RESPONSE_DETAILS_CHECKOUT_DATA_INVALID),
        );
    }

    public function createExceptionFromRestCheckoutError(
        RestCheckoutErrorTransfer $restCheckoutErrorTransfer,
        string $localeName,
    ): GlueApiException {
        $errorIdentifier = (string)$restCheckoutErrorTransfer->getErrorIdentifier();
        $errorMapping = $this->checkoutRestApiConfig->getErrorIdentifierToRestErrorMapping();

        $defaultStatus = (int)($restCheckoutErrorTransfer->getStatus() ?? Response::HTTP_UNPROCESSABLE_ENTITY);
        $defaultCode = (string)($restCheckoutErrorTransfer->getCode() ?? CheckoutRestApiConfig::RESPONSE_CODE_CHECKOUT_DATA_INVALID);
        $defaultDetail = $this->translateErrorDetail($restCheckoutErrorTransfer, $localeName);

        if (!isset($errorMapping[$errorIdentifier])) {
            return new GlueApiException($defaultStatus, $defaultCode, $defaultDetail);
        }

        $mapping = $errorMapping[$errorIdentifier];

        return new GlueApiException(
            (int)($mapping[static::KEY_STATUS] ?? $defaultStatus),
            (string)($mapping[static::KEY_CODE] ?? $defaultCode),
            $defaultDetail !== '' ? $defaultDetail : (string)($mapping[static::KEY_DETAIL] ?? ''),
        );
    }

    protected function translateErrorDetail(RestCheckoutErrorTransfer $restCheckoutErrorTransfer, string $localeName): string
    {
        $detail = (string)$restCheckoutErrorTransfer->getDetail();

        if ($detail === '' || $this->glossaryStorageClient === null) {
            return $detail;
        }

        $translated = $this->glossaryStorageClient->translate(
            $detail,
            $localeName,
            $restCheckoutErrorTransfer->getParameters(),
        );

        return $translated !== '' ? $translated : $detail;
    }
}
