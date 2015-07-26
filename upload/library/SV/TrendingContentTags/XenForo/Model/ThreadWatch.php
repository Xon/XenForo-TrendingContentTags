<?php

class SV_TrendingContentTags_XenForo_Model_ThreadWatch extends XFCP_SV_TrendingContentTags_XenForo_Model_ThreadWatch
{
    public function setThreadWatchState($userId, $threadId, $state)
    {
        $result = parent::setThreadWatchState($userId, $threadId, $state);
        if ($result && $state && !SV_TrendingContentTags_Globals::$LoggedTagActivity)
        {
            $this->_getTagModel()->incrementTagActivity('thread', $threadId, SV_TrendingContentTags_Globals::ACTIVITY_TYPE_WATCH);
            SV_TrendingContentTags_Globals::$LoggedTagActivity = true;
        }
        return $result;
    }

    protected function _getTagModel()
    {
        return $this->getModelFromCache('XenForo_Model_Tag');
    }
}