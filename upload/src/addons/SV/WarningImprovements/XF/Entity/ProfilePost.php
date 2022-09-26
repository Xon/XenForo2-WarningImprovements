<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsDisablingReactionTrait;

class ProfilePost extends XFCP_ProfilePost implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait;
}