<?php

/**
 * This file is part of openPanfu, a project that imitates the Flex remoting
 * and gameservers of Panfu.
 *
 * @category Utility
 * @author Altro50 <altro50@msn.com>
 */

session_start();

class Panfu
{
    private static $wordFilter = [];
    private static $levelDefinitions = null;

    /**
     * Sets and returns a session ticket for the user.
     * @author Altro50 <altro50@msn.com>
     * @return SecurityChatItemVO[]
     */
    public static function generateSafeChat()
    {
        require_once AMFPHP_ROOTPATH . '/Services/Vo/SecurityChatItemVO.php';
        $data = json_decode(file_get_contents(__DIR__ . '/safechatall.json'));
        $snippets = array();
        $i = 0;
        foreach($data as $entry) {
            $snippets[$i] = Self::traverseChildren($entry);
            $i++;
        }
        return $snippets;
    }

    /**
     * Returns children
     * @author Altro50 <altro50@msn.com>
     * @return SecurityChatItemVO[]
     */
    private static function traverseChildren($safeChatEntry)
    {
        $valueObject = new SecurityChatItemVO();
        $valueObject->label = $safeChatEntry->label . " ";
        foreach($safeChatEntry->children as $child) {
            array_push($valueObject->children, Self::traverseChildren($child));
        }
        return $valueObject;
    }
    
    /**
     * Sets and returns a session ticket for the user.
     * @author Altro50 <altro50@msn.com>
     * @return integer
     */
    public static function generateSessionId()
    {
        $sessionId = rand(7000, 19000);
        $pdo = Database::getPDO();
        $stmt = $pdo->prepare("UPDATE users SET ticket_id = :ticket WHERE id = :id");
        $stmt->bindParam(':ticket', $sessionId);
        $stmt->bindParam(':id', $_SESSION["id"]);
        $stmt->execute();
        return $sessionId;
    }

    /**
     * Ran every 10 minutes (Triggered by client).
     * @author Altro50 <altro50@msn.com>
     * @return ListVO Rewards for playing so long.
     */
    public static function played10()
    {
        require_once AMFPHP_ROOTPATH . "/Services/Vo/ListVO.php";
        require_once AMFPHP_ROOTPATH . "/Services/Vo/RewardVO.php";

        $listVo = new ListVO();
        $listVo->list = [];
        if(Panfu::$levelDefinitions == null) {
            // Load the level definitions from levels.json.
            Panfu::$levelDefinitions = json_decode(file_get_contents(__DIR__ . '/levels.json'));
        }

        $userData = Panfu::getUserDataById($_SESSION['id']);

        if($userData['social_level'] >= Panfu::$levelDefinitions->maxLevel) {
            return $listVo;
        }

        $level = Panfu::getLevel($userData['social_level']);

        if($level !== null) {
            // We can use $level->increment now.
            $newScore = $userData['social_score'] + $level->increment;
            $levelUp = new RewardVO();
            $levelUp->type = "sp";
            
            if($newScore >= 100) {
                // Yay, the user leveled!

                // Set the user's score to 0.
                $newScore = 0;
                $newLevel = $userData['social_level'] + 1;
                $levelUp->levelStatus = 1;
                
                $levelUp->number = $newScore;
                array_push($listVo->list, $levelUp);

                // Now we push the level rewards, what do they get for leveling?
                $nextLevel = Panfu::getLevel($newLevel);
                foreach($nextLevel->rewards as $reward) {
                    $toPush = new RewardVO();
                    $toPush->type = $reward->type;
                    switch($reward->type) {
                        case "item":
                            Panfu::addItemToUser((int)$reward->value);
                            $toPush->item = Panfu::getItemVo((int)$reward->value);
                            $toPush->item->active = false;
                            $toPush->item->bought = true;
                            break;
                        case "score":
                            $toPush->number = (int)$reward->value;
                            break;    
                        default:
                            Console::log("played10 > unknown reward type " . $reward->type . "! (No handling code)");
                            break;
                    }
                    Console::log($toPush);
                    array_push($listVo->list, $toPush);
                }
                
                // Set the last played 10 time to the current timestamp.
                // This prevents the user from spamming it to gain quick levels.
                $_SESSION['lastPlayed10'] = time();

                // Set their level to their new level.
                Panfu::setSocialLevel($_SESSION['id'], $newLevel);
            } else {
                // Huh? What's going on??
                // Why is this here twice?!
                $levelUp->number = $newScore;
                array_push($listVo->list, $levelUp);

                // Well, you see, the game will completely deny any items (with an error)
                // if you don't send the levelUp first.

                // btw, thank you satoshi for telling me this.
            }

            // Set their score to their new score.
            Panfu::setSocialScore($_SESSION['id'], $newScore);

        } else {
            Console::log("Missing level definition: " . $level);
        }

        return $listVo;
    }

