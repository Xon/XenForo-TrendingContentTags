<?php

class SV_TrendingContentTags_XenForo_Model_Thread extends XFCP_SV_TrendingContentTags_XenForo_Model_Thread
{
    public function logThreadView($threadId)
    {
        if (!SV_TrendingContentTags_Globals::$LoggedTagActivity)
        {
            $this->_getTagModel()->incrementTagActivity('thread', $threadId, 
                                                        XenForo_Visitor::getInstance()->getUserId() 
                                                        ? SV_TrendingContentTags_Globals::ACTIVITY_TYPE_VIEW_MEMBER 
                                                        : SV_TrendingContentTags_Globals::ACTIVITY_TYPE_VIEW_GUEST);
            SV_TrendingContentTags_Globals::$LoggedTagActivity = true;
        }
        parent::logThreadView($threadId);
    }

    protected function _getTagModel()
    {
        return $this->getModelFromCache('XenForo_Model_Tag');
    }
}