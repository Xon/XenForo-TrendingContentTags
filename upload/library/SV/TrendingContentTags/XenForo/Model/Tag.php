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
        $w_activity_type = 'w_'.$activity_type;
        $scaling_factor = isset($this->sv_tagTrending_tracking[$w_activity_type]) && is_numeric($this->sv_tagTrending_tracking[$w_activity_type])
                          ? $this->sv_tagTrending_tracking[$w_activity_type]
                          : 1;

        switch($contentType)
        {
            case 'thread':
                break;
            case 'post':
                if (!empty(SV_TrendingContentTags_Globals::$postToThreads[$contentId]))
                {
                    $contentType = 'thread';
                    $contentId = SV_TrendingContentTags_Globals::$postToThreads[$contentId];
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

        $time = XenForo_Application::$time - (XenForo_Application::$time % $this->sv_tagTrending_sampleInterval);
        $rows = $this->_getDb()->query('
INSERT INTO xf_sv_tag_trending (tag_id, stats_date, activity_count)
    SELECT tag_id, ?, ?
    FROM xf_tag_content AS tag_content
    WHERE tag_content.content_type = ? AND tag_content.content_id = ?
ON DUPLICATE KEY UPDATE
    activity_count = activity_count + VALUES(activity_count)
        ', array($time, $scaling_factor, $contentType, $contentId));

        return !empty($rows) && $rows->rowCount() > 0;
    }

    protected $cacheObject = null;

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

            $raw = $this->cacheObject->load(SV_TrendingContentTags_Globals::sv_trendingTag_cacheId, true);
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
            $this->cacheObject->save($raw, SV_TrendingContentTags_Globals::sv_trendingTag_cacheId, array(), $expiry);
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


    public function summarizeOldTrendingTags()
    {
        $db = $this->_getDb();

        $options = XenForo_Application::getOptions();
        $summarizeAfter = $options->sv_tagTrending_summarizeAfter * 60*60;
        $summarizeInterval = $options->sv_tagTrending_summarizeInterval * 60*60;
        $summarizeLimit = $options->sv_tagTrending_summarizeLimit * 60*60;
        if (empty($summarizeAfter))
        {
            return;
        }

        if ($this->cacheObject === null)
        {
            $this->cacheObject = XenForo_Application::getCache();
        }
        $checkpoint = 0;
        if ($this->cacheObject)
        {
            $checkpoint = 0 + $this->cacheObject->load(SV_TrendingContentTags_Globals::sv_trendingTag_summarize_checkpoint_cacheId);
        }

        if ($checkpoint)
        {
            $checkpoint = $checkpoint - ($checkpoint % $summarizeInterval);
        }
        $summarizeTime = XenForo_Application::$time - $summarizeAfter;
        $summarizeTime = $summarizeTime - ($summarizeTime % $summarizeInterval);
        $max = $summarizeTime;

        $db->query("
            TRUNCATE TABLE xf_sv_tag_trending_summary;
        ");

        if (empty($summarizeLimit) || !is_numeric($summarizeLimit))
        {
            $summarizeLimit = 10000;
        }
        $limit = "limit $summarizeLimit";

        XenForo_Db::beginTransaction($db);

        // build summarize list
        $stmt = $db->query("
            INSERT INTO xf_sv_tag_trending_summary (tag_id, stats_date, activity_count)
                SELECT tag_id, (stats_date - (stats_date % ?)), activity_count
                FROM xf_sv_tag_trending
                WHERE stats_date >= ? and stats_date  < ? and (stats_date - (stats_date % ?)) <> stats_date
                {$limit}
            ON DUPLICATE KEY UPDATE
                xf_sv_tag_trending_summary.activity_count = xf_sv_tag_trending_summary.activity_count + VALUES(xf_sv_tag_trending_summary.activity_count);
        ", array($summarizeInterval, $checkpoint, $summarizeTime, $summarizeInterval));

        if ($stmt->rowCount() > 0)
        {
            // determine how much data is to be manipulated
            $min = $db->fetchOne("
                SELECT MIN(stats_date)
                FROM xf_sv_tag_trending_summary;
            ");
            $max = $db->fetchOne("
                SELECT MAX(stats_date)
                FROM xf_sv_tag_trending_summary;
            ");

            // delete non-summerized rows
            $db->query("
                DELETE
                FROM xf_sv_tag_trending
                WHERE stats_date >= ? and stats_date  <= ? and (stats_date - (stats_date % ?)) <> stats_date
            ", array($min, $max, $summarizeInterval));

            // populate trending tags table with summarized rows
            $db->query("
                INSERT INTO xf_sv_tag_trending (tag_id, stats_date, activity_count)
                    SELECT tag_id, stats_date, activity_count
                    FROM xf_sv_tag_trending_summary
                ON DUPLICATE KEY UPDATE
                    xf_sv_tag_trending.activity_count = xf_sv_tag_trending.activity_count + VALUES(xf_sv_tag_trending.activity_count);
            ");

            // cleanup the summary table
            $db->query("
                DELETE FROM xf_sv_tag_trending_summary;
            ");
        }

        XenForo_Db::commit($db);

        if ($max < $summarizeTime)
        {
            XenForo_Application::defer('SV_TrendingContentTags_Deferred_CleanUp', array(), null, true);
            $summarizeTime = $max;
        }

        if ($this->cacheObject)
        {
            $checkpoint = $this->cacheObject->save(''.$summarizeTime, SV_TrendingContentTags_Globals::sv_trendingTag_summarize_checkpoint_cacheId, array(), 86400);
        }
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

        if ($this->cacheObject === null)
        {
            $this->cacheObject = XenForo_Application::getCache();
        }
        if ($this->cacheObject)
        {
            $this->cacheObject->remove(SV_TrendingContentTags_Globals::sv_trendingTag_cacheId);
        }
    }
}