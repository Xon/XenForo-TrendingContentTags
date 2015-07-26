<?php

class SV_TrendingContentTags_XenForo_Model_Tag extends XFCP_SV_TrendingContentTags_XenForo_Model_Tag
{
    protected $sv_tagTrending_tracking = null;
    protected $sv_tagTrending_sampleInterval = null;

    public function incrementTagActivity($contentType, $contentId, $activity_type)
    {
        if (empty($this->sv_tagTrending_tracking))
        {
            $options = XenForo_Application::getOptions();
            $this->sv_tagTrending_tracking = $options->sv_tagTrending_tracking;
            $this->sv_tagTrending_sampleInterval = $options->sv_tagTrending_sampleInterval * 60;
        }
        $supported_activity_type = !empty($this->sv_tagTrending_tracking[$activity_type]);
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
                    $supported_activity_type = false;
                }
                break;
            default:
                $supported_activity_type = false;
                break;
        }

        if (!$supported_activity_type)
        {
            return false;
        }

        $tags = $this->getTagsForContent($contentType, $contentId);
        if (empty($tags))
        {
            return false;
        }

        $time = XenForo_Application::$time - (XenForo_Application::$time % $this->sv_tagTrending_sampleInterval);
        foreach($tags as $tag)
        {
            $this->_getDb()->query('
                insert into xf_sv_tag_trending (tag_id, stats_date, activity_count)
                values (?, ?, 1)
                on duplicate key update
                    activity_count = activity_count + 1
            ', array($tag['tag_id'], $time));
        }

        return true;
    }


    public function getTrendingTagCloud($limit, $minActivity, $sample_window)
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
                FROM xf_sv_tag_trending
                WHERE stats_date >= ?
                GROUP by tag_id
                HAVING total_activity_count >= ?
                ORDER BY total_activity_count DESC
                " . $limitstring . "
            ) a
            join xf_tag on xf_tag.tag_id =  a.tag_id
        ", 'tag_id', array(XenForo_Application::$time - $sample_window, $minActivity));
    }
}