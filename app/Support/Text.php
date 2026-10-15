<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Safe rendering of user-written text: escaped, with @mentions highlighted and links clickable.
 */
class Text
{
    public static function render(?string $text): HtmlString
    {
        $html = e((string) $text);

        $html = preg_replace(
            '~\bhttps?://[^\s<>"\']+~i',
            '<a href="$0" target="_blank" rel="noopener noreferrer nofollow" class="text-indigo-600 underline decoration-indigo-300 hover:text-indigo-500 dark:text-indigo-400">$0</a>',
            $html,
        ) ?? $html;

        $html = preg_replace(
            '/(?<![\w.\/])@([a-z0-9][a-z0-9._-]*[a-z0-9]|[a-z0-9])/i',
            '<span class="mention">@$1</span>',
            $html,
        ) ?? $html;

        return new HtmlString($html);
    }
}
