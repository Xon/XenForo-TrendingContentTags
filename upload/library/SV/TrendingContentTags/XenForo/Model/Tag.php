<?php

class SV_TrendingContentTags_XenForo_Model_Tag extends XFCP_SV_TrendingContentTags_XenForo_Model_Tag
{
    public function incrementTagActivity($contentType, $contentId, $activity_type)
    {
        $tracking = XenForo_Application::getOptions()->sv_tagTrending_tracking;
        $supported_activity_type = !empty($tracking[$activity_type]);
        //$scaling_factor = 1.0;

        switch($contentType)
        {
            case 'thread':
                break;
            case 'post':
                if (!empty(SV_TrendingContentTags_Globals::$postToThreads[$contentId]))
                {
                    $contentType = 'thread';
                    $threadId = SV_TrendingContentTags_Globals::$postToThreads[$contentId];
                }
                else
                {
                    $contentType = '';
                }
                break;
            default:
                $contentType = '';
                break;
        }

        if (!$supported_activity_type && empty($contentType))
        {
            return false;
        }

        $tags = $this->getTagsForContent($contentType, $contentId);
        // nearest 15 minutes
        $time = XenForo_Application::$time - (XenForo_Application::$time % 900);
        foreach($tags as $tag)
        {
            $this->_getDb()->query('
                insert into xf_tag_sv_trending (tag_id, stats_date, activity_count)
                values (?, ?, 1)
                on duplicate key update
                    activity_count = activity_count + 1
            ', array($tag['tag_id'], $time));
        }
    }


    public function getTrendingTagCloud($limit, $minActivity = 1, $time_window = 3600)
    {
        $limitstring = '';
        $limit = intval($limit);
        if ($limit > 0)
        {
            $limitstring = "LIMIT " . $limit;
        }

        return $this->fetchAllKeyed("
            SELECT xf_tag.*, a.total_activity_count
            FROM
            (
                SELECT tag_id, sum(activity_count) AS total_activity_count
                FROM xf_tag_sv_trending
                WHERE stats_date >= ?
                GROUP by tag_id
                HAVING total_activity_count >= ?
                ORDER BY total_activity_count DESC
                " . $limitstring . "
            ) a
            join xf_tag on xf_tag.tag_id =  a.tag_id
        ", 'tag_id', array(XenForo_Application::$time - $time_window, $minActivity));
    }
}