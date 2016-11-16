<?php

class SV_TrendingContentTags_Option_SampleInterval
{
    public static function verifyOption(&$option, XenForo_DataWriter $dw, $fieldName)
    {
        $tagModel = XenForo_Model::create('XenForo_Model_Tag');
        // on initial install, our extensions to the XenForo_Model_Tag may not have been implemented
        if (is_callable(array($tagModel, 'PersistTrendingTags')))
        {
            $tagModel->PersistTrendingTags(true);
        }
        return true;
    }
}