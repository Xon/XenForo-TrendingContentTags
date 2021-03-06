<?php

class SV_TrendingContentTags_CronEntry_CleanUp
{
	public static function persistTrendingTags()
	{
        $tagModel = XenForo_Model::create('XenForo_Model_Tag');
        if (method_exists($tagModel, 'PersistTrendingTags'))
        {
            $tagModel->PersistTrendingTags();
        }
    }

	public static function runOldTagsCleanUp()
	{
        $tagModel = XenForo_Model::create('XenForo_Model_Tag');
        if (method_exists($tagModel, 'summarizeOldTrendingTags'))
        {
            $tagModel->summarizeOldTrendingTags();
        }
    }
}