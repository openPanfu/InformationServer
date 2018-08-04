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
require_once 'Vo/UserActionDailyVO.php';

class amfActionService 
{
    public function getLastDoneActionToday($id, $action, $time)
    {
        //TODO: Implement.
        $response = new AmfResponse();
        $response->valueObject = new UserActionDailyVO();
        return $response;
    }
}