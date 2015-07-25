<?php

class SV_TrendingContentTags_XenForo_Model_Like extends XFCP_SV_TrendingContentTags_XenForo_Model_Like
{
    public function likeContent($contentType, $contentId, $contentUserId, $likeUserId = null, $likeDate = null)
    {
        $latestLikeUsers = parent::likeContent($contentType, $contentId, $contentUserId, $likeUserId, $likeDate);
        if ($latestLikeUsers && !SV_TrendingContentTags_Globals::$LoggedTagActivity)
        {
            $this->_getTagModel()->incrementTagActivity($contentType, $contentId);
            SV_TrendingContentTags_Globals::$LoggedTagActivity = true;
        }
        return ($latestLikeUsers);
    }

    protected function _getTagModel()
    {
        return $this->getModelFromCache('XenForo_Model_Tag');
    }
}