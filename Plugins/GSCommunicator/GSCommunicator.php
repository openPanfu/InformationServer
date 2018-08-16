<?php

/**
 * This file is part of openPanfu, a project that imitates the Flex remoting
 * and gameservers of Panfu.
 *
 * @category Utility
 * @author Altro50 <altro50@msn.com>
 */

 class GSCommunicator
 {
    public static function checkConnection()
    {
        GSCommunicator::communicate("testConnection");
    }

    public static function communicate()
    {
        $servers = Panfu::getGameServers();
        if(isset($servers[0])) {
            $key = Panfu::getGameServerKey($servers[0]->id);
            $command = "900;$key";
            foreach (func_get_args() as $param) {
                $command .= ";$param";
            }
            $command .= "|";
        }
        $connection = fsockopen("tcp://" . $servers[0]->url . "", $servers[0]->port, $error, $errorStr);

        // Connection failed somehow.
        if(!$connection) {
            Console::log("An error occured while communicating message: $command to the gameserver.; $error: $errorStr");
            return;
        }

        fwrite($connection, $command);
        fclose($connection);
    }
 }