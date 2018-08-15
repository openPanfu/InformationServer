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

class amfRegistrationService
{
    /**
     * Checks if the username is still available.
     *
     * @param string $name the username to check
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function checkUserName($name)
    {
        $response = new AmfResponse();
        if(Panfu::usernameAcceptable($name)) {
            if(Panfu::usernameNotTaken($name)) {
                $response->valueObject = true;
            } else {
                $response->valueObject = false;
            }
        } else {
            $response->valueObject = "BLACKLISTED";
        }
        return $response;
    }

    /**
     * Gives the user username suggestions.
     *
     * @param string $name the username to check
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function loadUsernameSuggestions($name, $gender, $unknown)
    {
        $namesuggestion = "";
        while($namesuggestion == ""){
            $test = substr($name . rand(7000, 19000), 0, 12);
            if(Panfu::usernameNotTaken($test)) {
                $namesuggestion = $test;
            }
        }

        $response = new AmfResponse();
        // Needs to be an array, however the client only uses the first entry.
        $response->valueObject = [$namesuggestion];
        return $response;
    }

    /**
     * Checks if the email has already been used or is invalid.
     *
     * @param string $name the username to check
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function checkEmailAddress($email)
    {
        $response = new AmfResponse();
        $response->valueObject = true;
        return $response;
    }

    /**
     * Register a user using a registerVO
     *
     * @param registerVO $registerVO
     * @author Altro50 <altro50@msn.com>
     * 
     * @return int (When successful) userId.
     * @return AmfResponse (On error)
     *
     *  statusCode will be set to 0 (SUCCESS) if accepted.
     *  statusCode will be set to 1 (GENERAL_ERROR) if declined.
     */
    public function register($registerVO)
	{
        if(Panfu::registerUserWithVo($registerVO)) {
            return 0;
        } else {
            $response = new AmfResponse();
            $response->statusCode = 1;
            return $response;
        }   
    }

}