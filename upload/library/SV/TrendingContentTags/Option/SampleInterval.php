<?php

class SV_TrendingContentTags_Option_SampleInterval
{
    public static function verifyOption(&$option, XenForo_DataWriter $dw, $fieldName)
    {
        $tagModel = XenForo_Model::create('XenForo_Model_Tag');
        $tagModel->PersistTrendingTags(true);
        return true;
    }
}