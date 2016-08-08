<?php

class SV_TrendingContentTags_XenForo_ControllerHelper_ForumThreadPost extends XFCP_SV_TrendingContentTags_XenForo_ControllerHelper_ForumThreadPost
{
    public function assertPostValidAndViewable($postId, array $postFetchOptions = array(), array $threadFetchOptions = array(), array $forumFetchOptions = array())
    {
        $response = parent::assertPostValidAndViewable($postId, $postFetchOptions, $threadFetchOptions, $forumFetchOptions);

        if (!empty($response[1]['thread_id']))
        {
            $threadId = 
            SV_TrendingContentTags_Globals::$postToThreads[$postId] = $response[1]['thread_id'];
        }

        return $response;
    }

    public function assertThreadValidAndViewable($threadId, array $threadFetchOptions = array(), array $forumFetchOptions = array())
    {
        $response = parent::assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

        if (!empty($response[0]['thread_id']))
        {
            $threadId = $response[0]['thread_id'];
            SV_TrendingContentTags_Globals::$threadTags[$threadId] = isset($response[0]['tagsList']) ? $response[0]['tagsList'] : array();
        }

        return $response;
    }
}