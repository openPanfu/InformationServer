<?php
/**
 * This file is part of openPanfu, a project that imitates the Flex remoting
 * and gameservers of Panfu.
 *
 * @category Utility
 * @author Altro50 <altro50@msn.com>
 */

Class Console
{
    public static $enabled = true;
    public static function log()
    {
        if(Console::$enabled) {
            $args = func_get_args();
            if(count($args) > 0) {
                foreach($args as $arg) {
                    if(is_object($arg) || is_array($arg) || is_resource($arg)) {
                        $output = print_r($arg, true);
                    } else {
                        $output = (string) $arg;
                    }
                    file_put_contents('php://stdout', "\033[0m\033[34m\e[1m[CONSOLE] " . $output . "\033[0m" . PHP_EOL);
                }
            }
        }
    }
}