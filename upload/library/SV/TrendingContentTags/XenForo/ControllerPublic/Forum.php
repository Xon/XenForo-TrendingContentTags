<?php

class SV_TrendingContentTags_XenForo_ControllerPublic_Forum extends XFCP_SV_TrendingContentTags_XenForo_ControllerPublic_Forum
{
    public function actionIndex()
    {
        $response = parent::actionIndex();
        if ($response instanceof XenForo_ControllerResponse_View)
        {
            if (XenForo_Application::getOptions()->tagTrending['enabled'])
            {
                $tagModel = $this->_getTagModel();
                $tagCloud = $tagModel->getTrendingTagCloud(XenForo_Application::getOptions()->tagTrending['count'], XenForo_Application::getOptions()->tagTrendingMinViews);
                //$tagCloudLevels = $tagModel->getTagCloudLevels($tagCloud);
            }
            else
            {
                $tagCloud = array();
                //$tagCloudLevels = array();
            }
            $response->params['tagCloud'] = $tagCloud;
            //$response->params['tagCloudLevels'] = $tagCloud;
        }
        return $response;
    }

    /**
     * @return  XenForo_Model_Tag
     */
    protected function _getTagModel()
    {
        return $this->getModelFromCache('XenForo_Model_Tag');
    }
}