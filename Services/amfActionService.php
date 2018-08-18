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
        if(!Panfu::isLoggedIn())
            return;
        //TODO: Implement.
        $response = new AmfResponse();
        $response->valueObject = new UserActionDailyVO();
        $response->valueObject->playerId = $_SESSION['id'];
        $response->message = $action;
        return $response;
    }

    public function performAction($playerId, $action)
    {
        if(!Panfu::isLoggedIn())
            return;

        $response = new AmfResponse();
        Console::log("Player Id " . $playerId . " performed " . $action);

        if($playerId == $_SESSION['id'] && $action == "played10") {
            Console::log($_SESSION['lastPlayed10'], time());

            if(!isset($_SESSION['lastPlayed10'])) {
                $_SESSION['lastPlayed10'] = 0;
            }
    
            if((time() - $_SESSION['lastPlayed10']) < 580) {
                $response->statusCode = 1;
                $response->message = "lastplayed10 denied " . (time() - $_SESSION['lastPlayed10']) . " seconds since last request.";
                return $response;
            }
            $response->message = "lastplayed10 accepted " . (time() - $_SESSION['lastPlayed10']) . " seconds since last request.";
            $response->valueObject = Panfu::played10();
        }
        return $response;
    }
}