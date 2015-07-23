<?php

class SV_TrendingContentTags_XenForo_Model_Tag extends XFCP_SV_TrendingContentTags_XenForo_Model_Tag
{
    public function getTrendingTagCloud($limit, $minViews = 1, $time_window = 3600)
    {
        $limitstring = '';
        $limit = intval($limit);
        if ($limit > 0)
        {
            $limitstring = "LIMIT " . $limit;
        }

        return $this->fetchAllKeyed("
            SELECT xf_tag.*, a.total_view_count
            FROM
            (
                SELECT tag_id, sum(view_count) AS total_view_count
                FROM xf_tag_sv_trending
                WHERE stats_date >= ?
                GROUP by tag_id
                HAVING total_view_count >= ?
                ORDER BY total_view_count DESC
                " . $limitstring . "
            ) a
            join xf_tag on xf_tag.tag_id =  a.tag_id
        ", 'tag_id', array(XenForo_Application::$time - $time_window, $minViews));
    }
}