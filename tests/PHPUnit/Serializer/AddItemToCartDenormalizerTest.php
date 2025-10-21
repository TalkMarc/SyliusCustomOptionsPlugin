<?php

declare(strict_types=1);

namespace Tests\Brille24\SyliusCustomerOptionsPlugin\PHPUnit\Serializer;

use Brille24\SyliusCustomerOptionsPlugin\Serializer\AddItemToCartDenormalizer;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class AddItemToCartDenormalizerTest extends TestCase
{
    private DenormalizerInterface $innerDenormalizer;
    private AddItemToCartDenormalizer $denormalizer;

    protected function setUp(): void
    {
        $this->innerDenormalizer = $this->createMock(DenormalizerInterface::class);

        $this->denormalizer = new AddItemToCartDenormalizer();
        $this->denormalizer->setDenormalizer($this->innerDenormalizer);
    }

    public function testDenormalizeAddsCustomerOptionsToCommand(): void
    {
        $data = [
            'productVariantCode' => 'variant-123',
            'quantity' => 2,
            'customerOptions' => [
                'color' => ['red', 'blue'],
                'size' => ['large'],
            ],
        ];

        $command = new AddItemToCart('variant-123', 2);

        $this->innerDenormalizer
            ->expects($this->once())
            ->method('denormalize')
            ->with(
                $data,
                AddItemToCart::class,
                null,
                $this->callback(function ($context) {
                    return isset($context['brille24_add_item_to_cart_denormalizer_already_called']);
                })
            )
            ->willReturn($command);

        $result = $this->denormalizer->denormalize($data, AddItemToCart::class, null, []);

        $this->assertInstanceOf(AddItemToCart::class, $result);
        $this->assertSame([
            'color' => ['red', 'blue'],
            'size' => ['large'],
        ], $result->customerOptions);
    }

    public function testDenormalizeWithoutCustomerOptions(): void
    {
        $data = [
            'productVariantCode' => 'variant-123',
            'quantity' => 2,
        ];

        $command = new AddItemToCart('variant-123', 2);

        $this->innerDenormalizer
            ->expects($this->once())
            ->method('denormalize')
            ->willReturn($command);

        $result = $this->denormalizer->denormalize($data, AddItemToCart::class, null, []);

        $this->assertInstanceOf(AddItemToCart::class, $result);
        $this->assertNull($result->customerOptions ?? null);
    }

    public function testDenormalizeWithEmptyCustomerOptions(): void
    {
        $data = [
            'productVariantCode' => 'variant-123',
            'quantity' => 2,
            'customerOptions' => [],
        ];

        $command = new AddItemToCart('variant-123', 2);

        $this->innerDenormalizer
            ->expects($this->once())
            ->method('denormalize')
            ->willReturn($command);

        $result = $this->denormalizer->denormalize($data, AddItemToCart::class, null, []);

        $this->assertInstanceOf(AddItemToCart::class, $result);
        $this->assertSame([], $result->customerOptions);
    }

    public function testSupportsDenormalizationWithCustomerOptions(): void
    {
        $data = [
            'productVariantCode' => 'variant-123',
            'quantity' => 2,
            'customerOptions' => [
                'color' => ['red'],
            ],
        ];

        $result = $this->denormalizer->supportsDenormalization($data, AddItemToCart::class, null, []);

        $this->assertTrue($result);
    }

    public function testDoesNotSupportDenormalizationWithoutCustomerOptions(): void
    {
        $data = [
            'productVariantCode' => 'variant-123',
            'quantity' => 2,
        ];

        $result = $this->denormalizer->supportsDenormalization($data, AddItemToCart::class, null, []);

        $this->assertFalse($result);
    }

    public function testDoesNotSupportDenormalizationForWrongType(): void
    {
        $data = [
            'productVariantCode' => 'variant-123',
            'quantity' => 2,
            'customerOptions' => [
                'color' => ['red'],
            ],
        ];

        $result = $this->denormalizer->supportsDenormalization($data, \stdClass::class, null, []);

        $this->assertFalse($result);
    }

    public function testDoesNotSupportDenormalizationWhenAlreadyCalled(): void
    {
        $data = [
            'productVariantCode' => 'variant-123',
            'quantity' => 2,
            'customerOptions' => [
                'color' => ['red'],
            ],
        ];

        $context = [
            'brille24_add_item_to_cart_denormalizer_already_called' => true,
        ];

        $result = $this->denormalizer->supportsDenormalization($data, AddItemToCart::class, null, $context);

        $this->assertFalse($result);
    }

    public function testDenormalizeHandlesNonArrayCustomerOptions(): void
    {
        $data = [
            'productVariantCode' => 'variant-123',
            'quantity' => 2,
            'customerOptions' => 'not-an-array',
        ];

        $command = new AddItemToCart('variant-123', 2);

        $this->innerDenormalizer
            ->expects($this->once())
            ->method('denormalize')
            ->willReturn($command);

        $result = $this->denormalizer->denormalize($data, AddItemToCart::class, null, []);

        $this->assertInstanceOf(AddItemToCart::class, $result);
        // Non-array customerOptions should not be set
        $this->assertNull($result->customerOptions ?? null);
    }

    public function testDenormalizePreservesComplexCustomerOptionsStructure(): void
    {
        $data = [
            'productVariantCode' => 'variant-123',
            'quantity' => 2,
            'customerOptions' => [
                'text_option' => ['Custom text'],
                'multi_select' => ['option1', 'option2', 'option3'],
                'date_option' => ['2024-01-15'],
            ],
        ];

        $command = new AddItemToCart('variant-123', 2);

        $this->innerDenormalizer
            ->expects($this->once())
            ->method('denormalize')
            ->willReturn($command);

        $result = $this->denormalizer->denormalize($data, AddItemToCart::class, null, []);

        $this->assertInstanceOf(AddItemToCart::class, $result);
        $this->assertSame([
            'text_option' => ['Custom text'],
            'multi_select' => ['option1', 'option2', 'option3'],
            'date_option' => ['2024-01-15'],
        ], $result->customerOptions);
    }
}
