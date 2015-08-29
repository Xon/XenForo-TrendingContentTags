<?php

class SV_TrendingContentTags_Listener
{
    const AddonNameSpace = 'SV_TrendingContentTags_';

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }
}