<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\WarningCategory as WarningCategoryEntity;
use XF\Entity\Forum as ForumEntity;
use XF\Entity\Thread as ThreadEntity;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\WarningAction
 * COLUMNS
 * @property int|null                    $sv_post_node_id
 * @property int|null                    $sv_post_thread_id
 * @property int|null                    $sv_post_as_user_id
 * @property int|null                    $sv_warning_category_id
 * GETTERS
 * @property string                      $title
 * RELATIONS
 * @property-read ?WarningCategoryEntity $Category
 * @property-read ?ForumEntity           $PostForum
 * @property-read ?ThreadEntity          $PostThread
 * @property-read ?User                  $PostAsUser
 */
class WarningAction extends XFCP_WarningAction
{
    /**
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function getTitle()
    {
        return \XF::phrase('svWarningPoints:') . ' ' . $this->points;
    }

    /**
     * @param int|null $value
     * @param string   $key
     * @param string   $type
     * @param array    $columnOptions
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function svVerifyNullableInt(&$value, string $key, string $type, array $columnOptions): bool
    {
        $value = (int)$value;
        if ($value === 0)
        {
            $value = null;
        }

        return true;
    }

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['action_length_type']['allowedValues'][] = 'hours';
        $structure->columns['sv_post_node_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true, 'verify' => 'svVerifyNullableInt'];
        $structure->columns['sv_post_thread_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true, 'verify' => 'svVerifyNullableInt'];
        $structure->columns['sv_post_as_user_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true, 'verify' => 'svVerifyNullableInt'];
        $structure->columns['sv_warning_category_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true, 'verify' => 'svVerifyNullableInt'];

        $structure->relations['Category'] = [
            'entity'     => 'SV\WarningImprovements:WarningDefault',
            'type'       => self::TO_ONE,
            'conditions' => 'sv_warning_category_id',
            'primary'    => true,
        ];

        $structure->relations['PostForum'] = [
            'entity'     => 'XF:Forum',
            'type'       => self::TO_ONE,
            'conditions' => 'sv_post_node_id',
            'primary'    => true,
        ];

        $structure->relations['PostThread'] = [
            'entity'     => 'XF:Thread',
            'type'       => self::TO_ONE,
            'conditions' => 'sv_post_thread_id',
            'primary'    => true,
        ];

        $structure->relations['PostAsUser'] = [
            'entity'     => 'XF:User',
            'type'       => self::TO_ONE,
            'conditions' => 'sv_post_as_user_id',
            'primary'    => true,
        ];

        if (\XF::$versionId < 2010500)
        {
            $structure->getters['title'] = true;
        }

        return $structure;
    }
}
