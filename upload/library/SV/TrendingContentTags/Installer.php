<?php

class SV_TrendingContentTags_Installer
{
    public static function install($existingAddOn, array $addOnData, SimpleXMLElement $xml)
    {
        if ($xml && XenForo_Application::$versionId < 1050033)
        {
            throw new XenForo_Exception("Minimum supported version is XF 1.5.0 Beta 3");
        }

        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

        $db = XenForo_Application::getDb();

        if ($version == 0)
        {
            $db->query("
                CREATE TABLE IF NOT EXISTS xf_sv_tag_trending (
                    `tag_id` int(10) unsigned NOT NULL,
                    `stats_date` int(10) unsigned NOT NULL DEFAULT '0',
                    `activity_count` float NOT NULL DEFAULT '0',
                    PRIMARY KEY (`stats_date`,`tag_id`)
                ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
            ");

/*
            $db->query("
                insert into xf_sv_tag_trending (tag_id, stats_date, activity_count)
                    select xf_tag_content.tag_id, (xf_post.post_date - xf_post.post_date % 900) as stats_date, count(xf_post.post_id)
                    from xf_tag_content
                    join xf_post on xf_post.thread_id = xf_tag_content.content_id
                    where xf_tag_content.content_type = 'thread'
                    group by xf_tag_content.tag_id, stats_date
                on duplicate key update
                    activity_count = activity_count + values(activity_count)
            ");
*/
/*
            $db->query("
                insert into xf_sv_tag_trending (tag_id, stats_date, activity_count)
                    select xf_tag_content.tag_id, (xf_liked_content.like_date - xf_liked_content.like_date % 900) as stats_date, count(xf_liked_content.like_id)
                    from xf_tag_content
                    join xf_post on xf_post.thread_id = xf_tag_content.content_id
                    join xf_liked_content on xf_liked_content.content_id = xf_post.post_id and xf_liked_content.content_id = 'post'
                    where xf_tag_content.content_type = 'thread'
                    group by xf_tag_content.tag_id, stats_date
                 on duplicate key update
                    activity_count = activity_count + values(activity_count)
            ");
*/
        }

        if ($version == 0 || $version < 1000000)
        {
            $db->query("
                CREATE TABLE IF NOT EXISTS xf_sv_tag_trending_summary (
                    `tag_id` int(10) unsigned NOT NULL,
                    `stats_date` int(10) unsigned NOT NULL DEFAULT '0',
                    `activity_count` float NOT NULL DEFAULT '0',
                    PRIMARY KEY (`stats_date`,`tag_id`)
                ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
            ");
        }

        SV_Utils_Install::dropColumn("xf_tag", "sv_activity_count");
        SV_Utils_Install::modifyColumn("xf_sv_tag_trending", "activity_count", 'int(10) unsigned', 'float');

        XenForo_Application::defer('SV_TrendingContentTags_Deferred_CleanUp', array(), null, true);
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');

        $db->query("
            DROP TABLE IF EXISTS `xf_sv_tag_trending`
        ");

        $db->query("
            DROP TABLE IF EXISTS `xf_sv_tag_trending_summary`
        ");

        SV_Utils_Install::dropColumn("xf_tag", "sv_activity_count");

        return true;
    }
}