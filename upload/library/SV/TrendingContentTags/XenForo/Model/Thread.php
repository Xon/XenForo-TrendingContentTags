<?php

class SV_TrendingContentTags_XenForo_Model_Thread extends XFCP_SV_TrendingContentTags_XenForo_Model_Thread
{
    public function logThreadView($threadId)
    {
        $this->_getTagModel()->incrementTagActivity('thread', $threadId);
        SV_TrendingContentTags_Globals::$LoggedTagActivity = true;
        parent::logThreadView($threadId);
    }

    protected function _getTagModel()
    {
        return $this->getModelFromCache('XenForo_Model_Tag');
    }
}