    /**
     * Gets a level from the level definitions.
     * @author Altro50 <altro50@msn.com>
     * @param int $level The level.
     * @return object level definition.
     */
    public static function getLevel($level)
    {
        if(Panfu::$levelDefinitions == null) {
            // Load the level definitions from levels.json.
            Panfu::$levelDefinitions = json_decode(file_get_contents('levels.json'));
        }

        foreach(Panfu::$levelDefinitions->levels as $levelObj) {
            if($levelObj->level == $level) {
                return $levelObj;
            }
        }

        return null;
    }

    /**
     * Sets a user's social level.
     * @author Altro50 <altro50@msn.com>
     * @param int $userId User id to update.
     * @param int $level the new social level.
     * @return Void
     */
    public static function setSocialLevel($userId, $level)
    {
        $pdo = Database::getPDO();
        $update = $pdo->prepare("UPDATE users SET social_level = :social_level WHERE id = :id");
        $update->bindParam(":social_level", $level);
        $update->bindParam(":id", $userId);
        $update->execute();
    }
    /**
     * Sets a user's social score.
     * @author Altro50 <altro50@msn.com>
     * @param int $userId User id to update.
     * @param int $score the new social score.
     * @return Void
     */
    public static function setSocialScore($userId, $score)
    {
        $pdo = Database::getPDO();
        $update = $pdo->prepare("UPDATE users SET social_score = :social_score WHERE id = :id");
        $update->bindParam(":social_score", $score);
        $update->bindParam(":id", $userId);
        $update->execute();
    }

    /**
     * Returns a playerInfoVo for the specified user.
     * @author Altro50 <altro50@msn.com>
     * @param int $userId User id to get PlayerInfo for.
     * @return PlayerInfoVO
     */
    public static function getPlayerInfoForId($userId)
    {
        require_once AMFPHP_ROOTPATH . "/Services/Vo/PlayerInfoVO.php";
        try {
            $userData = Panfu::getUserDataById($userId);
            $playerInfo = new PlayerInfoVO();
            $playerInfo->id = $userData['id'];
            $playerInfo->name = $userData['name'];
            $playerInfo->coins = $userData['coins'];
            $playerInfo->isSheriff = $userData['sheriff'];
            $playerInfo->isPremium = (boolean)($userData['goldpanda'] > 0);
            $playerInfo->sex = ($userData['sex'] == 1 ? 'girl' : 'boy');
            $playerInfo->helperStatus = false; // obsolete, if the account is older than 2012, this will be set to false anyways.
            $playerInfo->isTourFinished = true; // TODO: implement tour
            $playerInfo->membershipStatus = $userData['goldpanda'];
            $playerInfo->socialLevel = $userData['social_level'];
            $playerInfo->socialScore = $userData['social_score'];
            $playerInfo->activeInventory = Panfu::getInventory($userData['id'], true);
            $playerInfo->inactiveInventory = Panfu::getInventory($userData['id'], false);
            $playerInfo->buddies = Panfu::getBuddiesForUserId($userId);

            // Let's calculate the days since register.
            $now = time();
            $difference = $now - strtotime($userData['created_at']);
            $playerInfo->daysOnPanfu = round($difference / (60 * 60 * 24));
            return $playerInfo;
        } catch(Exception $e) {
            Console::log("Error getting PlayerInfoVO \o/", $e);
            return null;
        }
    }

