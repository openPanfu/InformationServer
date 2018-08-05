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
     * @param int $status
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function updateTourFinished($status)
    {
        $response = new AmfResponse();
        $pdo = Database::getPDO();
        $update = $pdo->prepare("UPDATE users SET tour_finished = :status WHERE id = :playerId");
        $update->bindParam(":status", $status, PDO::PARAM_BOOL);
        $update->bindParam(":playerId", $_SESSION['id'], PDO::PARAM_INT);
        $update->execute();
        $response->statusCode = 0;
        $response->message = "Tour updated!";
        $response->valueObject = null;
        return $response;
    }

    /**
     * Purchase an item
     *
     * @param int $itemId The id to add to the user's profile
     * @param string $itemHash A hash to validate.
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function purchaseItem($itemId, $itemHash)
    {
        $response = new AmfResponse();
        if(Panfu::isLoggedIn()) {
            if(Panfu::itemExists($itemId)) {
                $itemData = Panfu::getItem($itemId);
                if(Panfu::canAfford($itemData['price'])) {
                    Panfu::deductCoins($itemData['price']);
                    Panfu::addItemToUser($itemId);
                    $response->message = "Item added!";
                    $response->statusCode = 0;
                    $itemVO = Panfu::getItemVo($itemId);
                    $response->valueObject =  $itemVO;
                } else {
                    $response->message = "Not enough coins.";
                    $response->statusCode = 6;
                }
            } else {
                $response->message = "Item doesn't exist.";
                $response->statusCode = 1;
            }
        } else {
            $response->message = "Not logged in";
            $response->statusCode = 1;
        }
        return $response;
    }

    /**
     * Update the inventory of a user
     *
     * @param ItemVO[] $activeInventory
     * @param ItemVO[] $inactiveInventory
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function updateItems($activeInventory, $inactiveInventory)
    {
        $response = new AmfResponse();
        $pdo = Database::getPDO();
        foreach($activeInventory as $itemVO) {
            $update = $pdo->prepare("UPDATE inventories SET active = 1 WHERE user_id = :playerId AND item_id = :itemId");
            $update->bindParam(":playerId", $_SESSION['id'], PDO::PARAM_INT);
            $update->bindParam(":itemId", $itemVO->id);
            $update->execute();
        }
        foreach($inactiveInventory as $itemVO) {
            $update = $pdo->prepare("UPDATE inventories SET active = 0 WHERE user_id = :playerId AND item_id = :itemId");
            $update->bindParam(":playerId", $_SESSION['id'], PDO::PARAM_INT);
            $update->bindParam(":itemId", $itemVO->id);
            $update->execute();
        }
        $response->valueObject = Panfu::getPlayerInfoForId($_SESSION['id']);
        return $response;
    }
    /**
     * Delete an item
     *
     * @param int[] $itemArray Arrays of items to remove.
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function removeItems($itemArray)
    {
        // TODO: implement, this is a placeholder so that the client-side inventory isn't overwritten.
        $response = new AmfResponse();
        $response->valueObject = new InventoryVO();
        $response->valueObject->activeItems = Panfu::getInventory($_SESSION['id'], true);
        $response->valueObject->inactiveItems = Panfu::getInventory($_SESSION['id'], false);
        return $response;
    }

    /**
     * Get PlayerInfoVOs for players
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
                $i++;
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

    /**
     * Sets the user's coins after finishing a minigame.
     *
     * @param int $score the coins the user has earned
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function updateScore($score)
    {
        $response = new AmfResponse();
        if(Panfu::isLoggedIn()) {
            Panfu::updateCoins($score);
        }
        return $response;
    }
}
