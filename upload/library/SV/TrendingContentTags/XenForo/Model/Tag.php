<?php

class SV_TrendingContentTags_XenForo_Model_Tag extends XFCP_SV_TrendingContentTags_XenForo_Model_Tag
{
    protected $sv_tagTrending_tracking = null;
    protected $sv_tagTrending_sampleInterval = null;
    protected $sv_tagTrending_key_expiry = null;

    protected function _setupState()
    {
        $options = XenForo_Application::getOptions();
        $this->sv_tagTrending_tracking = $options->sv_tagTrending_tracking;
        $this->sv_tagTrending_sampleInterval = $options->sv_tagTrending_sampleInterval * 60;
        // cron-task runs every ~5 minutes, ensure the samples will last long enough even if a instance is missed
        $this->sv_tagTrending_key_expiry = min($this->sv_tagTrending_sampleInterval, 11 * 60);
    }

    public function incrementTagActivity($contentType, $contentId, $activity_type)
    {
        if (empty($this->sv_tagTrending_tracking))
        {
            $this->_setupState();
        }
        $supported_activity_type = !empty($this->sv_tagTrending_tracking[$activity_type]);
        $w_activity_type = 'w_'.$activity_type;
        $scaling_factor = isset($this->sv_tagTrending_tracking[$w_activity_type]) && is_numeric($this->sv_tagTrending_tracking[$w_activity_type])
                          ? $this->sv_tagTrending_tracking[$w_activity_type]
                          : 1;

        $tags = null;
        switch($contentType)
        {
            case 'thread':
                if (isset(SV_TrendingContentTags_Globals::$threadTags[$contentId]))
                {
                    $tags = SV_TrendingContentTags_Globals::$threadTags[$contentId];
                }
                break;
            case 'post':
                if (!empty(SV_TrendingContentTags_Globals::$postToThreads[$contentId]))
                {
                    $contentType = 'thread';
                    $contentId = SV_TrendingContentTags_Globals::$postToThreads[$contentId];
                    if (isset(SV_TrendingContentTags_Globals::$threadTags[$contentId]))
                    {
                        $tags = SV_TrendingContentTags_Globals::$threadTags[$contentId];
                    }
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

        if (!$supported_activity_type || empty($tags))
        {
            return false;
        }

        $time = XenForo_Application::$time - (XenForo_Application::$time % $this->sv_tagTrending_sampleInterval);
        if ($this->hasRedis())
        {
            return $this->_incrementTagActivityRedis($contentType, $contentId, $scaling_factor, $time, $tags);
        }
        return $this->_incrementTagActivityDb($contentType, $contentId, $scaling_factor, $time, $tags));
    }

    protected function _incrementTagActivityDb($contentType, $contentId, $scaling_factor, $time, $tags))
    {
        $args = array();
        $sqlArgs = array();
        foreach($tags as $tagId => $tag)
        {
            $args[] = array($tagId, $time, $scaling_factor);
            $foreach[] = '(?,?,?)';
        }
        if (empty($args))
        {
            return false;
        }

        $values = implode(',', $foreach);

        $rows = $this->_getDb()->query('
INSERT INTO xf_sv_tag_trending (tag_id, stats_date, activity_count) values
'.$values.'
ON DUPLICATE KEY UPDATE
    activity_count = activity_count + VALUES(activity_count)
        ', $args);

        return !empty($rows) && $rows->rowCount() > 0;
    }

    protected $credis = null;
    protected function hasRedis()
    {
        if ($this->credis !== null)
        {
            return ($this->credis !== false);
        }
        if ($this->cacheObject === null)
        {
            $this->cacheObject = XenForo_Application::getCache();
        }
        $registry = $this->_getDataRegistryModel();
        if (!method_exists($registry, 'getCredis') || !($credis = $registry->getCredis($this->cacheObject)))
        {
            $this->credis = false;
            return false;
        }
        $this->credis = $credis;
        return true;
    }

    protected function _incrementTagActivityRedis($contentType, $contentId, $scaling_factor, $time, $tags)
    {
        if (empty($tags))
        {
            return false;
        }
        $credis = $this->credis;
        // increment tag counters
        $datakey = Cm_Cache_Backend_Redis::PREFIX_KEY. $this->cacheObject->getOption('cache_id_prefix') . "tags_trending.{$time}";
        $gckey = Cm_Cache_Backend_Redis::PREFIX_KEY. $this->cacheObject->getOption('cache_id_prefix') . "tags_trendingGC";
        foreach($tags as $tagId => $tag)
        {
            $credis->hIncrByFloat($datakey, $tagId, $scaling_factor);
        }
        $credis->expire($datakey, $this->sv_tagTrending_key_expiry);
        // we need to persist the activity to fixed storage after a period of time
        $credis->zadd($gckey, $time, $time);
        $credis->expire($gckey, $this->sv_tagTrending_key_expiry);

        return true;
    }

    protected $cacheObject = null;

    public function PersistTrendingTags($includeCurrent = false)
    {
        if (!$this->hasRedis())
        {
            return;
        }
        if (empty($this->sv_tagTrending_tracking))
        {
            $this->_setupState();
        }

        $credis = $this->credis;
        $gckey = Cm_Cache_Backend_Redis::PREFIX_KEY. $this->cacheObject->getOption('cache_id_prefix') . "tags_trendingGC";
        $datakey = Cm_Cache_Backend_Redis::PREFIX_KEY. $this->cacheObject->getOption('cache_id_prefix') . "tags_trending.";
        $renameKey = Cm_Cache_Backend_Redis::PREFIX_KEY. $this->cacheObject->getOption('cache_id_prefix') . "tags_trendingFrozen.";
        if ($includeCurrent)
        {
            $end = XenForo_Application::$time + 86400;
        }
        else
        {
            $end = XenForo_Application::$time - (XenForo_Application::$time % $this->sv_tagTrending_sampleInterval) - 1;
        }

        $keys = array();
        // get expired buckets
        $timeBuckets = $credis->zRangeByScore($gckey, 0, $end, array('withscores' => true));
        if (!empty($timeBuckets) && is_array($timeBuckets))
        {
            $credis->zRemRangeByScore($gckey, 0, $end);
            // if there are any buckets left, bump the GC's expiry date
            if ($credis->zcard($gckey))
            {
                $credis->expire($gckey, $this->sv_tagTrending_key_expiry);
            }
            // persist the buckets which have been removed by the GC
            foreach($timeBuckets as $timeBucket => $_)
            {
                $oldkey = $datakey.$timeBucket;
                $fullkey = $renameKey.$timeBucket;
                $credis->rename($oldkey, $fullkey);
                // prevent looping forever
                $loopGuard = 100000;
                // find indexes matching the pattern
                $cursor = null;
                do
                {
                    $tags = $credis->hScan($cursor, $fullkey);
                    $loopGuard--;
                    if ($tags === false)
                    {
                        break;
                    }

                    if (is_array($tags))
                    {
                        foreach($tags as $tagId => $activityCount)
                        {
                            // update each tag individually rather than in a large batch
                            $this->_getDb()->query('
                            INSERT INTO xf_sv_tag_trending (tag_id, stats_date, activity_count)
                                values (?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                activity_count = activity_count + VALUES(activity_count)
                            ', array($tagId, $timeBucket, $activityCount));
                        }
                    }
                }
                while($loopGuard > 0 && !empty($cursor));
                // explicitly delete the key we are working on so it doesn't get re-used
                $credis->del($fullkey);
            }
        }
    }

    public function getTrendingTagCloud($limit, $minActivity, $minCount, $sample_window, $trendingTagProbes = 1)
    {
        if (empty($this->sv_tagTrending_tracking))
        {
            $this->_setupState();
        }
        if ($this->cacheObject === null)
        {
            $this->cacheObject = XenForo_Application::getCache();
        }
        if ($this->cacheObject)
        {
            $expiry = $this->sv_tagTrending_sampleInterval < 120 ? 120 : intval($this->sv_tagTrending_sampleInterval);

            $raw = $this->cacheObject->load(SV_TrendingContentTags_Globals::sv_trendingTag_cacheId, true);
            if ($raw !== false)
            {
                $trendingTags = @unserialize($raw);
                if (!empty($trendingTags))
                {
                    return $trendingTags;
                }
            }
        }

        if ($this->hasRedis())
        {
            $this->PersistTrendingTags(true);
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
            $this->cacheObject->save($raw, SV_TrendingContentTags_Globals::sv_trendingTag_cacheId, array(), $this->sv_tagTrending_key_expiry);
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

        if (empty($this->sv_tagTrending_tracking))
        {
            $this->_setupState();
        }
        if ($this->cacheObject === null)
        {
            $this->cacheObject = XenForo_Application::getCache();
        }
        if ($this->hasRedis())
        {
            $this->PersistTrendingTags();
        }

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
            DELETE FROM xf_sv_tag_trending_summary
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
                WHERE stats_date >= ? and stats_date  <= ? and (stats_date - (stats_date % ?)) <> stats_date
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
                DELETE FROM xf_sv_tag_trending_summary
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