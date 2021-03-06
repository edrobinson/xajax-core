<?php

/**
 * Php.php - Xajax config reader
 *
 * Read the config data from a PHP config file, save it locally
 * using the Config class, and then set the options in the library.
 *
 * @package xajax-core
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2016 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause License
 * @link https://github.com/lagdo/xajax-core
 */

namespace Xajax\Config;

class Php
{
    /**
     * Read and set Xajax options from a PHP config file
     *
     * @param array         $sConfigFile        The full path to the config file
     * @param string        $sKeys                The keys of the options in the file
     *
     * @return void
     */
    public static function read($sConfigFile, $sKey = '')
    {
        $sConfigFile = realpath($sConfigFile);
        if(!is_readable($sConfigFile))
        {
            throw new \Xajax\Exception\Config\File('access', $sConfigFile);
        }
        $aConfigOptions = include($sConfigFile);
        if(!is_array($aConfigOptions))
        {
            throw new \Xajax\Exception\Config\File('content', $sConfigFile);
        }

        // Content read from config file. Try to parse.
        Config::setOptions($aConfigOptions, $sKey);
    }
}
