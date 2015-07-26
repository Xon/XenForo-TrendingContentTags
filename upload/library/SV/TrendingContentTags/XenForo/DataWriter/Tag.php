<?php

class SV_TrendingContentTags_XenForo_DataWriter_Tag extends XFCP_SV_TrendingContentTags_XenForo_DataWriter_Tag
{
    protected function _getFields()
    {
        $fields = parent::_getFields();
        $fields['xf_tag']['sv_activity_count'] = array('type' => self::TYPE_UINT_FORCED, 'default' => 0);
        return $fields;
    }
}