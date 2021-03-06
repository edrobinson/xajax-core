<?php

/**
 * Manager.php - Xajax Request Manager
 *
 * This class processes the input arguments from the GET or POST data of the request.
 * If this is a request for the initial page load, no arguments will be processed.
 * During a xajax request, any arguments found in the GET or POST will be converted to a PHP array.
 *
 * @package xajax-core
 * @author Jared White
 * @author J. Max Wilson
 * @author Joseph Woolley
 * @author Steffen Konerow
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright Copyright (c) 2005-2007 by Jared White & J. Max Wilson
 * @copyright Copyright (c) 2008-2010 by Joseph Woolley, Steffen Konerow, Jared White  & J. Max Wilson
 * @copyright 2016 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause License
 * @link https://github.com/lagdo/xajax-core
 */

namespace Xajax\Request;

use Xajax\Xajax;

class Manager
{
    use \Xajax\Utils\ContainerTrait;

    /**
     * An array of arguments received via the GET or POST parameter xjxargs.
     *
     * @var array
     */
    private $aArgs;
    
    /**
     * Stores the method that was used to send the arguments from the client.
     * Will be one of: Xajax::METHOD_UNKNOWN, Xajax::METHOD_GET, Xajax::METHOD_POST.
     *
     * @var integer
     */
    private $nMethod;
    
    /**
     * The constructor
     *
     * Get and decode the arguments of the HTTP request
     */
    private function __construct()
    {

        $this->aArgs = array();
        $this->nMethod = Xajax::METHOD_UNKNOWN;
        
        if(isset($_POST['xjxargs']))
        {
            $this->nMethod = Xajax::METHOD_POST;
            $this->aArgs = $_POST['xjxargs'];
        }
        else if(isset($_GET['xjxargs']))
        {
            $this->nMethod = Xajax::METHOD_GET;
            $this->aArgs = $_GET['xjxargs'];
        }
        if(get_magic_quotes_gpc() == 1)
        {
            array_walk($this->aArgs, array(&$this, '__argumentStripSlashes'));
        }
        array_walk($this->aArgs, array(&$this, '__argumentDecode'));
    }
    
    /**
     * Return the one and only instance of the Xajax request manager
     *
     * @return Manager
     */
    public static function getInstance()
    {
        static $xInstance = null;
        if(!$xInstance)
        {
            $xInstance = new Manager();    
        }
        return $xInstance;
    }
    
    /**
     * Converts a string to a boolean var
     *
     * @param string        $sValue                The string to be converted
     *
     * @return boolean
     */
    private function __convertStringToBool($sValue)
    {
        if(strcasecmp($sValue, 'true') == 0)
        {
            return true;
        }
        if(strcasecmp($sValue, 'false') == 0)
        {
            return false;
        }
        if(is_numeric($sValue))
        {
            if($sValue == 0)
            {
                return false;
            }
            return true;
        }
        return false;
    }
    
    /**
     * Strip the slashes from a string
     *
     * @param string        $sArg                The string to be stripped
     *
     * @return string
     */
    private function __argumentStripSlashes(&$sArg)
    {
        if(!is_string($sArg))
        {
            return '';
        }
        $sArg = stripslashes($sArg);
    }
    
    /**
     * Convert an Xajax request argument to its value
     *
     * Depending of its first char, the Xajax request argument is converted to a given type.
     *
     * @param string        $sValue                The keys of the options in the file
     *
     * @return mixed
     */
    private function __convertValue($sValue)
    {
        $cType = substr($sValue, 0, 1);
        $sValue = substr($sValue, 1);
        switch ($cType)
        {
            case 'S':
                $value = ($sValue === false ? '' : $sValue);
                break;
            case 'B':
                $value = $this->__convertStringToBool($sValue);
                break;
            case 'N':
                $value = ($sValue == floor($sValue) ? (int)$sValue : (float)$sValue);
                break;
            case '*':
                $value = null;
                break;
        }
        return $value;
    }

