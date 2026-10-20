<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use NumberFormatter;

/**
 * Formats a count for the site's language: 3412 → "3,412" (or "3.412"…).
 * Usage in templates: $this->specialBlocksNumber($n)
 */
class SpecialBlocksNumber extends AbstractHelper
{
    public function __invoke(int $n): string
    {
        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter($this->getView()->lang(), NumberFormatter::DECIMAL);
            $out = $formatter->format($n);
            if ($out !== false) {
                return $out;
            }
        }
        return number_format($n);
    }
}
