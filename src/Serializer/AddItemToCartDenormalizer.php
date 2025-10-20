<?php

declare(strict_types=1);

namespace Brille24\SyliusCustomerOptionsPlugin\Serializer;

use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class AddItemToCartDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'brille24_add_item_to_cart_denormalizer_already_called';

    public function denormalize($data, string $type, string $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var AddItemToCart $object */
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        // Add customer options if present in the data
        if (isset($data['customerOptions']) && is_array($data['customerOptions'])) {
            $object->customerOptions = $data['customerOptions'];
        }

        return $object;
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $type === AddItemToCart::class && isset($data['customerOptions']);
    }
}
