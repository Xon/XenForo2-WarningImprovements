<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsDisablingReactionTrait;

class ProfilePostComment extends XFCP_ProfilePostComment implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait;
}