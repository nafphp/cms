<?php

declare(strict_types=1);

namespace NixCMS\Stage;

use NixCMS\Stage\Attributes\StageField;
use ReflectionClass;

final class StageReflection
{
    /**
     * @return array<string, StageFieldDefinition>
     */
    public function fields(object|string $objectOrClass): array
    {
        $reflection = new ReflectionClass($objectOrClass);
        $fields = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(StageField::class);
            if ($attributes === []) {
                continue;
            }

            /** @var StageField $attribute */
            $attribute = $attributes[0]->newInstance();
            $name = trim((string) ($attribute->name ?? $property->getName()));
            if ($name === '') {
                continue;
            }

            $fields[$name] = new StageFieldDefinition(
                $name,
                $property->getName(),
                $attribute->writable,
                $attribute->json,
                $property,
            );
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(object $object): array
    {
        $snapshot = [];
        foreach ($this->fields($object) as $field) {
            $value = $field->reflection->getValue($object);
            if ($field->json) {
                $value = $this->decodeJson((string) ($value ?? ''));
            }

            $snapshot[$field->name] = $value;
        }

        return $snapshot;
    }

    private function decodeJson(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
