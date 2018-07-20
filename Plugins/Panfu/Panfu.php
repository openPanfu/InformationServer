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

    /**
     * Sets and returns a session ticket for the user.
     * @author Altro50 <altro50@msn.com>
     * @return integer
     */
    public static function generateSessionId()
    {
        $sessionId = rand(1000, 9000);
        $pdo = Database::getPDO();
        $stmt = $pdo->prepare("UPDATE users SET ticketId = :ticket WHERE id = :id");
        $stmt->bindParam(':ticket', $sessionId);
        $stmt->bindParam(':id', $_SESSION["id"]);
        $stmt->execute();
        return $sessionId;
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
            $playerInfo->name = $userData['username'];
            $playerInfo->coins = $userData['coins'];
            $playerInfo->isSheriff = $userData['sheriff'];
            $playerInfo->isPremium = (boolean)($userData['goldpanda'] > 0);
            $playerInfo->sex = ($userData['sex'] == 1 ? 'girl' : 'boy');
            $playerInfo->helperStatus = false; // obsolete, if the account is older than 2012, this will be set to false anyways.
            $playerInfo->isTourFinished = true; // TODO: implement tour
            $playerInfo->membershipStatus = $userData['goldpanda'];
            $playerInfo->socialLevel = 1;

            // Let's calculate the days since register.
            $now = time();
            $difference = $now - $userData['signupDate'];
            $playerInfo->daysOnPanfu = round($difference / (60 * 60 * 24));
            return $playerInfo;
        } catch(Exception $e) {
            return null;
        }
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
        $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $gameServers = array();
        $i = 0;
        foreach ($servers as $gs) {
            $gameServers[$i] = new GameServerVO();
            $gameServers[$i]->id = $gs['id'];
            $gameServers[$i]->name = $gs['name'];
            $gameServers[$i]->url = $gs['url'];
            $gameServers[$i]->port = $gs['port'];
            $gameServers[$i]->playercount = $gs['playerCount'];
            $i++;
        }
        return $gameServers;
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
     * Register a user with the data provided in the registerVO
     * @author Altro50 <altro50@msn.com>
     * @param registerVO $registerVO Registration Data
     * @return boolean
     */
    public static function registerUserWithVo($registerVO)
    {
        if(isset($registerVO->_explicitType)) {
            if ($registerVO->_explicitType == "com.pandaland.mvc.model.vo.RegisterVO" && $registerVO->pwParents === "..7654..") {
                $username = (string)$registerVO->name;
                $password = (string)password_hash($registerVO->pw, PASSWORD_BCRYPT);
                $email = (string)$registerVO->emailParents;
                $sex = (int)($registerVO->sex == "girl");

                if(Panfu::usernameAcceptable($username) && Panfu::usernameNotTaken($username)) {
                    $pdo = Database::getPDO();
                    $insert = $pdo->prepare("INSERT INTO users (username, password, email, sex, signupDate) VALUES (:username, :password, :email,:sex, :signupDate)");
                    $insert->bindParam(":username", $username);
                    $insert->bindParam(":password", $password);
                    $insert->bindParam(":email", $email);
                    $insert->bindParam(":sex", $sex);
                    $currentTime = time();
                    $insert->bindParam(":signupDate", $currentTime);
                    $result = $insert->execute();
                    return ($result === 1);
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
        $checkStmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $checkStmt->bindParam(":username", $username, PDO::PARAM_INT);
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
                    Console::log($username . " contains the forbidden word: " . $forbiddenWord);
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
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
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
        $userData = $userStatement->fetch(PDO::FETCH_ASSOC);
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
            $userStatement = $pdo->prepare("SELECT * FROM users WHERE username = :username");
            $userStatement->bindParam(":username", $username, PDO::PARAM_INT);
            $userStatement->execute();
            $userData = $userStatement->fetch(PDO::FETCH_ASSOC);
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
        $statement = $pdo->prepare("SELECT * FROM states WHERE playerId = :id");
        $statement->bindParam(":id", $_SESSION['id']);
        $statement->execute();
        $i = 0;
        if($statement->rowCount() > 0) {
            foreach($statement as $state) {
                if(in_array($state['category'], $states)) {
                    $states[$i] = new StateVO();
                    $states[$i]->playerId = $_SESSION['id'];
                    $states[$i]->cathegoryId = $state['category'];
                    $states[$i]->nameId = $state['name'];
                    $states[$i]->stateValue = $state['value'];
                    $states[$i]->lastChanged = $state['lastChanged'] * 1000000;
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
            $update = $pdo->prepare("UPDATE states SET value = :value, lastChanged = :lastChanged WHERE playerId = :playerId AND category = :category AND name = :name");
            $update->bindParam(":value", $value);
            $update->bindParam(":lastChanged", $timestamp);
            $update->bindParam(":playerId", $_SESSION["id"]);
            $update->bindParam(":category", $category);
            $update->bindParam(":name", $name);
            $update->execute();
        } else {
            $insert = $pdo->prepare("INSERT INTO states (value,lastChanged,playerId,category,name) VALUES (:value, :lastChanged, :playerId, :category, :name)");
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
        $state->lastChanged = $timestamp * 1000000;
        return $state;
    }

    public static function stateExists($category, $name)
    {
        $pdo = Database::getPDO();
        $statement = $pdo->prepare("SELECT * FROM states WHERE playerId = :id AND category = :category AND name = :name");
        $statement->bindParam(":id", $_SESSION['id'], PDO::PARAM_INT);
        $statement->bindParam(":category", $category, PDO::PARAM_INT);
        $statement->bindParam(":name", $name, PDO::PARAM_INT);
        $statement->execute();
        return ($statement->rowCount() > 0);
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