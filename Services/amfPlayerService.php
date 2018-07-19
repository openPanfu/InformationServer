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
require_once 'Vo/listVO.php';
require_once 'Vo/StateVO.php';
require_once 'Vo/InventoryVO.php';

class amfPlayerService
{
    /**
     * Get states from the categories provided in $states
     *
     * @param int[] $states
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     *
     *  valueObject will be a ListVO filled with StateVOs if accepted
     */
    public function getStates($states)
    {
        $response = new AmfResponse();
        if(Panfu::isLoggedIn()) {
            $response->statusCode = 0;
            $response->valueObject = new ListVO();
            $response->valueObject->list = Panfu::getStates($states);
        } else {
            $response->statusCode = 1;
        }
        return $response;
    }

    /**
     * Set a state
     *
     * @param int $category
     * @param int $name
     * @param int $value
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function setState($category, $name, $value)
    {
        $response = new AmfResponse();
        if(Panfu::isLoggedIn()) {
            $response->statusCode = 0;
            $response->valueObject = Panfu::setState($category, $name, $value);
        } else {
            $response->statusCode = 1;
        }
        return $response;
    }

    /**
     * Set the tour status
     *
     * @param int $value
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function updateTourFinished($value)
    {
        return new AmfResponse();
    }

    /**
     * Purchase a item
     *
     * @param int $itemId The id to add to the user's profile
     * @param string $itemHash A hash to validate.
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function purchaseItem($itemId, $itemHash)
    {
        $response = new AmfResponse();
        $response->statusCode = 6;
        return $response;
    }

    /**
     * Delete a item
     *
     * @param int[] $itemArray Arrays of items to remove.
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function removeItems($itemArray)
    {
        $response = new AmfResponse();
        $response->valueObject = new InventoryVO();
        return $response;
    }

    /**
     * Delete a item
     *
     * @param int[] $players Player ids
     * @param Boolean $detailed Unused
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function getPlayerInfoList($players, $detailed)
    {
        $response = new AmfResponse();
        if(Panfu::isLoggedIn()) {
            $list = new ListVO();
            $i = 0;
            foreach ($players as $player) {

                $list->list[$i] = Panfu::getPlayerInfoForId($player);
            }
            $response->valueObject = $list;
        } else {
            $response->statusCode = 1;
        }
        return $response;
    }

    /**
     * Gets the PlayerInfoVO for a player
     *
     * @param int $playerId The player id to get
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function getPlayerCard($playerId)
    {
        $response = new AmfResponse();
        if(Panfu::isLoggedIn()) {
            $response->valueObject = Panfu::getPlayerInfoForId($playerId);
        }
        return $response;
    }
}