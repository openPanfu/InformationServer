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
require_once 'Vo/FeedbackVO.php';
require_once 'Vo/LoginResultVO.php';
require_once 'Vo/PlayerInfoVO.php';
require_once 'Vo/GameServerVO.php';
require_once 'Vo/PartnerTrackingVO.php';

class amfConnectionService
{
    /**
     * Login an user using a loginVO
     *
     * @param loginVO $loginVo
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     *
     *  valueObject will be a LoginResultVO if accepted
     *  statusCode will be set to 0 (SUCCESS) if accepted.
     *  statusCode will be set to 1 (GENERAL_ERROR) if declined.
     */
    public function doLogin($loginVo)
	{
        $response = new AmfResponse();
        if(Panfu::loginUserWithVo($loginVo)) {
            // Color black
            if(!Panfu::hasItem(1001)) {
                Panfu::addItemToUser(1001, true);
            }
            // Treehouse
            if(!Panfu::hasItem(100)) {
                Panfu::addItemToUser(100, true);
            }
            $userData = Panfu::getUserDataById($_SESSION['id']);
            $response->statusCode = 0;
            $response->valueObject = new LoginResultVO();
            $response->valueObject->partnerTracking = new PartnerTrackingVO();
            $response->valueObject->membershipStatus = 0; // 1 = Show validate email message.
            $response->valueObject->email = $userData['email'];
            $response->valueObject->ticketId = Panfu::generateSessionId();
            $response->valueObject->gameServers = Panfu::getGameServers();
            // TODO: implement tour
            $response->valueObject->showTour = false;
            $response->valueObject->playerInfo = Panfu::getPlayerInfoForId($_SESSION['id']);
        } else {
            $response->statusCode = 1;
        }
        return $response;
    }

    /**
     * Login an user using a session ticket
     *
     * @param String $sessionTicket
     * @author Christiaan Bultena <christiaanbultena49@gmail.com>
     * @return AmfResponse
     *
     *  valueObject will be a LoginResultVO if accepted
     *  statusCode will be set to 0 (SUCCESS) if accepted.
     *  statusCode will be set to 1 (GENERAL_ERROR) if declined.
     */
    public function doLoginSession($sessionTicket)
	{
        $response = new AmfResponse();
	    if(Panfu::doLoginSession($sessionTicket) == 1) {
            // Color black
            if(!Panfu::hasItem(1001)) {
                Panfu::addItemToUser(1001, true);
            }
            // Treehouse
            if(!Panfu::hasItem(100)) {
                Panfu::addItemToUser(100, true);
            }
            $userData = Panfu::getUserDataById($_SESSION['id']);
            $response->statusCode = 0;
            $response->valueObject = new LoginResultVO();
            $response->valueObject->partnerTracking = new PartnerTrackingVO();
            $response->valueObject->membershipStatus = 0; // 1 = Show validate email message.
            $response->valueObject->email = $userData['email'];
            $response->valueObject->ticketId = Panfu::generateSessionId();
            $response->valueObject->gameServers = Panfu::getGameServers();
            // TODO: implement tour
            $response->valueObject->showTour = false;
            $response->valueObject->playerInfo = Panfu::getPlayerInfoForId($_SESSION['id']);
        } else {
	        $response->statusCode = 1;
        }
        return $response;
    }

    /**
     * Register a user using a registerVO
     *
     * @param registerVO $registerVO
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     *
     *  statusCode will be set to 0 (SUCCESS) if accepted.
     *  statusCode will be set to 1 (GENERAL_ERROR) if declined.
     */
    public function doRegister($registerVO)
	{
        $response = new AmfResponse();
        if(Panfu::registerUserWithVo($registerVO)) {
            $response->statusCode = 0;
        } else {
            $response->statusCode = 1;
        }
        return $response;
    }

    /**
     * Checks if the username has already been taken or is not acceptable.
     *
     * @param String $name Username to check
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     *
     *  statusCode will be set to 0 (SUCCESS) if accepted.
     *  statusCode will be set to 1 (GENERAL_ERROR) if declined.
     *  valueObject will be null if accepted.
     *  valueObject will be set to a instance of FeedbackVO if declined.
     */
    public function checkUserName($name)
    {
        $response = new AmfResponse();
        if(Panfu::usernameAcceptable($name) && Panfu::usernameNotTaken($name)) {
            $response->statusCode = 0;
        } else {
            $response->statusCode = 1;
            $response->valueObject = new FeedbackVO();
        }
        return $response;
    }

    /**
     * Checks if the email has already been taken or is not acceptable.
     *
     * @param String $email email to check
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     *
     *  statusCode will be set to 0 (SUCCESS) if accepted.
     *  statusCode will be set to 1 (GENERAL_ERROR) if declined.
     */
    public function checkEmailAddress($email)
    {
        //TODO: implement checks on the email address (valid email, email not already registered.)
        $response = new AmfResponse();
        return $response;
    }

    /**
     * Handle client ping
     *
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function ping()
    {
        $response = new AmfResponse();
        if(!Panfu::isLoggedIn()) {
            $response->statusCode = 1;
        }

        return $response;
    }
}
