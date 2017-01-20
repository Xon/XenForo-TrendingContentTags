<?php

class SV_TrendingContentTags_Deferred_CleanUp extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        if (!empty($data['persist']))
        {
            $tagModel = XenForo_Model::create('XenForo_Model_Tag');
            if (method_exists($tagModel, 'PersistTrendingTags'))
            {
                $tagModel->PersistTrendingTags(!empty($data['persist_current']));
            }
        }
        $tagModel = XenForo_Model::create('XenForo_Model_Tag');
        if (method_exists($tagModel, 'summarizeOldTrendingTags'))
        {
            $tagModel->summarizeOldTrendingTags();
        }
        return false;
    }
}