<?php

declare(strict_types=1);

namespace Brille24\SyliusCustomerOptionsPlugin\CommandHandler;

use ApiPlatform\Exception\InvalidArgumentException;
use Brille24\SyliusCustomerOptionsPlugin\Entity\OrderItemOptionInterface;
use Brille24\SyliusCustomerOptionsPlugin\Entity\ProductInterface;
use Brille24\SyliusCustomerOptionsPlugin\Factory\OrderItemOptionFactoryInterface;
use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class AddItemToCartWithCustomerOptionsHandler implements MessageHandlerInterface
{
    public function __construct(
        private MessageHandlerInterface $decoratedHandler,
        private OrderItemOptionFactoryInterface $orderItemOptionFactory,
        private OrderProcessorInterface $orderProcessor,
    ) {
    }

    public function __invoke(AddItemToCart $addItemToCart): OrderInterface
    {
        /** @var OrderInterface $cart */
        $cart = ($this->decoratedHandler)($addItemToCart);

        // Check if the command has customer options configured
        if (!isset($addItemToCart->customerOptions) || empty($addItemToCart->customerOptions)) {
            return $cart;
        }

        // Find the last added item (the one we just added)
        /** @var OrderItemInterface $cartItem */
        $cartItem = $cart->getItems()->last();

        if ($cartItem === false) {
            return $cart;
        }

        $salesOrderConfigurations = [];

        // Validate that all submitted customer option codes belong to the product
        /** @var ProductInterface $product */
        $product = $cartItem->getProduct();
        $this->validateCustomerOptionCodes($product, array_keys($addItemToCart->customerOptions));

        foreach ($addItemToCart->customerOptions as $customerOptionCode => $valueArray) {
            if (!is_array($valueArray)) {
                $valueArray = [$valueArray];
            }

            // Flatten nested arrays
            foreach ($valueArray as $key => $value) {
                if (is_array($value)) {
                    $valueArray = array_merge($valueArray, $value);
                    unset($valueArray[$key]);
                }
            }

            foreach ($valueArray as $value) {
                $salesOrderConfiguration = $this->orderItemOptionFactory->createNewFromStrings(
                    $cartItem,
                    $customerOptionCode,
                    $value,
                );

                // Validate that select/multi_select options have valid values
                $this->validateOrderItemOption($salesOrderConfiguration);

                $salesOrderConfigurations[] = $salesOrderConfiguration;
            }
        }

        $cartItem->setCustomerOptionConfiguration($salesOrderConfigurations);

        // Process the order to recalculate adjustments for customer options
        $this->orderProcessor->process($cart);

        return $cart;
    }

    private function validateCustomerOptionCodes(ProductInterface $product, array $submittedCodes): void
    {
        $validCustomerOptions = $product->getCustomerOptions();
        $validCodes = array_map(fn($option) => $option->getCode(), $validCustomerOptions);

        foreach ($submittedCodes as $code) {
            if (!in_array($code, $validCodes, true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Customer option "%s" is not available for this product.',
                        $code
                    )
                );
            }
        }
    }

    private function validateOrderItemOption(OrderItemOptionInterface $orderItemOption): void
    {
        $customerOptionType = $orderItemOption->getCustomerOptionType();

        // For select and multi_select options, the value must be a valid CustomerOptionValue
        if (in_array($customerOptionType, ['select', 'multi_select'], true)) {
            if ($orderItemOption->getCustomerOptionValue() === null) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid value for customer option "%s". Please provide a valid option value code.',
                        $orderItemOption->getCustomerOptionCode()
                    )
                );
            }
        }
    }
}
