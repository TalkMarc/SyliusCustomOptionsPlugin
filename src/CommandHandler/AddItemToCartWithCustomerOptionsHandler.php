<?php

declare(strict_types=1);

namespace Brille24\SyliusCustomerOptionsPlugin\CommandHandler;

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

                $salesOrderConfigurations[] = $salesOrderConfiguration;
            }
        }

        $cartItem->setCustomerOptionConfiguration($salesOrderConfigurations);

        // Process the order to recalculate adjustments for customer options
        $this->orderProcessor->process($cart);

        return $cart;
    }
}
