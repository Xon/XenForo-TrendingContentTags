<?php

// This class is used to encapsulate global state between layers without using $GLOBAL[] or
// relying on the consumer being loaded correctly by the dynamic class autoloader
class SV_TrendingContentTags_Globals
{
    public static $LoggedTagActivity = false;
    public static $postToThreads = array();

    const ACTIVITY_TYPE_LIKE = 'like';
    const ACTIVITY_TYPE_VIEW_GUEST = 'view_guest';
    const ACTIVITY_TYPE_VIEW_MEMBER = 'view_member';
    const ACTIVITY_TYPE_REPLY = 'reply';
    const ACTIVITY_TYPE_WATCH = 'watch';

    private function __construct() {}
}