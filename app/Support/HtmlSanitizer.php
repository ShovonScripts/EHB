<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Server-side HTML allowlist sanitizer for rich-text bodies
 * (SECURITY.md §6 — never trust the WYSIWYG editor alone).
 *
 * Strips elements outside the allowlist (script, iframe, style, ...),
 * all attributes except a small per-element allowlist, and event
 * handlers / javascript: URLs. UTF-8 safe (Bangla content preserved).
 */
class HtmlSanitizer
{
    /** Elements kept with their children. */
    private const ALLOWED = [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'mark', 'small', 'sub', 'sup',
        'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'blockquote', 'pre', 'code', 'cite', 'q', 'abbr',
        'a', 'img', 'figure', 'figcaption', 'span', 'div',
    ];

    /** Elements removed entirely, including their children. */
    private const DENY_WITH_CHILDREN = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'svg', 'math', 'frame', 'frameset', 'noscript',
        'applet', 'base', 'link', 'meta', 'title', 'head',
    ];

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);

        $doc = new DOMDocument;
        // The XML declaration forces DOMDocument to interpret input as UTF-8
        // (otherwise Bangla text gets mangled).
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__sanitize_root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__sanitize_root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::walk($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        // Snapshot: children may be removed while iterating.
        $children = [];

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, self::DENY_WITH_CHILDREN, true)) {
                    $node->removeChild($child);

                    continue;
                }

                if (! in_array($tag, self::ALLOWED, true)) {
                    // Unknown element: unwrap — keep its children, drop the tag.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }

                    $node->removeChild($child);

                    // Re-walk the promoted children (they were not visited yet
                    // because they lived inside the removed element).
                    self::walk($node);

                    continue;
                }

                self::cleanAttributes($child);

                self::walk($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);

        $allowedAttrs = match ($tag) {
            'a' => ['href', 'title', 'rel', 'target'],
            'img' => ['src', 'alt', 'title', 'width', 'height'],
            'ol' => ['start'],
            default => ['title'],
        };

        $attrs = [];

        foreach ($el->attributes as $attr) {
            $attrs[] = $attr->name;
        }

        foreach ($attrs as $name) {
            $lower = strtolower($name);

            if (str_starts_with($lower, 'on') || ! in_array($lower, $allowedAttrs, true)) {
                $el->removeAttribute($name);

                continue;
            }

            $value = $el->getAttribute($name);

            if (in_array($lower, ['href', 'src'], true)
                && preg_match('/^\s*(javascript|data|vbscript)\s*:/i', $value)) {
                $el->removeAttribute($name);
            }
        }

        if ($tag === 'a') {
            $href = $el->getAttribute('href');

            if ($href !== '' && str_starts_with($href, 'http')) {
                $el->setAttribute('rel', 'noopener noreferrer');
                $el->setAttribute('target', '_blank');
            }
        }
    }
}