    /**
     * Decode and convert an Xajax request argument from JSON
     *
     * @param string        $sArg                The Xajax request argument
     *
     * @return mixed
     */
    private function __argumentDecode(&$sArg)
    {
        if($sArg == '')
        {
            return '';
        }

        $data = json_decode($sArg, true);

        if($data !== null && $sArg != $data)
        {
            $sArg = $data;
        }
        else
        {
            $sArg = $this->__convertValue($sArg);
        }
    }

    /**
     * Decode an Xajax request argument and convert to UTF8 with iconv
     *
     * @param string|array        $mArg                The Xajax request argument
     *
     * @return void
     */
    private function __argumentDecodeUTF8_iconv(&$mArg)
    {
        if(is_array($mArg))
        {
            foreach($mArg as $sKey => $xArg)
            {
                $sNewKey = $sKey;
                $this->__argumentDecodeUTF8_iconv($sNewKey);
                if($sNewKey != $sKey)
                {
                    $mArg[$sNewKey] = $xArg;
                    unset($mArg[$sKey]);
                    $sKey = $sNewKey;
                }
                $this->__argumentDecodeUTF8_iconv($xArg);
            }
        }
        else if(is_string($mArg))
        {
            $mArg = iconv("UTF-8", $this->getOption('core.encoding') . '//TRANSLIT', $mArg);
        }
    }
    
    /**
     * Decode an Xajax request argument and convert to UTF8 with mb_convert_encoding
     *
     * @param string|array        $mArg                The Xajax request argument
     *
     * @return void
     */
    private function __argumentDecodeUTF8_mb_convert_encoding(&$mArg)
    {
        if(is_array($mArg))
        {
            foreach($mArg as $sKey => $xArg)
            {
                $sNewKey = $sKey;
                $this->__argumentDecodeUTF8_mb_convert_encoding($sNewKey);
                if($sNewKey != $sKey)
                {
                    $mArg[$sNewKey] = $xArg;
                    unset($mArg[$sKey]);
                    $sKey = $sNewKey;
                }
                $this->__argumentDecodeUTF8_mb_convert_encoding($xArg);
            }
        }
        else if(is_string($mArg))
        {
            $mArg = mb_convert_encoding($mArg, $this->getOption('core.encoding'), "UTF-8");
        }
    }
    
    /**
     * Decode an Xajax request argument from UTF8
     *
     * @param string|array        $mArg                The Xajax request argument
     *
     * @return void
     */
    private function __argumentDecodeUTF8_utf8_decode(&$mArg)
    {
        if(is_array($mArg))
        {
            foreach($mArg as $sKey => $xArg)
            {
                $sNewKey = $sKey;
                $this->__argumentDecodeUTF8_utf8_decode($sNewKey);
                
                if($sNewKey != $sKey)
                {
                    $mArg[$sNewKey] = $xArg;
                    unset($mArg[$sKey]);
                    $sKey = $sNewKey;
                }
                
                $this->__argumentDecodeUTF8_utf8_decode($xArg);
            }
        }
        else if(is_string($mArg))
        {
            $mArg = utf8_decode($mArg);
        }
    }
    
    /**
     * Return the method that was used to send the arguments from the client
     *
     * The method is one of: Xajax::METHOD_UNKNOWN, Xajax::METHOD_GET, Xajax::METHOD_POST.
     *
     * @return integer
     */
    public function getRequestMethod()
    {
        return $this->nMethod;
    }
    
    /**
     * Return the array of arguments that were extracted and parsed from the GET or POST data
     *
     * @return array
     */
    public function process()
    {
        if(($this->getOption('core.decode_utf8')))
        {
            $sFunction = '';
            
            if(function_exists('iconv'))
            {
                $sFunction = "iconv";
            }
            else if(function_exists('mb_convert_encoding'))
            {
                $sFunction = "mb_convert_encoding";
            }
            else if($this->getOption('core.encoding') == "ISO-8859-1")
            {
                $sFunction = "utf8_decode";
            }
            else
            {
                throw new \Xajax\Exception\Error('errors.request.conversion');
            }

            $mFunction = array(&$this, '__argumentDecodeUTF8_' . $sFunction);
            array_walk($this->aArgs, $mFunction);
            $this->setOption('decodeUTF8Input', false);
        }
        
        return $this->aArgs;
    }
}
