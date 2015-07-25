<?php

class SV_TrendingContentTags_Dark_PostRating_Model extends XFCP_SV_TrendingContentTags_Dark_PostRating_Model
{
    public function ratePost(array $post, $user_id, $rating, $ignoreExistingLike = false)
    {
        $result = parent::ratePost($post, $user_id, $rating, $ignoreExistingLike);
        if ($result && !SV_TrendingContentTags_Globals::$LoggedTagActivity)
        {
            $this->_getTagModel()->incrementTagActivity('thread', $post['thread_id']);
            SV_TrendingContentTags_Globals::$LoggedTagActivity = true;
        }
        return $result;
    }

    protected function _getTagModel()
    {
        return $this->getModelFromCache('XenForo_Model_Tag');
    }
}