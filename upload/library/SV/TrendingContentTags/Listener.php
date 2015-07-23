<?php

class SV_TrendingContentTags_Listener
{
    const AddonNameSpace = 'SV_TrendingContentTags';

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
                CREATE TABLE IF NOT EXISTS xf_tag_sv_trending (
                    `tag_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `stats_date` int(10) unsigned NOT NULL DEFAULT '0',
                    `view_count` int(10) unsigned NOT NULL DEFAULT '0',
                    PRIMARY KEY (`tag_id`,`view_date`)
                ) ENGINE = InnoDB
            ");

            SV_TrendingTags_Install::addColumn("xf_tag", "sv_view_count", "INT UNSIGNED NOT NULL DEFAULT 0");
        }
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');

        $db->query("
            DROP TABLE IF EXISTS `xf_tag_sv_trending`
        ");

        SV_TrendingContentTags_Install::dropColumn("xf_tag", "sv_view_count");

        return true;
    }

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.'_'.$class;
    }
}