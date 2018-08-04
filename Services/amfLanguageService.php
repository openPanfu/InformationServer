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
require_once 'Vo/SecurityChatItemVO.php';

class amfLanguageService
{
    /**
     * Gets the secure chat snippets for the selected language
     *
     * @param String $language
     * @param String $type
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
     public function getSecureChatSnippets($language, $type)
     {
        $response = new AmfResponse();
        $response->valueObject = new SecurityChatItemVO();
        $response->valueObject->children = Panfu::generateSafeChat();
        $response->message = $type;
        $response->statusCode = 0;
        return $response;
     }
}