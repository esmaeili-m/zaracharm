<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * پاک‌سازی HTML محتوای ویرایشگر (توضیحات محصول، مقاله، کمپین، ...) قبل از چاپ با {!! !!}
 * فهرست سفید تگ و ویژگی؛ اسکریپت، رویدادهای on* و آدرس‌های javascript: حذف می‌شوند.
 * قالب‌بندی ویرایشگر (تیتر، لیست، لینک، تصویر، جدول، رنگ و کلاس‌های ql-*) حفظ می‌شود.
 */
class SafeHtml
{
    protected const TAGS = [
        'p', 'br', 'hr', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'mark', 'small', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
    ];

    // تگ‌هایی که با محتوایشان حذف می‌شوند
    protected const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'base'];

    protected const ATTRIBUTES = ['href', 'src', 'alt', 'title', 'class', 'style', 'width', 'height', 'target', 'rel', 'dir', 'colspan', 'rowspan', 'align', 'data-list'];

    public static function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div id="__safe_root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__safe_root');

        if (! $root) {
            return e(strip_tags($html));
        }

        static::sanitizeChildren($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    protected static function sanitizeChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            static::sanitizeChildren($child);

            if (! in_array($tag, self::TAGS, true)) {
                // تگ ناشناخته حذف، ولی متن داخلش حفظ می‌شود
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);

                $keep = in_array($name, self::ATTRIBUTES, true);

                if ($keep && in_array($name, ['href', 'src'], true)) {
                    $keep = static::safeUrl($value, $tag === 'img' && $name === 'src');
                }

                if ($keep && $name === 'style') {
                    $keep = ! preg_match('/expression\s*\(|javascript:|vbscript:|url\s*\(|@import|behavior\s*:/i', $value);
                }

                if (! $keep) {
                    $child->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a' && strtolower($child->getAttribute('target')) === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    protected static function safeUrl(string $url, bool $allowDataImage): bool
    {
        // کاراکترهای کنترلی/فاصله داخل scheme (مثل "java\tscript:") حذف
        $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if ($normalized === '' || str_starts_with($normalized, '#') || str_starts_with($normalized, '/') || str_starts_with($normalized, '?')) {
            return true;
        }

        if (preg_match('#^(https?:|mailto:|tel:)#', $normalized)) {
            return true;
        }

        if ($allowDataImage && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#', $normalized)) {
            return true;
        }

        // بدون scheme (مسیر نسبی) مجاز؛ هر scheme دیگری (javascript:, data:, vbscript:) رد
        return ! preg_match('#^[a-z][a-z0-9+.\-]*:#', $normalized);
    }
}
