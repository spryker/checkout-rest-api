<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\CheckoutRestApi\Api\Storefront\Processor;

use Generated\Api\Storefront\CheckoutStorefrontResource;
use Generated\Shared\Transfer\RestCheckoutRequestAttributesTransfer;
use Spryker\Client\CheckoutRestApi\CheckoutRestApiClientInterface;
use Spryker\Glue\CheckoutRestApi\Api\Storefront\Exception\CheckoutExceptionFactory;
use Spryker\Glue\CheckoutRestApi\Api\Storefront\Mapper\CheckoutResourceMapper;
use Spryker\Glue\CheckoutRestApi\CheckoutRestApiConfig;
use Spryker\Glue\GlueApplication\Compatibility\RequestBuilder\SyntheticRestRequestBuilderInterface;
use Spryker\Service\Container\Attributes\Plugins;

class CheckoutStorefrontProcessor extends AbstractCheckoutStorefrontProcessor
{
    /**
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutRequestAttributesValidatorPluginInterface> $checkoutRequestAttributesValidatorPlugins
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutRequestValidatorPluginInterface> $checkoutRequestValidatorPlugins
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutRequestExpanderPluginInterface> $checkoutRequestExpanderPlugins
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutResponseMapperPluginInterface> $checkoutResponseMapperPlugins
     */
    public function __construct(
        CheckoutRestApiClientInterface $checkoutRestApiClient,
        SyntheticRestRequestBuilderInterface $syntheticRestRequestBuilder,
        CheckoutExceptionFactory $exceptionFactory,
        protected CheckoutResourceMapper $checkoutResourceMapper,
        CheckoutRestApiConfig $checkoutRestApiConfig,
        #[Plugins(dependencyProviderMethod: 'getCheckoutRequestAttributesValidatorPlugins')]
        protected array $checkoutRequestAttributesValidatorPlugins = [],
        #[Plugins(dependencyProviderMethod: 'getCheckoutRequestValidatorPlugins')]
        protected array $checkoutRequestValidatorPlugins = [],
        #[Plugins(dependencyProviderMethod: 'getCheckoutRequestExpanderPlugins')]
        protected array $checkoutRequestExpanderPlugins = [],
        #[Plugins(dependencyProviderMethod: 'getCheckoutResponseMapperPlugins')]
        protected array $checkoutResponseMapperPlugins = [],
    ) {
        parent::__construct(
            $checkoutRestApiClient,
            $syntheticRestRequestBuilder,
            $exceptionFactory,
            $checkoutRestApiConfig,
        );
    }

    protected function processPost(mixed $data): CheckoutStorefrontResource
    {
        $restCheckoutRequestAttributesTransfer = $this->buildRequestAttributesTransfer($data);

        $this->assertGuestCustomerDataProvided($restCheckoutRequestAttributesTransfer);
        $this->assertPaymentProvidersAreMapped($restCheckoutRequestAttributesTransfer);
        $this->aggregateAndThrowFromValidatorPlugins(
            $this->checkoutRequestAttributesValidatorPlugins,
            $restCheckoutRequestAttributesTransfer,
        );
        $this->aggregateAndThrowFromValidatorPlugins(
            $this->checkoutRequestValidatorPlugins,
            $restCheckoutRequestAttributesTransfer,
        );

        $restCheckoutRequestAttributesTransfer = $this->expandCustomerData($restCheckoutRequestAttributesTransfer);
        $restCheckoutRequestAttributesTransfer = $this->expandPaymentSelection($restCheckoutRequestAttributesTransfer);
        $restCheckoutRequestAttributesTransfer = $this->runRequestExpanderPlugins(
            $this->checkoutRequestExpanderPlugins,
            $restCheckoutRequestAttributesTransfer,
        );

        $restCheckoutResponseTransfer = $this->checkoutRestApiClient->placeOrder($restCheckoutRequestAttributesTransfer);

        if (!$restCheckoutResponseTransfer->getIsSuccess()) {
            throw $this->exceptionFactory->createExceptionFromRestCheckoutError(
                $restCheckoutResponseTransfer->getErrors()->offsetGet(0),
                $this->getLocale()->getLocaleNameOrFail(),
            );
        }

        $responseAttributesTransfer = $this->checkoutResourceMapper->buildResponseAttributes(
            $restCheckoutResponseTransfer,
            $this->checkoutResponseMapperPlugins,
        );

        return $this->checkoutResourceMapper->mapResponseAttributesToResource(
            $responseAttributesTransfer,
            new CheckoutStorefrontResource(),
        );
    }

    protected function getResourceShortName(): string
    {
        return CheckoutRestApiConfig::RESOURCE_CHECKOUT;
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    protected function assertGuestCustomerDataProvided(RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer): void
    {
        if ($this->hasCustomer() && $this->getCustomer()->getIdCustomer() !== null) {
            return;
        }

        $restCustomerTransfer = $restCheckoutRequestAttributesTransfer->getCustomer();
        $requiredFields = $this->checkoutRestApiConfig->getRequiredCustomerRequestDataForGuestCheckout();

        if ($restCustomerTransfer === null) {
            throw $this->exceptionFactory->createCustomerDataMissingException();
        }

        $providedFields = array_keys($restCustomerTransfer->modifiedToArray(true, true));

        if ($requiredFields !== array_intersect($requiredFields, $providedFields)) {
            throw $this->exceptionFactory->createCustomerDataMissingException();
        }
    }
}
