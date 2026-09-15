<?php

declare(strict_types=1);

namespace Naf\CMS\Security;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

use function Naf\View\s;

/**
 * Sanitizes the small Editor.js inline-formatting subset used by articles.
 */
final class InlineHtmlSanitizer
{
    /** @var array<string, true> */
    private const ALLOWED_TAGS = [
        'a'      => true,
        'b'      => true,
        'br'     => true,
        'code'   => true,
        'em'     => true,
        'i'      => true,
        'mark'   => true,
        'strong' => true,
        'u'      => true,
    ];

    public function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="nafcms-inline-root">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->getElementById('nafcms-inline-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $this->sanitizeNode($child);
        }

        return $output;
    }

    private function sanitizeNode(DOMNode $node): string
    {
        if ($node instanceof DOMComment) {
            return '';
        }

        if ($node instanceof DOMText) {
            return (string) s($node->nodeValue);
        }

        if (!$node instanceof DOMElement) {
            return $this->sanitizeChildren($node);
        }

        $tag = strtolower($node->tagName);
        if (!isset(self::ALLOWED_TAGS[$tag])) {
            return $this->sanitizeChildren($node);
        }

        $content = $this->sanitizeChildren($node);
        if ($tag === 'br') {
            return '<br>';
        }

        if ($tag !== 'a') {
            return '<' . $tag . '>' . $content . '</' . $tag . '>';
        }

        $href        = $tag === 'a' ? UrlPolicy::link($node->getAttribute('href')) : null;
        $title       = trim($node->getAttribute('title'));
        $targetBlank = strtolower(trim($node->getAttribute('target'))) === '_blank';
        $attributes  = '';

        if ($href !== null) {
            $attributes .= ' href="' . s($href) . '"';
        }
        if ($title !== '') {
            $attributes .= ' title="' . s($title) . '"';
        }
        if ($href !== null && $targetBlank) {
            $attributes .= ' target="_blank" rel="noopener noreferrer"';
        }

        return '<a' . $attributes . '>' . $content . '</a>';
    }

    private function sanitizeChildren(DOMNode $node): string
    {
        $output = '';
        foreach ($node->childNodes as $child) {
            $output .= $this->sanitizeNode($child);
        }

        return $output;
    }
}
