<?php

declare(strict_types=1);

namespace NixPHP\CMS\Commands;

use NixPHP\CMS\Services\Ai\LocalAiToolCatalog;
use NixPHP\CMS\Services\Ai\ToolRouting\KeywordToolRouter;
use NixPHP\CMS\Services\Ai\ToolRouting\SemanticToolRouter;
use NixPHP\CMS\Services\Ai\ToolRouting\ToolContext;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;
use function NixPHP\CMS\cms;
use function NixPHP\MCP\tool;

final class DebugAiToolRoutingCommand extends AbstractCommand
{
    public const string NAME = 'ai:tools:debug';

    protected function configure(): void
    {
        $this
            ->setTitle('Debug AI Tool Routing')
            ->setDescription('Show which CMS tools the local AI routing layer selects for a phrase.')
            ->addArgument('phrase')
            ->addOption('area', null, true)
            ->addOption('mode', null, true)
            ->addOption('capability', null, true)
            ->addOption('content-type', null, true)
            ->addOption('page-id', null, true)
            ->addOption('layout-id', null, true)
            ->addOption('theme-id', null, true)
            ->addOption('limit', 'l', true)
            ->addOption('keyword')
            ->addOption('json')
            ->addOption('help');
    }

    public function run(Input $input, Output $output): int
    {
        if ($input->getOption('help') === true) {
            $output->writeLine('  Usage:');
            $output->writeLine('    php bin/nix ai:tools:debug "Wie ist die Systemgesundheit?"');
            $output->writeLine('    php bin/nix ai:tools:debug "Wie wird das Wetter morgen in München?" --limit=5');
            $output->writeLine('    php bin/nix ai:tools:debug "Füge einen Block hinzu" --area=pageBuilder --mode=write --capability=pages.write');
            $output->writeEmptyLine();
            $output->writeLine('  Options:');
            $output->writeLine('    --keyword          Force keyword routing instead of the configured embedding router.');
            $output->writeLine('    --json             Print the selected tool definitions as JSON.');
            $output->writeLine('    --capability       May be passed multiple times.');
            return self::SUCCESS;
        }

        $phrase = trim((string) $input->getArgument('phrase'));
        if ($phrase === '') {
            $output->writeLine('Phrase is required.', 'error');
            return self::ERROR;
        }

        $limit = $this->positiveInt($input->getOption('limit'), 8);
        $definitions = (new LocalAiToolCatalog())->enrich(tool()->definitions());
        $context = ToolContext::fromArray($this->contextFromInput($input));
        $router = $input->getOption('keyword') === true
            ? new KeywordToolRouter($definitions)
            : new SemanticToolRouter($definitions);

        $selected = $router->select($phrase, $context, $limit);

        if ($input->getOption('json') === true) {
            $output->writeLine(json_encode($selected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]');
            return self::SUCCESS;
        }

        $embeddingModel = trim((string) cms()->settings()->value('ai.embedding_model', ''));
        $output->writeLine('AI tool routing debug', 'ok');
        $output->writeLine(sprintf('  Phrase:          %s', $phrase));
        $output->writeLine(sprintf(
            '  Router:          %s',
            $input->getOption('keyword') === true
                ? 'keyword forced'
                : ($embeddingModel !== '' ? 'semantic embeddings (' . $embeddingModel . ')' : 'keyword fallback')
        ));
        $output->writeLine(sprintf('  Tool catalog:    %d visible tools', count($definitions)));
        $this->writeContext($output, $context);
        $output->writeEmptyLine();

        if ($selected === []) {
            $output->writeLine('No matching tool was selected.', 'error');
            $output->writeLine('If this phrase should trigger a tool, improve that tool description, routing description, examples, or capabilities.');
            return self::SUCCESS;
        }

        $output->writeLine(sprintf('Selected tools (%d):', count($selected)));
        foreach ($selected as $index => $tool) {
            $this->writeTool($output, $index + 1, $tool);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFromInput(Input $input): array
    {
        return array_filter([
            'area' => $this->stringOption($input, 'area'),
            'mode' => $this->stringOption($input, 'mode'),
            'contentType' => $this->stringOption($input, 'content-type'),
            'pageId' => $this->stringOption($input, 'page-id'),
            'layoutId' => $this->stringOption($input, 'layout-id'),
            'themeId' => $this->stringOption($input, 'theme-id'),
            'capabilities' => $this->listOption($input->getOption('capability')),
        ], static fn(mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    private function writeContext(Output $output, ToolContext $context): void
    {
        $parts = array_filter([
            $context->area !== '' ? 'area=' . $context->area : '',
            $context->requestedMode() !== null ? 'mode=' . $context->requestedMode() : '',
            ($context->contentType ?? '') !== '' ? 'contentType=' . $context->contentType : '',
            ($context->pageId ?? '') !== '' ? 'pageId=' . $context->pageId : '',
            ($context->layoutId ?? '') !== '' ? 'layoutId=' . $context->layoutId : '',
            ($context->themeId ?? '') !== '' ? 'themeId=' . $context->themeId : '',
            $context->requestedCapabilities() !== [] ? 'capabilities=' . implode(',', $context->requestedCapabilities()) : '',
        ]);

        $output->writeLine(sprintf('  Context:         %s', $parts === [] ? '-' : implode('; ', $parts)));
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function writeTool(Output $output, int $position, array $tool): void
    {
        $meta = is_array($tool['meta'] ?? null) ? $tool['meta'] : [];
        $route = is_array($tool['route'] ?? null) ? $tool['route'] : [];
        $name = trim((string) ($tool['name'] ?? ''));
        $score = is_numeric($route['score'] ?? null) ? (float) $route['score'] : null;
        $reasons = is_array($route['reasons'] ?? null) ? array_map('strval', $route['reasons']) : [];
        $capabilities = is_array($meta['capabilities'] ?? null) ? array_map('strval', $meta['capabilities']) : [];

        $output->writeLine(sprintf('  %d. %s (%s)', $position, $this->titleFromName($name), $name));
        $output->writeLine(sprintf(
            '     score: %s  reasons: %s',
            $score === null ? '-' : rtrim(rtrim(number_format($score, 4, '.', ''), '0'), '.'),
            $reasons === [] ? '-' : implode(', ', $reasons)
        ));
        $output->writeLine(sprintf(
            '     meta:  risk=%s mode=%s category=%s',
            (string) ($meta['risk'] ?? '-'),
            (string) ($meta['mode'] ?? '-'),
            (string) ($meta['category'] ?? '-')
        ));
        $output->writeLine(sprintf('     caps:  %s', $capabilities === [] ? '-' : implode(', ', $capabilities)));
        $output->writeLine(sprintf('     desc:  %s', $this->oneLine((string) ($tool['description'] ?? '-'))));

        $examples = is_array($meta['examples'] ?? null) ? array_values(array_filter(array_map('strval', $meta['examples']))) : [];
        if ($examples !== []) {
            $output->writeLine(sprintf('     ex:    %s', implode(' | ', array_slice($examples, 0, 3))));
        }

        if ($name === 'cms_tools_search' && in_array('no-match-search', $reasons, true)) {
            $output->writeLine('     note:  No direct confident match. The assistant should use this to search iteratively.');
        }
    }

    private function stringOption(Input $input, string $name): string
    {
        $value = $input->getOption($name);
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return array<int, string>
     */
    private function listOption(string|array|bool|null $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map(
            static fn(mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $values,
        )));
    }

    private function positiveInt(string|array|bool|null $value, int $default): int
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        $number = is_numeric($value) ? (int) $value : $default;

        return max(1, min(32, $number));
    }

    private function titleFromName(string $name): string
    {
        $name = preg_replace('/^(cms|external)_/', '', $name) ?? $name;

        return ucwords(str_replace('_', ' ', $name));
    }

    private function oneLine(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return mb_strlen($value) > 180 ? mb_substr($value, 0, 177) . '...' : $value;
    }
}
