<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsDisablingReactionTrait;

class Post extends XFCP_Post implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait;
}