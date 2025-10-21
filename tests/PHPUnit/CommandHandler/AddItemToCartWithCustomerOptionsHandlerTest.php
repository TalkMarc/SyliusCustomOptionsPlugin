<?php

declare(strict_types=1);

namespace Tests\Brille24\SyliusCustomerOptionsPlugin\PHPUnit\CommandHandler;

use ApiPlatform\Exception\InvalidArgumentException;
use Brille24\SyliusCustomerOptionsPlugin\CommandHandler\AddItemToCartWithCustomerOptionsHandler;
use Brille24\SyliusCustomerOptionsPlugin\Entity\CustomerOptions\CustomerOptionInterface;
use Brille24\SyliusCustomerOptionsPlugin\Entity\CustomerOptions\CustomerOptionValueInterface;
use Brille24\SyliusCustomerOptionsPlugin\Entity\OrderItemOptionInterface;
use Brille24\SyliusCustomerOptionsPlugin\Entity\OrderItemInterface;
use Brille24\SyliusCustomerOptionsPlugin\Entity\ProductInterface;
use Brille24\SyliusCustomerOptionsPlugin\Factory\OrderItemOptionFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class AddItemToCartWithCustomerOptionsHandlerTest extends TestCase
{
    private MessageHandlerInterface $decoratedHandler;
    private OrderItemOptionFactoryInterface $orderItemOptionFactory;
    private OrderProcessorInterface $orderProcessor;
    private AddItemToCartWithCustomerOptionsHandler $handler;

    protected function setUp(): void
    {
        $this->decoratedHandler = $this->getMockBuilder(MessageHandlerInterface::class)
            ->addMethods(['__invoke'])
            ->getMock();
        $this->orderItemOptionFactory = $this->createMock(OrderItemOptionFactoryInterface::class);
        $this->orderProcessor = $this->createMock(OrderProcessorInterface::class);

        $this->handler = new AddItemToCartWithCustomerOptionsHandler(
            $this->decoratedHandler,
            $this->orderItemOptionFactory,
            $this->orderProcessor
        );
    }

    public function testInvokesDecoratedHandlerWithoutCustomerOptions(): void
    {
        $command = new AddItemToCart('variant-code', 1);
        $command->orderTokenValue = 'token';

        $cart = $this->createMock(OrderInterface::class);

        $this->decoratedHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($command)
            ->willReturn($cart);

        $this->orderProcessor
            ->expects($this->never())
            ->method('process');

        $result = ($this->handler)($command);

        $this->assertSame($cart, $result);
    }

    public function testAddsValidCustomerOptionsToCartItem(): void
    {
        $command = new AddItemToCart('variant-code', 1);
        $command->orderTokenValue = 'token';
        $command->customerOptions = [
            'color' => ['red'],
            'size' => ['large'],
        ];

        $cart = $this->createMock(OrderInterface::class);
        $cartItem = $this->createMock(OrderItemInterface::class);
        $product = $this->createMock(ProductInterface::class);

        $colorOption = $this->createMock(CustomerOptionInterface::class);
        $colorOption->method('getCode')->willReturn('color');

        $sizeOption = $this->createMock(CustomerOptionInterface::class);
        $sizeOption->method('getCode')->willReturn('size');

        $product->method('getCustomerOptions')->willReturn([$colorOption, $sizeOption]);

        $cartItem->method('getProduct')->willReturn($product);

        $items = new ArrayCollection([$cartItem]);
        $cart->method('getItems')->willReturn($items);

        $this->decoratedHandler
            ->method('__invoke')
            ->with($command)
            ->willReturn($cart);

        $colorOrderItemOption = $this->createMock(OrderItemOptionInterface::class);
        $colorOrderItemOption->method('getCustomerOptionType')->willReturn('select');
        $colorOrderItemOption->method('getCustomerOptionValue')->willReturn(
            $this->createMock(CustomerOptionValueInterface::class)
        );

        $sizeOrderItemOption = $this->createMock(OrderItemOptionInterface::class);
        $sizeOrderItemOption->method('getCustomerOptionType')->willReturn('select');
        $sizeOrderItemOption->method('getCustomerOptionValue')->willReturn(
            $this->createMock(CustomerOptionValueInterface::class)
        );

        $this->orderItemOptionFactory
            ->expects($this->exactly(2))
            ->method('createNewFromStrings')
            ->willReturnOnConsecutiveCalls($colorOrderItemOption, $sizeOrderItemOption);

        $cartItem
            ->expects($this->once())
            ->method('setCustomerOptionConfiguration')
            ->with([$colorOrderItemOption, $sizeOrderItemOption]);

        $this->orderProcessor
            ->expects($this->once())
            ->method('process')
            ->with($cart);

        $result = ($this->handler)($command);

        $this->assertSame($cart, $result);
    }

    public function testThrowsExceptionForInvalidCustomerOptionCode(): void
    {
        $command = new AddItemToCart('variant-code', 1);
        $command->orderTokenValue = 'token';
        $command->customerOptions = [
            'invalid_option' => ['value'],
        ];

        $cart = $this->createMock(OrderInterface::class);
        $cartItem = $this->createMock(OrderItemInterface::class);
        $product = $this->createMock(ProductInterface::class);

        $validOption = $this->createMock(CustomerOptionInterface::class);
        $validOption->method('getCode')->willReturn('valid_option');

        $product->method('getCustomerOptions')->willReturn([$validOption]);
        $cartItem->method('getProduct')->willReturn($product);

        $items = new ArrayCollection([$cartItem]);
        $cart->method('getItems')->willReturn($items);

        $this->decoratedHandler
            ->method('__invoke')
            ->with($command)
            ->willReturn($cart);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer option "invalid_option" is not available for this product.');

        ($this->handler)($command);
    }

    public function testThrowsExceptionForInvalidSelectValue(): void
    {
        $command = new AddItemToCart('variant-code', 1);
        $command->orderTokenValue = 'token';
        $command->customerOptions = [
            'color' => ['bogus_value'],
        ];

        $cart = $this->createMock(OrderInterface::class);
        $cartItem = $this->createMock(OrderItemInterface::class);
        $product = $this->createMock(ProductInterface::class);

        $colorOption = $this->createMock(CustomerOptionInterface::class);
        $colorOption->method('getCode')->willReturn('color');

        $product->method('getCustomerOptions')->willReturn([$colorOption]);
        $cartItem->method('getProduct')->willReturn($product);

        $items = new ArrayCollection([$cartItem]);
        $cart->method('getItems')->willReturn($items);

        $this->decoratedHandler
            ->method('__invoke')
            ->with($command)
            ->willReturn($cart);

        // OrderItemOption with null value (invalid)
        $orderItemOption = $this->createMock(OrderItemOptionInterface::class);
        $orderItemOption->method('getCustomerOptionType')->willReturn('select');
        $orderItemOption->method('getCustomerOptionValue')->willReturn(null);
        $orderItemOption->method('getCustomerOptionCode')->willReturn('color');

        $this->orderItemOptionFactory
            ->method('createNewFromStrings')
            ->willReturn($orderItemOption);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for customer option "color". Please provide a valid option value code.');

        ($this->handler)($command);
    }

    public function testAcceptsTextCustomerOptionWithAnyValue(): void
    {
        $command = new AddItemToCart('variant-code', 1);
        $command->orderTokenValue = 'token';
        $command->customerOptions = [
            'custom_text' => ['Any text value'],
        ];

        $cart = $this->createMock(OrderInterface::class);
        $cartItem = $this->createMock(OrderItemInterface::class);
        $product = $this->createMock(ProductInterface::class);

        $textOption = $this->createMock(CustomerOptionInterface::class);
        $textOption->method('getCode')->willReturn('custom_text');

        $product->method('getCustomerOptions')->willReturn([$textOption]);
        $cartItem->method('getProduct')->willReturn($product);

        $items = new ArrayCollection([$cartItem]);
        $cart->method('getItems')->willReturn($items);

        $this->decoratedHandler
            ->method('__invoke')
            ->with($command)
            ->willReturn($cart);

        $orderItemOption = $this->createMock(OrderItemOptionInterface::class);
        $orderItemOption->method('getCustomerOptionType')->willReturn('text');
        $orderItemOption->method('getCustomerOptionValue')->willReturn(null);

        $this->orderItemOptionFactory
            ->method('createNewFromStrings')
            ->willReturn($orderItemOption);

        $cartItem
            ->expects($this->once())
            ->method('setCustomerOptionConfiguration')
            ->with([$orderItemOption]);

        $this->orderProcessor
            ->expects($this->once())
            ->method('process')
            ->with($cart);

        $result = ($this->handler)($command);

        $this->assertSame($cart, $result);
    }

    public function testHandlesMultipleValuesForSameOption(): void
    {
        $command = new AddItemToCart('variant-code', 1);
        $command->orderTokenValue = 'token';
        $command->customerOptions = [
            'toppings' => ['cheese', 'bacon', 'onions'],
        ];

        $cart = $this->createMock(OrderInterface::class);
        $cartItem = $this->createMock(OrderItemInterface::class);
        $product = $this->createMock(ProductInterface::class);

        $toppingsOption = $this->createMock(CustomerOptionInterface::class);
        $toppingsOption->method('getCode')->willReturn('toppings');

        $product->method('getCustomerOptions')->willReturn([$toppingsOption]);
        $cartItem->method('getProduct')->willReturn($product);

        $items = new ArrayCollection([$cartItem]);
        $cart->method('getItems')->willReturn($items);

        $this->decoratedHandler
            ->method('__invoke')
            ->with($command)
            ->willReturn($cart);

        $orderItemOption1 = $this->createMock(OrderItemOptionInterface::class);
        $orderItemOption1->method('getCustomerOptionType')->willReturn('multi_select');
        $orderItemOption1->method('getCustomerOptionValue')->willReturn(
            $this->createMock(CustomerOptionValueInterface::class)
        );

        $orderItemOption2 = $this->createMock(OrderItemOptionInterface::class);
        $orderItemOption2->method('getCustomerOptionType')->willReturn('multi_select');
        $orderItemOption2->method('getCustomerOptionValue')->willReturn(
            $this->createMock(CustomerOptionValueInterface::class)
        );

        $orderItemOption3 = $this->createMock(OrderItemOptionInterface::class);
        $orderItemOption3->method('getCustomerOptionType')->willReturn('multi_select');
        $orderItemOption3->method('getCustomerOptionValue')->willReturn(
            $this->createMock(CustomerOptionValueInterface::class)
        );

        $this->orderItemOptionFactory
            ->expects($this->exactly(3))
            ->method('createNewFromStrings')
            ->willReturnOnConsecutiveCalls($orderItemOption1, $orderItemOption2, $orderItemOption3);

        $cartItem
            ->expects($this->once())
            ->method('setCustomerOptionConfiguration')
            ->with([$orderItemOption1, $orderItemOption2, $orderItemOption3]);

        $this->orderProcessor
            ->expects($this->once())
            ->method('process')
            ->with($cart);

        $result = ($this->handler)($command);

        $this->assertSame($cart, $result);
    }

    public function testHandlesProductWithoutCustomerOptions(): void
    {
        $command = new AddItemToCart('variant-code', 1);
        $command->orderTokenValue = 'token';
        $command->customerOptions = [
            'some_option' => ['value'],
        ];

        $cart = $this->createMock(OrderInterface::class);
        $cartItem = $this->createMock(OrderItemInterface::class);
        $product = $this->createMock(ProductInterface::class);

        $product->method('getCustomerOptions')->willReturn([]);
        $cartItem->method('getProduct')->willReturn($product);

        $items = new ArrayCollection([$cartItem]);
        $cart->method('getItems')->willReturn($items);

        $this->decoratedHandler
            ->method('__invoke')
            ->with($command)
            ->willReturn($cart);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer option "some_option" is not available for this product.');

        ($this->handler)($command);
    }

    public function testReturnsEarlyWhenNoItemsInCart(): void
    {
        $command = new AddItemToCart('variant-code', 1);
        $command->orderTokenValue = 'token';
        $command->customerOptions = [
            'color' => ['red'],
        ];

        $cart = $this->createMock(OrderInterface::class);
        $items = new ArrayCollection([]);
        $cart->method('getItems')->willReturn($items);

        $this->decoratedHandler
            ->method('__invoke')
            ->with($command)
            ->willReturn($cart);

        $this->orderProcessor
            ->expects($this->never())
            ->method('process');

        $result = ($this->handler)($command);

        $this->assertSame($cart, $result);
    }
}
