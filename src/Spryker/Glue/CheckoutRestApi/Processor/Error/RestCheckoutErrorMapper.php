<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\CheckoutRestApi\Processor\Error;

use Generated\Shared\Transfer\RestCheckoutErrorTransfer;
use Generated\Shared\Transfer\RestErrorMessageTransfer;
use Spryker\Glue\CheckoutRestApi\CheckoutRestApiConfig;
use Spryker\Glue\CheckoutRestApi\Dependency\Client\CheckoutRestApiToGlossaryStorageClientInterface;

class RestCheckoutErrorMapper implements RestCheckoutErrorMapperInterface
{
    /**
     * @var \Spryker\Glue\CheckoutRestApi\CheckoutRestApiConfig
     */
    protected $config;

    /**
     * @var \Spryker\Glue\CheckoutRestApi\Dependency\Client\CheckoutRestApiToGlossaryStorageClientInterface
     */
    protected $glossaryStorageClient;

    public function __construct(
        CheckoutRestApiConfig $config,
        CheckoutRestApiToGlossaryStorageClientInterface $glossaryStorageClient
    ) {
        $this->config = $config;
        $this->glossaryStorageClient = $glossaryStorageClient;
    }

    public function mapRestCheckoutErrorTransferToRestErrorTransfer(
        RestCheckoutErrorTransfer $restCheckoutErrorTransfer,
        RestErrorMessageTransfer $restErrorMessageTransfer
    ): RestErrorMessageTransfer {
        return $this->mergeErrorDataWithErrorConfiguration(
            $restCheckoutErrorTransfer,
            $restErrorMessageTransfer,
            $restCheckoutErrorTransfer->toArray(),
        );
    }

    public function mapLocalizedRestCheckoutErrorTransferToRestErrorTransfer(
        RestCheckoutErrorTransfer $restCheckoutErrorTransfer,
        RestErrorMessageTransfer $restErrorMessageTransfer,
        string $localeCode
    ): RestErrorMessageTransfer {
        return $this->mergeErrorDataWithErrorConfiguration(
            $restCheckoutErrorTransfer,
            $restErrorMessageTransfer,
            $this->translateCheckoutErrorMessage($restCheckoutErrorTransfer, $localeCode)->toArray(),
        );
    }

    protected function mergeErrorDataWithErrorConfiguration(
        RestCheckoutErrorTransfer $restCheckoutErrorTransfer,
        RestErrorMessageTransfer $restErrorMessageTransfer,
        array $errorData
    ): RestErrorMessageTransfer {
        $errorIdentifierMapping = $this->getErrorIdentifierMapping($restCheckoutErrorTransfer);

        if ($errorIdentifierMapping) {
            $errorData = array_merge($errorIdentifierMapping, array_filter($errorData));
        }

        return $restErrorMessageTransfer->fromArray($errorData, true);
    }

    protected function getErrorIdentifierMapping(RestCheckoutErrorTransfer $restCheckoutErrorTransfer): array
    {
        return $this->config->getErrorIdentifierToRestErrorMapping()[$restCheckoutErrorTransfer->getErrorIdentifier()] ?? [];
    }

    protected function translateCheckoutErrorMessage(
        RestCheckoutErrorTransfer $restCheckoutErrorTransfer,
        string $localeName
    ): RestCheckoutErrorTransfer {
        if (!$restCheckoutErrorTransfer->getDetail()) {
            return $restCheckoutErrorTransfer;
        }

        $restCheckoutErrorDetail = $this->glossaryStorageClient->translate(
            $restCheckoutErrorTransfer->getDetail(),
            $localeName,
            $restCheckoutErrorTransfer->getParameters(),
        );

        if (!$restCheckoutErrorDetail) {
            return $restCheckoutErrorTransfer;
        }

        return $restCheckoutErrorTransfer->setDetail($restCheckoutErrorDetail);
    }
}
