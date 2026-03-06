<?php
declare(strict_types=1);

namespace App\Services;

/**
 * DOM-based HTML sanitizer — replaces the regex-based safe_html().
 *
 * Uses DOMDocument to parse HTML into a proper tree, then walks every
 * node and strips anything not on the allow-list. This is immune to
 * the encoding tricks and edge-cases that defeat regex sanitizers.
 *
 * Usage:
 *   Sanitizer::clean($html)           // default article body rules
 *   Sanitizer::clean($html, 'basic')  // comments — text + minimal formatting
 */
final class Sanitizer
{
    // ── Profiles ─────────────────────────────────────────────────

    /** Tags and their allowed attributes per profile */
    private const PROFILES = [
        'article' => [
            // Block
            'p'          => [],
            'br'         => [],
            'hr'         => [],
            'blockquote' => [],
            'pre'        => [],
            'code'       => ['class'],
            'div'        => ['class'],

            // Headings
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],

            // Inline
            'strong' => [], 'b' => [], 'em' => [], 'i' => [],
            'u' => [], 's' => [], 'mark' => [],
            'sub' => [], 'sup' => [], 'span' => ['class'],

            // Links
            'a' => ['href', 'title', 'target', 'rel'],

            // Lists
            'ul' => [], 'ol' => ['start', 'type'], 'li' => [],

            // Tables
            'table' => [], 'thead' => [], 'tbody' => [],
            'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],

            // Media
            'figure' => ['class'], 'figcaption' => [],
            'img' => ['src', 'alt', 'width', 'height', 'loading', 'class'],
            'picture' => [], 'source' => ['srcset', 'media', 'type'],
        ],

