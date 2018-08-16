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
require_once 'Vo/HomeDataVO.php';



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
        $update->bindParam(":status", $status, PDO::PARAM_INT);
        $update->bindParam(":playerId", $_SESSION['id'], PDO::PARAM_INT);
        $update->execute();
        $response->statusCode = 0;
        $response->message = "Tour updated!";
        $response->valueObject = null;
        return $response;
    }

    /**
     * Adds the specified user to your buddy list.
     *
     * @param int $buddyId
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function addToBuddylist($buddyId)
    {
        $response = new AmfResponse();
        Panfu::addBuddies($_SESSION['id'], $buddyId);
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
                    if(!Panfu::isFurniture($itemData['type'])) {
                        $itemVO = Panfu::getItemVo($itemId);
                        $response->valueObject = $itemVO;
                    } else {
                        $furniVO = Panfu::getFurnitureVo($itemId);
                        $response->valueObject = $furniVO;
                    }
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
        foreach($itemArray as $item) {
            Panfu::removeFromInventory($item);
        }
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

    public function lockHome($locked)
    {
        //TODO: stubbed
        return new AmfResponse();
    }

    /**
     * Returns the furniture for a user, to form their home.
     *
     * @param int $playerId
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function getPlayerHome($playerId)
    {
        $response = new AmfResponse();
        if(Panfu::isLoggedIn()) {
            try {
                $response->valueObject = new HomeDataVO();
                $response->valueObject->id = 0;
                $response->valueObject->playerID = $playerId;
                $response->valueObject->locked = false; // TODO: store in Database
                $response->valueObject->furnitureList = Panfu::getFurniture($playerId);
                $response->valueObject->trackList = []; // TODO: add
                $response->valueObject->pets = []; // TODO: add
                $response->valueObject->pokoPets = []; // TODO: add
                $response->valueObject->bollies = []; // TODO: add
            } catch(Exception $e) {
                $response->statusCode = 1;
                $response->message = "Error occured while getting your inventory.";
                return $response;
            }
        }
        return $response;
    }

    
    /**
     * Updates the furniture provided in $furnitureList.
     *
     * @param FurnitureDataVO[] $furnitureList
     * @author Altro50 <altro50@msn.com>
     * @return AmfResponse
     */
    public function updateFurnitures($furnitureList)
    {
        $response = new AmfResponse();
        if(Panfu::isLoggedIn()) {
            try {
                $pdo = Database::getPDO();
                foreach($furnitureList as $FurnitureDataVO) {
                    $update = $pdo->prepare("UPDATE inventories SET x = :x, y = :y, rot = :rot, room = :room, active = :active WHERE user_id = :playerId AND item_id = :itemId");
                    $update->bindParam(":x", $FurnitureDataVO->x, PDO::PARAM_INT);
                    $update->bindParam(":y", $FurnitureDataVO->y, PDO::PARAM_INT);
                    $update->bindParam(":rot", $FurnitureDataVO->rot, PDO::PARAM_INT);
                    $update->bindParam(":room", $FurnitureDataVO->roomID, PDO::PARAM_INT);
                    $update->bindParam(":active", $FurnitureDataVO->active, PDO::PARAM_INT);
                    $update->bindParam(":playerId", $_SESSION['id'], PDO::PARAM_INT);
                    $update->bindParam(":itemId", $FurnitureDataVO->id, PDO::PARAM_INT);
                    $update->execute();
                }
            } catch(Exception $e) {
                $response->statusCode = 1;
                $response->message = "Error occured while updating your inventory.";
                return $response;
            }
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
