<?php

class SV_TrendingContentTags_XenForo_DataWriter_Tag extends XFCP_SV_TrendingContentTags_XenForo_DataWriter_Tag
{
    protected function _delete()
    {
        parent::_delete();

        $this->_getDb()->query('
            delete into xf_sv_tag_trending where tag_id = ?
        ', $this->get('tag_id'));
    }

    public function delete()
    {
        parent::_delete();

        if ($cacheObject = XenForo_Application::getCache())
        {
            $cacheObject->remove(SV_TrendingContentTags_Globals::sv_trendingTag_cacheId);
        }
    }
}