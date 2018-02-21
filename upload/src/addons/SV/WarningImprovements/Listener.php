<?php

namespace SV\WarningImprovements;

class Listener
{
	public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
	{
		switch ($rule)
		{
			case 'warning_points_l': // received at least x points
				break;
			case 'warning_points_m': // received at most x points
				break;
			case 'sv_warning_minimum': // received at least x warnings
				break;
			case 'sv_warning_maximum': // received at most x warnings
				break;
		}
	}
}