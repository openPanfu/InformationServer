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
require_once 'Vo/ProfileVO.php';

class amfProfileService
{
    /**
     * Get a Profile for a user.
     *
     * @param int $id Id to get the profile for.
	 * @param boolean $premium Unused.
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function getProfile($id, $premium)
    {
        $response = new AmfResponse();
        $response->valueObject = new ProfileVO();
        $response->valueObject->id = $id;
        return $response;
    }
}