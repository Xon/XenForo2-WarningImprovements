<?php

namespace SV\WarningImprovements\XF\Finder;



/**
 * @extends \XF\Finder\Warning
 */
class Warning extends XFCP_Warning
{
    public function withAgeLimit(int $ageLimitInMonths): self
    {
        if ($ageLimitInMonths <= 0)
        {
            return $this;
        }

        $ageLimitInMonths = \XF::$time - $ageLimitInMonths * 2629746;
        $this->whereOr([
            ['warning_date', '>', $ageLimitInMonths],
            [
                ['points', '>', '0'],
                ['is_expired', '!=', '0'],
            ]
        ]);

        return $this;
    }
}