    /**
     * Gets the piece of furniture from the database as a FurnitureDataVO
     * @author Altro50 <altro50@msn.com>
     * @param int $itemId
     * @return FurnitureDataVO
     */
    public static function getFurnitureVo($itemId)
    {
        require_once AMFPHP_ROOTPATH . "/Services/Vo/FurnitureDataVO.php";
        $response = new FurnitureDataVO();
        $item = Panfu::getItem($itemId);
        $response->uid = $userId;
        $response->id = $itemId;
        $response->type = $item['type'];
        $response->active = false;
        $response->premium = true;
        $response->bought = true;
        $response->x = 0;
        $response->y = 0;
        $response->rot = 0;
        $response->roomID = 0;
        return $response;
    }

    public static function getFurniture($userId)
    {
        $pdo = Database::getPDO();
        $statement = $pdo->prepare("SELECT * FROM inventories WHERE user_id = :id");
        $statement->bindParam(":id", $userId, PDO::PARAM_INT);
        $statement->execute();
        $items = [];
        if($statement->rowCount() > 0) {
            foreach ($statement as $inventoryEntry) {
                $item = Panfu::getItem($inventoryEntry['item_id']);
                if(Panfu::isFurniture($item['type'])) {
                    $items[$i] = Panfu::getFurnitureVo($inventoryEntry['item_id']);
                    $items[$i]->active = $inventoryEntry['active'];
                    $items[$i]->premium = true;
                    $items[$i]->bought = true;
                    $items[$i]->x = $inventoryEntry['x'];
                    $items[$i]->y = $inventoryEntry['y'];
                    $items[$i]->rot = $inventoryEntry['rot'];
                    $items[$i]->roomID = $inventoryEntry['room'];
                    $i++;
                }
            }
        }
        return $items;
    }

    /**
     * Returns the gameservers on db as GameServerVOs
     * @author Altro50 <altro50@msn.com>
     * @return GameServerVO[]
     */
    public static function getGameServers()
    {
        require_once AMFPHP_ROOTPATH . "/Services/Vo/GameServerVO.php";
        $pdo = Database::getPDO();
        $stmt = $pdo->prepare("SELECT * FROM gameservers");
        $stmt->execute();
        $servers = $stmt->fetchAll();

        $gameServers = array();
        $i = 0;
        foreach ($servers as $gs) {
            $gameServers[$i] = new GameServerVO();
            $gameServers[$i]->id = $gs['id'];
            $gameServers[$i]->name = $gs['name'];
            $gameServers[$i]->url = $gs['url'];
            $gameServers[$i]->port = $gs['port'];
            $gameServers[$i]->playercount = $gs['player_count'];
            $i++;
        }
        return $gameServers;
    }

