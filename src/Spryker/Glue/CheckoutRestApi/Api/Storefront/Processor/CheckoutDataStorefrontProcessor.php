<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\CheckoutRestApi\Api\Storefront\Processor;

use Generated\Api\Storefront\CheckoutDataStorefrontResource;
use Spryker\Client\CheckoutRestApi\CheckoutRestApiClientInterface;
use Spryker\Glue\CheckoutRestApi\Api\Storefront\Exception\CheckoutExceptionFactory;
use Spryker\Glue\CheckoutRestApi\Api\Storefront\Mapper\CheckoutDataResourceMapper;
use Spryker\Glue\CheckoutRestApi\CheckoutRestApiConfig;
use Spryker\Glue\GlueApplication\Compatibility\RequestBuilder\SyntheticRestRequestBuilderInterface;
use Spryker\Service\Container\Attributes\Plugins;

class CheckoutDataStorefrontProcessor extends AbstractCheckoutStorefrontProcessor
{
    /**
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutRequestAttributesValidatorPluginInterface> $checkoutRequestAttributesValidatorPlugins
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutRequestExpanderPluginInterface> $checkoutRequestExpanderPlugins
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutDataResponseMapperPluginInterface> $checkoutDataResponseMapperPlugins
     */
    public function __construct(
        CheckoutRestApiClientInterface $checkoutRestApiClient,
        SyntheticRestRequestBuilderInterface $syntheticRestRequestBuilder,
        CheckoutExceptionFactory $exceptionFactory,
        protected CheckoutDataResourceMapper $checkoutDataResourceMapper,
        CheckoutRestApiConfig $checkoutRestApiConfig,
        #[Plugins(dependencyProviderMethod: 'getCheckoutRequestAttributesValidatorPlugins')]
        protected array $checkoutRequestAttributesValidatorPlugins = [],
        #[Plugins(dependencyProviderMethod: 'getCheckoutRequestExpanderPlugins')]
        protected array $checkoutRequestExpanderPlugins = [],
        #[Plugins(dependencyProviderMethod: 'getCheckoutDataResponseMapperPlugins')]
        protected array $checkoutDataResponseMapperPlugins = [],
    ) {
        parent::__construct(
            $checkoutRestApiClient,
            $syntheticRestRequestBuilder,
            $exceptionFactory,
            $checkoutRestApiConfig,
        );
    }

    protected function processPost(mixed $data): CheckoutDataStorefrontResource
    {
        $restCheckoutRequestAttributesTransfer = $this->buildRequestAttributesTransfer($data);

        $this->assertPaymentProvidersAreMapped($restCheckoutRequestAttributesTransfer);
        $this->aggregateAndThrowFromValidatorPlugins(
            $this->checkoutRequestAttributesValidatorPlugins,
            $restCheckoutRequestAttributesTransfer,
        );

        $restCheckoutRequestAttributesTransfer = $this->expandCustomerData($restCheckoutRequestAttributesTransfer);
        $restCheckoutRequestAttributesTransfer = $this->expandPaymentSelection($restCheckoutRequestAttributesTransfer);
        $restCheckoutRequestAttributesTransfer = $this->runRequestExpanderPlugins(
            $this->checkoutRequestExpanderPlugins,
            $restCheckoutRequestAttributesTransfer,
        );

        $restCheckoutDataResponseTransfer = $this->checkoutRestApiClient->getCheckoutData($restCheckoutRequestAttributesTransfer);

        if (!$restCheckoutDataResponseTransfer->getIsSuccess()) {
            throw $this->exceptionFactory->createExceptionFromRestCheckoutError(
                $restCheckoutDataResponseTransfer->getErrors()->offsetGet(0),
                $this->getLocale()->getLocaleNameOrFail(),
            );
        }

        $responseAttributesTransfer = $this->checkoutDataResourceMapper->buildResponseAttributes(
            $restCheckoutDataResponseTransfer->getCheckoutData(),
            $restCheckoutRequestAttributesTransfer,
            $this->checkoutDataResponseMapperPlugins,
        );

        return $this->checkoutDataResourceMapper->mapResponseAttributesToResource(
            $responseAttributesTransfer,
            $restCheckoutDataResponseTransfer->getCheckoutData(),
            new CheckoutDataStorefrontResource(),
        );
    }

    protected function getResourceShortName(): string
    {
        return CheckoutRestApiConfig::RESOURCE_CHECKOUT_DATA;
    }
}
