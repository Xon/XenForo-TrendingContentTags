<?php

class SV_TrendingContentTags_Deferred_CleanUp
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $tagModel = XenForo_Model::create('XenForo_Model_Tag');
        if (method_exists($tagModel, 'summarizeOldTrendingTags'))
        {
            $tagModel->summarizeOldTrendingTags();
        }
        return false;
    }
}