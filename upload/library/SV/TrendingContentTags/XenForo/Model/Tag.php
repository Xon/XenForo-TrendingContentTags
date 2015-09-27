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

    protected $cacheObject = null;
    const sv_trendingTag_cacheId = 'tags_trending';

    public function getTrendingTagCloud($limit, $minActivity, $minCount, $sample_window, $trendingTagProbes = 5)
    {
        if ($this->cacheObject === null)
        {
            $this->cacheObject = XenForo_Application::getCache();
        }
        if ($this->cacheObject)
        {
            if (empty($this->sv_tagTrending_tracking))
            {
                $options = XenForo_Application::getOptions();
                $this->sv_tagTrending_tracking = $options->sv_tagTrending_tracking;
                $this->sv_tagTrending_sampleInterval = $options->sv_tagTrending_sampleInterval * 60;
            }
            $expiry = $this->sv_tagTrending_sampleInterval < 120 ? 120 : intval($this->sv_tagTrending_sampleInterval);

            $raw = $this->cacheObject->load(self::sv_trendingTag_cacheId, true);
            $trendingTags = @unserialize($raw);
            if (!empty($trendingTags))
            {
                return $trendingTags;
            }
        }

        $limitstring = '';
        $limit = intval($limit);
        if ($limit > 0)
        {
            $limitstring = "LIMIT " . $limit;
        }

        $trendingTags = $this->fetchAllKeyed("
            SELECT xf_tag.*, a._activity_count AS activity_count
            FROM
            (
                SELECT tag_id, sum(activity_count) AS _activity_count
                FROM xf_sv_tag_trending
                WHERE stats_date >= ?
                GROUP by tag_id
                HAVING _activity_count >= ?
                ORDER BY _activity_count DESC
                " . $limitstring . "
            ) a
            join xf_tag on xf_tag.tag_id =  a.tag_id
            WHERE xf_tag.use_count >= ?
            ORDER BY tag
        ", 'tag_id', array(XenForo_Application::$time - $sample_window, $minActivity, $minCount));

        if (empty($trendingTags) && $trendingTagProbes > 0)
        {
            $trendingTagProbes -= 1;
            // probe for more stuff
            if ($sample_window < 3600)
            {
                $sample_window = 3600;
            }
            else
            {
                $sample_window = $sample_window * 2;
            }

            $trendingTags = $this->getTrendingTagCloud($limit, $minActivity, $minCount, $sample_window, $trendingTagProbes);
            if (!empty($trendingTagProbes))
            {
                return $trendingTags;
            }
        }

        if ($this->cacheObject && !empty($expiry))
        {
            $raw = serialize($trendingTags);
            $this->cacheObject->save($raw, self::sv_trendingTag_cacheId, array(), $expiry);
        }

        return $trendingTags;
    }

    public function getTrendingTagCloudLevels(array $tags, $levels = 7)
    {
        if (!$tags)
        {
            return array();
        }

        $uses = XenForo_Application::arrayColumn($tags, 'activity_count');
        $min = min($uses);
        $max = max($uses);
        $levelSize = ($max - $min) / $levels;

        $output = array();

        if ($min == $max)
        {
            $middle = ceil($levels / 2);
            foreach ($tags AS $id => $tag)
            {
                $output[$id] = $middle;
            }
        }
        else
        {
            foreach ($tags AS $id => $tag)
            {
                $diffFromMin = $tag['activity_count'] - $min;
                if (!$diffFromMin)
                {
                    $level = 1;
                }
                else
                {
                    $level = min($levels, ceil($diffFromMin / $levelSize));
                }
                $output[$id] = $level;
            }
        }

        return $output;
    }

    public function mergeTags($sourceTagId, $targetTagId)
    {
        parent::mergeTags($sourceTagId, $targetTagId);

        $db = $this->_getDb();

        XenForo_Db::beginTransaction($db);

        $db->query("
            UPDATE xf_sv_tag_trending a, xf_sv_tag_trending b
            SET a.activity_count = a.activity_count + b.activity_count
            WHERE a.tag_id = ? and b.tag_id = ? and a.stats_date = b.stats_date
        ", array($targetTagId, $sourceTagId));

        $db->query("
            UPDATE IGNORE xf_sv_tag_trending
            SET tag_id = ?
            WHERE tag_id = ?
        ", array($targetTagId, $sourceTagId));

        $db->query("
            delete from xf_sv_tag_trending
            WHERE tag_id = ?
        ", array($sourceTagId));

        XenForo_Db::commit($db);
    }
}