    public static function getGameServerKey($id)
    {
        $pdo = Database::getPDO();
        $statement = $pdo->prepare("SELECT secret_key FROM gameservers WHERE id=:id");
        $statement->bindParam(":id", $id, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetch()["secret_key"];
    }

    /**
     * Log-in the user in the loginVO data.
     * @author Altro50 <altro50@msn.com>
     * @param loginVO $loginVO Login data
     * @return boolean
     */

    public static function loginUserWithVo($loginVO)
    {
        if(isset($loginVO->_explicitType) && $loginVO->_explicitType == "com.pandaland.mvc.model.vo.LoginVO") {
            Console::log("User " . $loginVO->playerName . " is trying to login.");
            $username = $loginVO->playerName;
            $password = $loginVO->pw;
            // Make sure the username has been taken.
            if(!Panfu::usernameNotTaken($username)) {
                $userData = Panfu::getUserDataByUsername($username);
                if(password_verify($password, $userData['password'])) {
                    $_SESSION["id"] = $userData['id'];
                    return true;
                }
            }
            return false;
        }
        return false;
    }

    /**
     * Log-in the user with a session id.
     * @author Christiaan Bultena <christiaanbultena49@gmail.com>
     * @author Altro50 <altro50@msn.com>
     * @param ticketId
     * @return boolean
     */
    public static function doLoginSession($ticketId)
    {
        if($ticketId === null || $ticketId === "" || strlen($ticketId) < 5) {
            return false;
        }

        $pdo = Database::getPDO();
        $stmt = $pdo->prepare("Select id from users where ticket_id = :ticket");
        $stmt->bindParam(":ticket", $ticketId);
        $stmt->execute();
        if ($stmt->rowCount() == 1) {
            $userId = $stmt->fetch()["id"];
            $_SESSION["id"] = $userId;
            GSCommunicator::checkConnection();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Register a user with the data provided in the registerVO
     * @author Altro50 <altro50@msn.com>
     * @param registerVO $registerVO Registration Data
     * @return boolean
     */
    public static function registerUserWithVo($registerVO)
    {
        if(isset($registerVO->_explicitType)) {
            if ($registerVO->_explicitType == "com.pandaland.mvc.model.vo.RegisterVO") {
                $name = (string)$registerVO->name;
                $password = (string)password_hash($registerVO->pw, PASSWORD_BCRYPT);
                $email = (string)$registerVO->emailParents;
                $sex = (int)($registerVO->sex == "girl" || $registerVO->sex == "FEMALE");

                if(Panfu::usernameAcceptable($name) && Panfu::usernameNotTaken($name)) {
                    $pdo = Database::getPDO();
                    $insert = $pdo->prepare("INSERT INTO users (name, password, email, sex) VALUES (:name, :password, :email,:sex)");
                    $insert->bindParam(":name", $name);
                    $insert->bindParam(":password", $password);
                    $insert->bindParam(":email", $email);
                    $insert->bindParam(":sex", $sex);
                    $result = $insert->execute();
                    return true;
                }
                return false;
            }
            return false;
        }
        return false;
    }

    /**
     * Checks if the username has not yet been taken.
     * @author Altro50 <altro50@msn.com>
     * @param String $username Username to check
     * @return boolean
     */
    public static function usernameNotTaken($username)
    {
        $pdo = Database::getPDO();
        $checkStmt = $pdo->prepare("SELECT * FROM users WHERE name = :name");
        $checkStmt->bindParam(":name", $username, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->rowCount() == 0) {
            return true;
        }
        return false;
    }

    /**
     * Checks if the username is acceptable (no invalid characters, bad words)
     * @author Altro50 <altro50@msn.com>
     * @param String $username Username to check
     * @return boolean
     */
    public static function usernameAcceptable($username)
    {
        if (preg_match('/^[A-Za-z0-9_]{3,25}$/', $username)) {
            // Let's get rid of some characters
            $username = str_replace("_", "", $username);
            $username = str_replace("-", "", $username);
            $username = Panfu::undoLeet($username);

            // Load the wordfilter first
            if (sizeof(Panfu::$wordFilter) === 0) {
                Panfu::$wordFilter = explode("\n", str_replace("\r", "", file_get_contents(__DIR__ . "/wordfilter.txt")));
            }

            foreach(Panfu::$wordFilter as $forbiddenWord) {
                if(substr( $forbiddenWord, 0, 1 ) == "#") {
                    continue;
                }
                if(strpos($username, $forbiddenWord) !== false) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Checks if the user is currently logged in and if the session is still valid.
     * @author Altro50 <altro50@msn.com>
     * @return boolean
     */
    public static function isLoggedIn()
    {
        if (isset($_SESSION["id"])) {
            $pdo = Database::getPDO();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id");
            $stmt->bindParam(':id', $_SESSION["id"]);
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row) {
                return true;
            } else {
                // User suddenly removed from the DB.
                session_destroy();
                session_start();
                return false;
            }
        }
        return false;
    }

    /**
     * Adds two players to eachother's friendslist
     * @author Altro50 <altro50@msn.com>
     * @param int $buddy1
     * @param int $buddy2
     * @return void
     */
    public static function addBuddies($buddy1, $buddy2)
    {
        Panfu::setRelationBetweenPlayers($buddy1, $buddy2, 1);
        Panfu::setRelationBetweenPlayers($buddy2, $buddy1, 1);
    }

    /**
     * Removes players from eachother's friendslist
     * @author Altro50 <altro50@msn.com>
     * @param int $buddy1
     * @param int $buddy2
     * @return void
     */
    public static function removeBuddies($buddy1, $buddy2)
    {
        Panfu::setRelationBetweenPlayers($buddy1, $buddy2, 0);
        Panfu::setRelationBetweenPlayers($buddy2, $buddy1, 0);
    }

    /**
     * Ignore a user with userId
     * @author Altro50 <altro50@msn.com>
     * @param int $playerId
     * @return void
     */
    public static function ignorePlayer($playerId)
    {
        Panfu::setRelationBetweenPlayers($_SESSION['id'], $playerId, 2);
    }

    /**
     * Changes or inserts a relation between two users.
     * @author Altro50 <altro50@msn.com>
     * @param int $player1
     * @param int $player2
     * @param int $relation
     * @return void
     */
    public static function setRelationBetweenPlayers($player1, $player2, $relation)
    {
        $pdo = Database::getPDO();
        
        if(!Panfu::hasRelation($player1, $player2)) {
            $insert = $pdo->prepare("INSERT INTO relations (player1, player2, relation_type) VALUES (:player1, :player2, :relation_type)");
            $insert->bindParam(":player1", $player1);
            $insert->bindParam(":player2", $player2);
            $insert->bindParam(":relation_type", $relation);
            $insert->execute();
        } else {
            $update = $pdo->prepare("UPDATE relations SET relation_type = :relation_type WHERE player1 = :player1 AND player2 = :player2");
            $update->bindParam(":relation_type", $relation);
            $update->bindParam(":player1", $player1);
            $update->bindParam(":player2", $player2);
            $update->execute();
        }

        GSCommunicator::communicate("updateBuddyStatus", $player1, $player2, $relation);
    }

    /**
     * Checks if a relation between two users exists
     * @author Altro50 <altro50@msn.com>
     * @param int $player1
     * @param int $player2
     * @return boolean wether a relation exists
     */
    public static function hasRelation($player1, $player2)
    {
        $pdo = Database::getPDO();
        $statement = $pdo->prepare("SELECT id FROM relations WHERE player1 = :player1 AND player2 = :player2");
        $statement->bindParam(":player1", $player1, PDO::PARAM_INT);
        $statement->bindParam(":player2", $player2, PDO::PARAM_INT);
        $statement->execute();
        return ($statement->rowCount() > 0);
    }

    /**
     * Gets users on the user's relation list with a specific relation type.
     * @author Altro50 <altro50@msn.com>
     * @param int $player1
     * @param int $relation
     * @return int[] Players on the user's relation list with the specified relation type.
     */
    public static function getPlayersWithRelation($player1, $relation)
    {
        $pdo = Database::getPDO();
        $statement = $pdo->prepare("SELECT * FROM relations WHERE player1 = :player1 AND relation_type = :relation_type");
        $statement->bindParam(":player1", $player1, PDO::PARAM_INT);
        $statement->bindParam(":relation_type", $relation);
        $statement->execute();
        $relations = $statement->fetchAll();
        $players = [];
        $i = 0;
        foreach($relations as $relation) {
            // player2 will always be someone you have a relation with.
            $players[$i] = $relation['player2'];
            $i++;
        }
        return $players;
    }

    /**
     * Gets a list of all buddies on the user's relation list.
     * @author Altro50 <altro50@msn.com>
     * @param int $userId
     * @return SmallPlayerInfoVO[] Buddies
     */
    public static function getBuddiesForUserId($userId)
    {
        require_once AMFPHP_ROOTPATH . "/Services/Vo/SmallPlayerInfoVO.php";
        $buddies = Panfu::getPlayersWithRelation($userId, 1);
        $buddyArray = [];
        $i = 0;
        foreach($buddies as $buddyId) {
            $data = Panfu::getUserDataById($buddyId);
            $buddyArray[$i] = new SmallPlayerInfoVO();
            $buddyArray[$i]->playerId = $buddyId;
            $buddyArray[$i]->playerName = $data["name"];
            if($data["current_gameserver"] != null && $data["current_gameserver"] != 0)
                $buddyArray[$i]->currentGameServer = $data["current_gameserver"];

            $i++;
        }
        return $buddyArray;
    }

    /**
     * Gets a list of all buddies on the user's relation list as BuddyVo objects.
     * @author Altro50 <altro50@msn.com>
     * @param int $userId
     * @return SmallPlayerInfoVO[] Buddies
     */
    public static function getBuddiesVoForUserId($userId)
    {
        require_once AMFPHP_ROOTPATH . "/Services/Vo/BuddyVO.php";
        $buddies = Panfu::getPlayersWithRelation($userId, 1);
        $buddyArray = [];
        $i = 0;
        foreach($buddies as $buddyId) {
            $data = Panfu::getUserDataById($buddyId);
            $buddyArray[$i] = new BuddyVO();
            $buddyArray[$i]->id = $buddyId;
            $buddyArray[$i]->name = $data["name"];
            $buddyArray[$i]->premium = $data["goldpanda"];
            $buddyArray[$i]->bestfriend = false;
            if($data["current_gameserver"] != null && $data["current_gameserver"] != 0)
                $buddyArray[$i]->currentGameServer = $data["current_gameserver"];
            $buddyArray[$i]->socialLevel = $data["social_level"];
            $i++;
        }
        return $buddyArray;
    }

    /**
     * Returns the users table row for a id.
     * @param int $id The user id to look for.
     * @author Altro50 <altro50@msn.com>
     * @return array the row from the database.
     */
    public static function getUserDataById($id)
    {
        $pdo = Database::getPDO();
        $userStatement = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $userStatement->bindParam(":id", $id, PDO::PARAM_INT);
        $userStatement->execute();
        $userData = $userStatement->fetch();
        return $userData;
    }

    /**
     * Returns the users table row for a username.
     * @param String $username The username to look for.
     * @author Altro50 <altro50@msn.com>
     * @return array the row from the database.
     */
    public static function getUserDataByUsername($username)
    {
        if(!Panfu::usernameNotTaken($username)) {
            $pdo = Database::getPDO();
            $userStatement = $pdo->prepare("SELECT * FROM users WHERE name = :name");
            $userStatement->bindParam(":name", $username, PDO::PARAM_INT);
            $userStatement->execute();
            $userData = $userStatement->fetch();
            return $userData;
        } else {
            return null;
        }
    }

    /**
     * Returns an array filled with StateVOs
     * @author Altro50 <altro50@msn.com>
     * @param int[] $stateIds Ids of the states to get a stateVO of.
     * @return StateVO[]
     */
    public static function getStates($stateIds)
    {
        require_once AMFPHP_ROOTPATH . "/Services/Vo/StateVO.php";
        $states = array();
        $pdo = Database::getPDO();
        $statement = $pdo->prepare("SELECT * FROM states WHERE user_id = :id");
        $statement->bindParam(":id", $_SESSION['id']);
        $statement->execute();
        $i = 0;
        if($statement->rowCount() > 0) {
            foreach($statement as $state) {
                if(in_array($state['category'], $stateIds)) {
                    $states[$i] = new StateVO();
                    $states[$i]->playerId = $_SESSION['id'];
                    $states[$i]->cathegoryId = $state['category'];
                    $states[$i]->nameId = $state['name'];
                    $states[$i]->stateValue = $state['value'];
                    $states[$i]->lastChanged = $state['last_changed'] * 100000000;
                    $i++;
                }
            }
        }
        return $states;
    }

    /**
     * Sets a state on DB for the user
     * @author Altro50 <altro50@msn.com>
     * @param int $category
     * @param int $name
     * @param int $value
     * @return StateVO
     */
    public static function setState($category, $name, $value)
    {
        require_once AMFPHP_ROOTPATH . "/Services/Vo/StateVO.php";
        $pdo = Database::getPDO();
        $timestamp = round(microtime(true));
        if(Panfu::stateExists($category, $name)) {
            $update = $pdo->prepare("UPDATE states SET value = :value, last_changed = :lastChanged WHERE user_id = :playerId AND category = :category AND name = :name");
            $update->bindParam(":value", $value);
            $update->bindParam(":lastChanged", $timestamp);
            $update->bindParam(":playerId", $_SESSION["id"]);
            $update->bindParam(":category", $category);
            $update->bindParam(":name", $name);
            $update->execute();
        } else {
            $insert = $pdo->prepare("INSERT INTO states (value,last_changed,user_id,category,name) VALUES (:value, :lastChanged, :playerId, :category, :name)");
            $insert->bindParam(":value", $value);
            $insert->bindParam(":lastChanged", $timestamp);
            $insert->bindParam(":playerId", $_SESSION["id"]);
            $insert->bindParam(":category", $category);
            $insert->bindParam(":name", $name);
            $insert->execute();
        }
        $state = new StateVO();
        $state->playerId = $_SESSION['id'];
        $state->nameId = $name;
        $state->stateValue = $value;
        $state->cathegoryId = $category;
        $state->lastChanged = $timestamp * 100000000;
        return $state;
    }

    /**
     * Checks if a state exists
     * @author Altro50 <altro50@msn.com>
     * @param int $category
     * @param int $name
     * @return Boolean
     */
    public static function stateExists($category, $name)
    {
        $pdo = Database::getPDO();
        $statement = $pdo->prepare("SELECT * FROM states WHERE user_id = :id AND category = :category AND name = :name");
        $statement->bindParam(":id", $_SESSION['id'], PDO::PARAM_INT);
        $statement->bindParam(":category", $category, PDO::PARAM_INT);
        $statement->bindParam(":name", $name, PDO::PARAM_INT);
        $statement->execute();
        return ($statement->rowCount() > 0);
    }

    /**
     * Checks if the current user can afford something.
     * @author Altro50 <altro50@msn.com>
     * @param int $price
     * @return boolean
     */
    public static function canAfford($price)
    {
        $currentUser = Panfu::getUserDataById($_SESSION['id']);
        if($currentUser['coins'] > $price) {
            return true;
        }
        return false;
    }    
    
    /**
    * Updates the user's coin count.
    * @author Altro50 <altro50@msn.com>
    * @param int $coins
    * @return void
    */
   public static function updateCoins($coins)
   {
        $pdo = Database::getPDO();
        $update = $pdo->prepare("UPDATE users SET coins = :coins WHERE id = :userId");
        $update->bindParam(":coins", $coins);
        $update->bindParam(":userId", $_SESSION['id']);
        $update->execute();
   }

    /**
     * Deducts an certain amount coins from the currently logged in user.
     * @author Altro50 <altro50@msn.com>
     * @param int $coins
     * @return void
     */
    public static function deductCoins($coins)
    {
        if(Panfu::canAfford($coins)) {
            $pdo = Database::getPDO();
            $update = $pdo->prepare("UPDATE users SET coins = coins - :toDeduct WHERE id = :userId");
            $update->bindParam(":toDeduct", $coins);
            $update->bindParam(":userId", $_SESSION['id']);
            $update->execute();
        }
    }

    /**
     * Adds item to a users inventory.
     * @author Altro50 <altro50@msn.com>
     * @param int $itemId
     * @param boolean $active
     * @return void
     */
    public static function addItemToUser($itemId, $active = false)
    {
        $pdo = Database::getPDO();
        $insert = $pdo->prepare("INSERT INTO inventories (user_id, item_id, active, bought) VALUE (:userId, :itemId, :active, true)");
        $insert->bindParam(":userId", $_SESSION['id'], PDO::PARAM_INT);
        $insert->bindParam(":itemId", $itemId, PDO::PARAM_INT);
        $insert->bindParam(":active", $active, PDO::PARAM_INT);
        $result = $insert->execute();
        if(!$result) {
            Console::log($pdo->errorInfo());
        } else {
            Console::log("Pdo okiedokie!");
        }
    }

    /**
     * Gets the item row from the database
     * @author Altro50 <altro50@msn.com>
     * @param int $itemId
     * @return array the row from the database
     */
    public static function getItem($itemId)
    {
        $pdo = Database::getPDO();
        $itemStatement = $pdo->prepare("SELECT * FROM items WHERE id = :id");
        $itemStatement->bindParam(":id", $itemId, PDO::PARAM_INT);
        $itemStatement->execute();
        $itemData = $itemStatement->fetch();
        if($itemData["type"] < 10) {
            $itemData["type"] = "0" . (string)$itemData["type"];
        }
        return $itemData;
    }

    /**
     * Gets the item from the database as a itemVo
     * @author Altro50 <altro50@msn.com>
     * @param int $itemId
     * @return ItemVO
     */
    public static function getItemVo($itemId)
    {
        require_once AMFPHP_ROOTPATH . "/Services/Vo/ItemVO.php";
        $response = new ItemVO();
        $item = Panfu::getItem($itemId);
        $response->id = $item['id'];
        $response->name = $item['name'];
        $response->type = $item['type'];
        $response->price = $item['price'];
        $response->zettSort = $item['z'];
        $response->premium = $item['premium'];
        $response->bought = true;
        return $response;
    }

    /**
     * Checks if a item id exists
     * @author Altro50 <altro50@msn.com>
     * @param Int $itemId
     * @return boolean
     */
    public static function itemExists($itemId)
    {
        $pdo = Database::getPDO();
        $itemStatement = $pdo->prepare("SELECT * FROM items WHERE id = :id");
        $itemStatement->bindParam(":id", $itemId, PDO::PARAM_INT);
        $itemStatement->execute();
        if ($itemStatement->rowCount() == 0) {
            return false;
        }
        return true;
    }

    /**
     * Removes an item from the user's inventory.
     * @author Altro50 <altro50@msn.com>
     * @param Int $itemId
     * @return void
     */
    public static function removeFromInventory($itemId)
    {
        if(Panfu::hasItem($itemId)) {
            $removeStatement = $pdo->prepare("DELETE FROM inventories WHERE user_id = :userId AND item_id = :itemId");
            $removeStatement->bindParam(":userId", $_SESSION['id'], PDO::PARAM_INT);
            $removeStatement->bindParam(":itemId", $itemId, PDO::PARAM_INT);
            $removeStatement->execute();
        }
    }

    /**
     * Checks if the current user has a certain item.
     * @author Altro50 <altro50@msn.com>
     * @param Int $itemId
     * @return boolean
     */
    public static function hasItem($itemId)
    {
        $pdo = Database::getPDO();
        $itemStatement = $pdo->prepare("SELECT id FROM inventories WHERE user_id = :userId AND item_id = :itemId");
        $itemStatement->bindParam(":userId", $_SESSION['id'], PDO::PARAM_INT);
        $itemStatement->bindParam(":itemId", $itemId, PDO::PARAM_INT);
        $result = $itemStatement->execute();
        if(!$result) {
            Console::log($pdo->errorInfo());
        } else {

        }
        if ($itemStatement->rowCount() == 0) {
            return false;
        }
        return true;
    }

    /**
     * Gets the inventory for a user.
     * @author Altro50 <altro50@msn.com>
     * @param Int $userId
     * @param Boolean $active
     * @return ItemVO[]
     */
    public static function getInventory($userId, $active = false)
    {
        $pdo = Database::getPDO();
        $items = array();
        $i = 0;
        $statement = $pdo->prepare("SELECT * FROM inventories WHERE user_id = :id AND active = :active");
        $statement->bindParam(":id", $userId, PDO::PARAM_INT);
        $statement->bindParam(":active", $active, PDO::PARAM_INT);

        $statement->execute();
        if($statement->rowCount() > 0) {
            foreach ($statement as $inventoryEntry) {
                $items[$i] = Panfu::getItemVo($inventoryEntry['item_id']);
                $items[$i]->active = $inventoryEntry['active'];
                $i++;
            }
        }
        return $items;
    }

    /**
     * Checks if the item type is a piece of furniture.
     * @author Altro50 <altro50@msn.com>
     * @param Int $itemType the type to check.
     * @return Boolean True if furniture.
     */
    public static function isFurniture($itemType)
    {
        return ($itemType == "13" || $itemType == "17" || $itemType == "14" || $itemType == "00" || $itemType == "50");
    }

    /**
     * Often when coming up with usernames, users might try evading the word censor
     * by using something known as "1337 speak", this converts leet to normal text.
     * @author Altro50 <altro50@msn.com>
     * @param String $text The text to replace leet speak in.
     * @return String $text without leet speak
     */
    public static function undoLeet($text)
    {
        $text = str_split($text);
        $leet_replace = array();
        $leet_replace[0] = "o";
        $leet_replace[1] = "l";
        $leet_replace[2] = "z";
        $leet_replace[3] = "e";
        $leet_replace[4] = "a";
        $leet_replace[5] = "s";
        $leet_replace[6] = "b";
        $leet_replace[7] = "t";
        $leet_replace[8] = "b";
        $leet_replace[9] = "p";
        $changedText = "";
        foreach($text as $letter) {
            if(is_numeric($letter))
                $changedText .= str_ireplace(array_keys($leet_replace), array_values($leet_replace), $letter);
            else
                $changedText .= $letter;
        }
        return $changedText;
    }
}