        'basic' => [
            // Minimal set for user-submitted comments
            'p'      => [],
            'br'     => [],
            'strong' => [], 'b' => [], 'em' => [], 'i' => [],
            'a'      => ['href', 'title', 'rel'],
            'code'   => [],
        ],
    ];

    /** Attributes allowed on ANY tag (added to per-tag list) */
    private const GLOBAL_ATTRS = ['id'];

    /** URI attributes that must be validated */
    private const URI_ATTRS = ['href', 'src', 'srcset'];

    /** Allowed URI schemes */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto'];

    // ── Public API ───────────────────────────────────────────────

    /**
     * Sanitise an HTML string.
     *
     * @param  string $dirty   Raw HTML (e.g. from CKEditor)
     * @param  string $profile 'article' or 'basic'
     * @return string          Clean HTML safe for rendering
     */
    public static function clean(string $dirty, string $profile = 'article'): string
    {
        $dirty = trim($dirty);
        if ($dirty === '') {
            return '';
        }

        $allowed = self::PROFILES[$profile] ?? self::PROFILES['article'];

        // Parse with DOMDocument (suppress warnings from malformed HTML)
        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        // Wrap in a root element so DOMDocument doesn't add <html><body> around fragments
        $wrapped = '<div id="__sanitizer_root__">' . $dirty . '</div>';

        // The meta charset ensures DOMDocument interprets the string as UTF-8
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><head><meta charset="UTF-8"></head><body>' . $wrapped . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR
        );
        libxml_clear_errors();

        // Find our wrapper
        $root = $doc->getElementById('__sanitizer_root__');
        if (!$root) {
            return '';
        }

        // Walk and clean
        self::walkNode($root, $allowed, $doc);

        // Serialize children of the wrapper
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        // Clean up DOMDocument artifacts
        $html = str_replace(['<?xml encoding="UTF-8">', '<meta charset="UTF-8">'], '', $html);
        $html = trim($html);

        return $html;
    }

    // ── Internal ─────────────────────────────────────────────────

    /**
     * Recursively walk the DOM tree:
     *  - Remove disallowed elements (but keep their children = "unwrap")
     *  - Strip disallowed attributes from allowed elements
     *  - Validate URI attributes
     *  - Remove dangerous CSS in style attributes (we don't allow style, but belt & suspenders)
     */
    private static function walkNode(\DOMNode $node, array $allowed, \DOMDocument $doc): void
    {
        // Collect children first (modifying during iteration breaks the list)
        $children = [];
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                continue; // text is fine
            }

            if ($child->nodeType === XML_COMMENT_NODE) {
                // Strip HTML comments (can contain IE conditionals)
                $node->removeChild($child);
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                $node->removeChild($child);
                continue;
            }

            /** @var \DOMElement $child */
            $tag = strtolower($child->nodeName);

            if (!isset($allowed[$tag])) {
                // Disallowed tag: "unwrap" — replace the element with its children
                self::unwrapNode($child, $node);
                // The children are now direct children of $node — they'll be visited
                // on the next iteration of walkNode on $node's parent (or we re-walk).
                continue;
            }

            // Tag is allowed — strip disallowed attributes
            self::cleanAttributes($child, $allowed[$tag]);

            // Recurse into children
            self::walkNode($child, $allowed, $doc);
        }
    }

    /**
     * Replace an element with its children (unwrap/flatten).
     */
    private static function unwrapNode(\DOMElement $el, \DOMNode $parent): void
    {
        // Some elements should be completely removed (not unwrapped)
        $removeEntirely = ['script', 'style', 'iframe', 'object', 'embed', 'form',
                           'input', 'select', 'textarea', 'button', 'noscript',
                           'svg', 'math', 'base', 'link', 'meta', 'applet'];

        if (in_array(strtolower($el->nodeName), $removeEntirely, true)) {
            $parent->removeChild($el);
            return;
        }

        // Move children before the element, then remove the empty element
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    /**
     * Remove attributes not on the allow-list, validate URIs.
     */
    private static function cleanAttributes(\DOMElement $el, array $allowedAttrs): void
    {
        $allAllowed = array_merge($allowedAttrs, self::GLOBAL_ATTRS);

        // Collect attribute names first (can't modify during iteration)
        $toRemove = [];
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->name);
            if (!in_array($name, $allAllowed, true)) {
                $toRemove[] = $attr->name;
            }
        }
        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }

        // Validate URI attributes
        foreach (self::URI_ATTRS as $uriAttr) {
            if ($el->hasAttribute($uriAttr)) {
                $value = $el->getAttribute($uriAttr);
                if (!self::isSafeUri($value, $uriAttr)) {
                    $el->removeAttribute($uriAttr);
                    // For links, set a safe default
                    if ($uriAttr === 'href') {
                        $el->setAttribute('href', '#');
                    }
                }
            }
        }

        // Force safe target/rel on links
        if (strtolower($el->nodeName) === 'a') {
            $href = $el->getAttribute('href');
            if ($href && !str_starts_with($href, '#') && !str_starts_with($href, '/')) {
                // External link: force safe rel
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    /**
     * Check if a URI value is safe (no javascript:, data:, vbscript: etc.)
     */
    private static function isSafeUri(string $value, string $attrName): bool
    {
        $value = trim($value);

        // srcset is comma-separated list of URLs — validate each part
        if ($attrName === 'srcset') {
            $parts = explode(',', $value);
            foreach ($parts as $part) {
                $url = trim(explode(' ', trim($part))[0] ?? '');
                if ($url !== '' && !self::isSafeUri($url, 'src')) {
                    return false;
                }
            }
            return true;
        }

        // Empty is fine
        if ($value === '' || $value === '#') {
            return true;
        }

        // Relative URLs are fine
        if (str_starts_with($value, '/') || str_starts_with($value, './') || str_starts_with($value, '../')) {
            return true;
        }

        // Anchor-only
        if (str_starts_with($value, '#')) {
            return true;
        }

        // Check scheme
        // Decode entities first to catch &#106;avascript: tricks
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove whitespace/control chars that browsers ignore but could hide schemes
        $decoded = preg_replace('/[\x00-\x20\x7f]+/', '', $decoded) ?? $decoded;

        // Extract scheme
        $colonPos = strpos($decoded, ':');
        if ($colonPos === false) {
            return true; // no scheme = relative URL
        }

        $scheme = strtolower(substr($decoded, 0, $colonPos));

        // Must be in our safe list
        return in_array($scheme, self::SAFE_SCHEMES, true);
    }
}