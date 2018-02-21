<?php

namespace SV\WarningImprovements;

class Listener
{
	public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
	{
		switch ($rule)
		{
			case 'xfmg_media_count':
				if (isset($user->xfmg_media_count) && $user->xfmg_media_count >= $data['media_items'])
				{
					$returnValue = true;
				}
				break;
		}
	}
}