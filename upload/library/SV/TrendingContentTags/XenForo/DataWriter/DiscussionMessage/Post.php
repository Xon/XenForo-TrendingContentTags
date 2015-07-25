<?php

class SV_TrendingContentTags_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_SV_TrendingContentTags_XenForo_DataWriter_DiscussionMessage_Post
{
    protected function _postSaveAfterTransaction()
    {
        if ($this->isInsert())
        {
            $this->_getTagModel()->incrementTagActivity('thread', $this->get('thread_id'));
            SV_TrendingContentTags_Globals::$LoggedTagActivity = true;
        }
        return parent::_postSaveAfterTransaction();
    }

    protected function _getTagModel()
    {
        return $this->getModelFromCache('XenForo_Model_Tag');
    }
}