<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\CheckoutRestApi\Api\Storefront\Processor;

use Generated\Shared\Transfer\RestCheckoutRequestAttributesTransfer;
use Generated\Shared\Transfer\RestCustomerTransfer;
use Generated\Shared\Transfer\RestErrorCollectionTransfer;
use Spryker\ApiPlatform\State\Processor\AbstractStorefrontProcessor;
use Spryker\Client\CheckoutRestApi\CheckoutRestApiClientInterface;
use Spryker\Glue\CheckoutRestApi\Api\Storefront\Exception\CheckoutExceptionFactory;
use Spryker\Glue\CheckoutRestApi\CheckoutRestApiConfig;
use Spryker\Glue\GlueApplication\Compatibility\RequestBuilder\SyntheticRestRequestBuilderInterface;

abstract class AbstractCheckoutStorefrontProcessor extends AbstractStorefrontProcessor
{
    protected const string ANONYMOUS_CUSTOMER_REFERENCE_PREFIX = 'anonymous:';

    protected const string HEADER_ANONYMOUS_CUSTOMER_UNIQUE_ID = 'X-Anonymous-Customer-Unique-Id';

    public function __construct(
        protected CheckoutRestApiClientInterface $checkoutRestApiClient,
        protected SyntheticRestRequestBuilderInterface $syntheticRestRequestBuilder,
        protected CheckoutExceptionFactory $exceptionFactory,
        protected CheckoutRestApiConfig $checkoutRestApiConfig,
    ) {
    }

    abstract protected function getResourceShortName(): string;

    protected function buildRequestAttributesTransfer(mixed $data): RestCheckoutRequestAttributesTransfer
    {
        $rawAttributes = (is_object($data) && method_exists($data, 'toArray')) ? $data->toArray() : [];

        return (new RestCheckoutRequestAttributesTransfer())->fromArray(array_filter(
            $rawAttributes,
            static fn (mixed $value): bool => $value !== null,
        ), true);
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    protected function assertPaymentProvidersAreMapped(RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer): void
    {
        if (!$this->checkoutRestApiConfig->isPaymentProviderMethodToStateMachineMappingEnabled()) {
            return;
        }

        $mapping = $this->checkoutRestApiConfig->getPaymentProviderMethodToStateMachineMapping();

        foreach ($restCheckoutRequestAttributesTransfer->getPayments() as $restPaymentTransfer) {
            $providerName = (string)$restPaymentTransfer->getPaymentProviderName();
            $methodName = (string)$restPaymentTransfer->getPaymentMethodName();

            if (isset($mapping[$providerName][$methodName])) {
                continue;
            }

            throw $this->exceptionFactory->createInvalidPaymentException($methodName, $providerName);
        }
    }

    /**
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutRequestAttributesValidatorPluginInterface>|array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutRequestValidatorPluginInterface> $plugins
     */
    protected function aggregateAndThrowFromValidatorPlugins(
        array $plugins,
        RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer,
    ): void {
        $aggregatedErrors = new RestErrorCollectionTransfer();

        foreach ($plugins as $plugin) {
            $pluginErrors = $plugin->validateAttributes($restCheckoutRequestAttributesTransfer);

            foreach ($pluginErrors->getRestErrors() as $restErrorMessageTransfer) {
                $aggregatedErrors->addRestError($restErrorMessageTransfer);
            }
        }

        $this->throwAllErrors($aggregatedErrors);
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    protected function throwAllErrors(RestErrorCollectionTransfer $errorCollection): void
    {
        if ($errorCollection->getRestErrors()->count() === 0) {
            return;
        }

        $firstError = $errorCollection->getRestErrors()->offsetGet(0);
        $exception = $this->exceptionFactory->createExceptionFromRestErrorMessage($firstError);

        $errors = [];

        foreach ($errorCollection->getRestErrors() as $restErrorMessageTransfer) {
            $errors[] = [
                'code' => $restErrorMessageTransfer->getCode() ?? $exception->getErrorCode(),
                'status' => $restErrorMessageTransfer->getStatus() ?? $exception->getStatusCode(),
                'detail' => $restErrorMessageTransfer->getDetail() ?? '',
            ];
        }

        throw $exception->setErrors($errors);
    }

    protected function expandCustomerData(
        RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer,
    ): RestCheckoutRequestAttributesTransfer {
        $restCustomerTransfer = new RestCustomerTransfer();

        if ($restCheckoutRequestAttributesTransfer->getCustomer() !== null) {
            $restCustomerTransfer->fromArray(
                $restCheckoutRequestAttributesTransfer->getCustomer()->toArray(),
                true,
            );
        }

        $this->applyCustomerIdentity($restCustomerTransfer);

        return $restCheckoutRequestAttributesTransfer->setCustomer($restCustomerTransfer);
    }

    protected function applyCustomerIdentity(RestCustomerTransfer $restCustomerTransfer): void
    {
        if (!$this->hasCustomer()) {
            $restCustomerTransfer->setCustomerReference($this->buildAnonymousCustomerReference());

            return;
        }

        $customerTransfer = $this->getCustomer();

        if ($customerTransfer->getIdCustomer() !== null) {
            $restCustomerTransfer->setIdCustomer($customerTransfer->getIdCustomer());
        }

        $customerReference = $customerTransfer->getCustomerReference();

        if ($customerReference !== null) {
            $restCustomerTransfer->setCustomerReference((string)$customerReference);

            return;
        }

        $restCustomerTransfer->setCustomerReference($this->buildAnonymousCustomerReference());
    }

    protected function buildAnonymousCustomerReference(): string
    {
        $anonymousId = (string)$this->getRequest()->headers->get(static::HEADER_ANONYMOUS_CUSTOMER_UNIQUE_ID, '');

        return $anonymousId !== '' ? static::ANONYMOUS_CUSTOMER_REFERENCE_PREFIX . $anonymousId : '';
    }

    protected function expandPaymentSelection(
        RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer,
    ): RestCheckoutRequestAttributesTransfer {
        $mapping = $this->checkoutRestApiConfig->getPaymentProviderMethodToStateMachineMapping();

        foreach ($restCheckoutRequestAttributesTransfer->getPayments() as $restPaymentTransfer) {
            $providerName = (string)$restPaymentTransfer->getPaymentProviderName();
            $methodName = (string)$restPaymentTransfer->getPaymentMethodName();

            if (!isset($mapping[$providerName][$methodName])) {
                continue;
            }

            $restPaymentTransfer->setPaymentSelection($mapping[$providerName][$methodName]);
        }

        return $restCheckoutRequestAttributesTransfer;
    }

    /**
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutRequestExpanderPluginInterface> $plugins
     */
    protected function runRequestExpanderPlugins(
        array $plugins,
        RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer,
    ): RestCheckoutRequestAttributesTransfer {
        $legacyRestRequest = $this->syntheticRestRequestBuilder->build(
            $this->getRequest(),
            $this->hasCustomer() ? $this->getCustomer() : null,
            $this->getResourceShortName(),
        );

        foreach ($plugins as $plugin) {
            $restCheckoutRequestAttributesTransfer = $plugin->expand(
                $legacyRestRequest,
                $restCheckoutRequestAttributesTransfer,
            );
        }

        return $restCheckoutRequestAttributesTransfer;
    }
}
