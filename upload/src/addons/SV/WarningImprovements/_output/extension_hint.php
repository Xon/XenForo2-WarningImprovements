<?php

// ################## THIS IS A GENERATED FILE ##################
// DO NOT EDIT DIRECTLY. EDIT THE CLASS EXTENSIONS IN THE CONTROL PANEL.

namespace SV\WarningImprovements\XF\Admin\Controller
{
	class XFCP_Warning extends \XF\Admin\Controller\Warning {}
}

namespace SV\WarningImprovements\XF\ControllerPlugin
{
	class XFCP_Warn extends \XF\ControllerPlugin\Warn {}
}

namespace SV\WarningImprovements\XF\Entity
{
	class XFCP_ConversationMaster extends \XF\Entity\ConversationMaster {}
	class XFCP_User extends \XF\Entity\User {}
	class XFCP_UserBan extends \XF\Entity\UserBan {}
	class XFCP_UserChangeTemp extends \XF\Entity\UserChangeTemp {}
	class XFCP_UserOption extends \XF\Entity\UserOption {}
	class XFCP_Warning extends \XF\Entity\Warning {}
	class XFCP_WarningAction extends \XF\Entity\WarningAction {}
	class XFCP_WarningDefinition extends \XF\Entity\WarningDefinition {}
}

namespace SV\WarningImprovements\XF\Pub\Controller
{
	class XFCP_Member extends \XF\Pub\Controller\Member {}
}

namespace SV\WarningImprovements\XF\Pub\View\Member
{
	class XFCP_WarnFill extends \XF\Pub\View\Member\WarnFill {}
}

namespace SV\WarningImprovements\XF\Repository
{
	class XFCP_UserChangeTemp extends \XF\Repository\UserChangeTemp {}
	class XFCP_Warning extends \XF\Repository\Warning {}
}

namespace SV\WarningImprovements\XF\Service\Conversation
{
	class XFCP_Notifier extends \XF\Service\Conversation\Notifier {}
}

namespace SV\WarningImprovements\XF\Service\User
{
	class XFCP_ContentChange extends \XF\Service\User\ContentChange {}
	class XFCP_Warn extends \XF\Service\User\Warn {}
	class XFCP_WarningPointsChange extends \XF\Service\User\WarningPointsChange {}
}