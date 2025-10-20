<?php

declare(strict_types=1);

namespace Brille24\SyliusCustomerOptionsPlugin\Swagger;

use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\OpenApi;

final class AddItemToCartOpenApiFactoryDecorator implements OpenApiFactoryInterface
{
    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $customerOptionsProperty = [
            'type' => 'object',
            'description' => 'Customer options configuration: keys are customer option codes, values are arrays of selected values.',
            'additionalProperties' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'example' => [
                'some_option' => ['val_1', 'val_2'],
                'text_option' => ['Custom text input'],
            ],
        ];

        // 1) Update component schemas named like *AddItemToCart*
        $components = $openApi->getComponents();
        $schemas = $components->getSchemas();

        foreach ($schemas as $name => $schema) {
            if (\is_string($name) && stripos($name, 'AddItemToCart') !== false) {
                if ($schema instanceof \ArrayObject) {
                    $schema['properties']['customerOptions'] = $customerOptionsProperty;
                    $schemas[$name] = $schema;
                } elseif (\is_array($schema)) {
                    $schema['properties']['customerOptions'] = $customerOptionsProperty;
                    $schemas[$name] = $schema;
                }
            }
        }

        $components = $components->withSchemas($schemas);
        $openApi = $openApi->withComponents($components);

        // 2) Also handle inline requestBody schemas (not using $ref)
        $paths = $openApi->getPaths();
        foreach ($paths->getPaths() as $path => $pathItem) {
            $newPathItem = $pathItem;

            $ops = [
                'get' => $pathItem->getGet(),
                'post' => $pathItem->getPost(),
                'put' => $pathItem->getPut(),
                'patch' => $pathItem->getPatch(),
                'delete' => $pathItem->getDelete(),
            ];

            foreach ($ops as $method => $operation) {
                if (!$operation instanceof Operation) {
                    continue;
                }

                $requestBody = $operation->getRequestBody();
                if (!$requestBody instanceof RequestBody) {
                    continue;
                }

                $content = $requestBody->getContent();

                $updated = false;
                /** @var mixed $mediaType */
                foreach ($content as $mime => $mediaType) {
                    $schema = null;
                    $isObjectMediaType = $mediaType instanceof MediaType;

                    if ($isObjectMediaType) {
                        $schema = $mediaType->getSchema();
                    } elseif (is_array($mediaType) && isset($mediaType['schema'])) {
                        $schema = $mediaType['schema'];
                    }

                    if ($schema === null) {
                        continue;
                    }

                    // If it references a component containing AddItemToCart, the component edit above is enough
                    if (
                        (is_array($schema) || $schema instanceof \ArrayObject) &&
                        isset($schema['$ref']) &&
                        stripos((string) $schema['$ref'], 'AddItemToCart') !== false
                    ) {
                        continue;
                    }

                    // Try to detect inline AddItemToCart-like payload and enrich it
                    if (
                        (is_array($schema) || $schema instanceof \ArrayObject) &&
                        isset($schema['type']) && $schema['type'] === 'object' &&
                        isset($schema['properties']) &&
                        (\is_array($schema['properties']) || $schema['properties'] instanceof \ArrayObject) &&
                        (
                            isset($schema['properties']['productVariant']) ||
                            isset($schema['properties']['productVariantCode'])
                        )
                    ) {
                        $schema['properties']['customerOptions'] = $customerOptionsProperty;

                        if ($isObjectMediaType) {
                            $mediaType = $mediaType->withSchema($schema);
                        } else {
                            $mediaType['schema'] = $schema;
                        }

                        $content[$mime] = $mediaType;
                        $updated = true;
                    }
                }

                if ($updated) {
                    $requestBody = $requestBody->withContent($content);
                    $operation = $operation->withRequestBody($requestBody);

                    switch ($method) {
                        case 'get':
                            $newPathItem = $newPathItem->withGet($operation);
                            break;
                        case 'post':
                            $newPathItem = $newPathItem->withPost($operation);
                            break;
                        case 'put':
                            $newPathItem = $newPathItem->withPut($operation);
                            break;
                        case 'patch':
                            $newPathItem = $newPathItem->withPatch($operation);
                            break;
                        case 'delete':
                            $newPathItem = $newPathItem->withDelete($operation);
                            break;
                    }
                }
            }

            // ApiPlatform 2.7 Paths has addPath(), not withPath()
            if ($newPathItem !== $pathItem) {
                $paths->addPath($path, $newPathItem);
            }
        }

        return $openApi->withPaths($paths);
    }
}
