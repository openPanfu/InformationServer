<?php
/**
 * This file is part of openPanfu, a project that imitates the Flex remoting
 * and gameservers of Panfu.
 *
 * @category AMF Service
 * @package com.pandaland.api.service
 * @author Altro50 <altro50@msn.com>
 */

require_once 'Vo/AmfResponse.php';
require_once 'Vo/ListVO.php';

class amfBuddyListService
{

    /**
     * Gets a player's complete buddy list
     *
     * @param int $userId
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public static function getCompleteBuddyList($userId)
    {
        $response = new AmfResponse();
        $response->valueObject = new ListVO();
        $response->valueObject->list = Panfu::getBuddiesVoForUserId($userId);
        return $response;
    }
}