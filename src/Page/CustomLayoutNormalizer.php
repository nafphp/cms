<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use Naf\CMS\Theme\Theme;

final class CustomLayoutNormalizer
{
    public const MAX_SLOTS = 50;

    /**
     * @param array<int, mixed> $layout
     * @param array<int, string> $errors
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $layout, array &$errors = []): array
    {
        if (count($layout) > self::MAX_SLOTS) {
            $errors[] = sprintf('Custom layout exceeds the limit of %d slots.', self::MAX_SLOTS);
            $layout   = array_slice($layout, 0, self::MAX_SLOTS);
        }

        $normalized = [];
        $seen       = [];

        foreach (array_values($layout) as $index => $slot) {
            if (!is_array($slot)) {
                $errors[] = sprintf('Custom layout slot %d must be an object.', $index + 1);
                continue;
            }

            $id        = trim((string) ($slot['id'] ?? ''));
            $label     = trim((string) ($slot['label'] ?? ''));
            $region    = trim((string) ($slot['region'] ?? 'main'));
            $size      = trim((string) ($slot['size'] ?? 'medium'));
            $position  = trim((string) ($slot['position'] ?? 'stretch'));
            $maxHeight = trim((string) ($slot['maxHeight'] ?? 'none'));
            $overflow  = trim((string) ($slot['overflow'] ?? 'visible'));
            $surface   = Theme::slug((string) ($slot['surface'] ?? 'none')) ?: 'none';

            if ($id === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $id)) {
                $errors[] = sprintf('Custom layout slot %d needs a valid id.', $index + 1);
                continue;
            }

            if (isset($seen[$id])) {
                $errors[] = sprintf('Custom layout contains duplicate slot id "%s".', $id);
                continue;
            }
            $seen[$id] = true;

            if ($label === '') {
                $errors[] = sprintf('Custom layout slot "%s" needs a label.', $id);
                continue;
            }
            if (mb_strlen($label) > 160) {
                $errors[] = sprintf('Custom layout slot "%s" label exceeds 160 characters.', $id);
                continue;
            }

            $invalidOption = $this->invalidOption([
                'region'        => [$region, CustomLayoutOptions::REGIONS],
                'size'          => [$size, CustomLayoutOptions::SIZES],
                'position'      => [$position, CustomLayoutOptions::POSITIONS],
                'max height'    => [$maxHeight, CustomLayoutOptions::MAX_HEIGHTS],
                'overflow mode' => [$overflow, CustomLayoutOptions::OVERFLOWS],
            ]);
            if ($invalidOption !== null) {
                $errors[] = sprintf('Custom layout slot "%s" uses an invalid %s.', $id, $invalidOption);
                continue;
            }

            $definition = [
                'id'        => $id,
                'label'     => $label,
                'region'    => $region,
                'size'      => $size,
                'position'  => $position,
                'maxHeight' => $maxHeight,
                'overflow'  => $overflow,
                'surface'   => $surface,
            ];

            $accepts = [];
            foreach (is_array($slot['accepts'] ?? null) ? $slot['accepts'] : [] as $type) {
                $type = trim((string) $type);
                if ($type !== '' && preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $type)) {
                    $accepts[$type] = $type;
                }
            }
            if ($accepts !== []) {
                $definition['accepts'] = array_values($accepts);
            }

            $defaultContentType = trim((string) ($slot['defaultContentType'] ?? ''));
            if ($defaultContentType !== '' && preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $defaultContentType)) {
                $definition['defaultContentType'] = $defaultContentType;
            }

            $normalized[] = $definition;
        }

        return $normalized;
    }

    /**
     * @param array<string, array{0: string, 1: array<int, string>}> $options
     */
    private function invalidOption(array $options): ?string
    {
        foreach ($options as $label => [$value, $allowed]) {
            if (!in_array($value, $allowed, true)) {
                return $label;
            }
        }

        return null;
    }
}
