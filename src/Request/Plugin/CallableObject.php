<?php

/**
 * CallableObject.php - Xajax callable object plugin
 *
 * This class registers user defined callable objects, generates client side javascript code,
 * and calls their methods on user request
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

namespace Xajax\Request\Plugin;

use Xajax\Xajax;
use Xajax\Plugin\Request as RequestPlugin;
use Xajax\Plugin\Manager as PluginManager;
use Xajax\Request\Manager as RequestManager;

class CallableObject extends RequestPlugin
{
    use \Xajax\Utils\ContainerTrait;

    /**
     * The registered callable objects
     *
     * @var array
     */
    protected $aCallableObjects;

    /**
     * The classpaths of the registered callable objects
     *
     * @var array
     */
    protected $aClassPaths;

    /**
     * The value of the class parameter of the incoming Xajax request
     *
     * @var string
     */
    protected $sRequestedClass;
    
    /**
     * The value of the method parameter of the incoming Xajax request
     *
     * @var string
     */
    protected $sRequestedMethod;

    public function __construct()
    {
        $this->aCallableObjects = array();
        $this->aClassPaths = array();

        $this->sRequestedClass = null;
        $this->sRequestedMethod = null;

        if(!empty($_GET['xjxcls']))
        {
            $this->sRequestedClass = $_GET['xjxcls'];
        }
        if(!empty($_GET['xjxmthd']))
        {
            $this->sRequestedMethod = $_GET['xjxmthd'];
        }
        if(!empty($_POST['xjxcls']))
        {
            $this->sRequestedClass = $_POST['xjxcls'];
        }
        if(!empty($_POST['xjxmthd']))
        {
            $this->sRequestedMethod = $_POST['xjxmthd'];
        }
    }

    /**
     * Return the name of this plugin
     *
     * @return string
     */
    public function getName()
    {
        return 'CallableObject';
    }

    /**
     * Register a user defined callable object
     *
     * @param array         $aArgs                An array containing the callable object specification
     *
     * @return array
     */
    public function register($aArgs)
    {
        if(count($aArgs) > 1)
        {
            $sType = $aArgs[0];

            if($sType == Xajax::CALLABLE_OBJECT)
            {
                $xCallableObject = $aArgs[1];

                if(!is_object($xCallableObject))
                {
                    throw new \Xajax\Exception\Error('errors.objects.instance');
                }
                if(!($xCallableObject instanceof \Xajax\Request\Support\CallableObject))
                {
                    $xUserCallable = $xCallableObject;
                    $xCallableObject = new \Xajax\Request\Support\CallableObject($xCallableObject);
                    // Save the Xajax callable object into the user callable object
                    if(method_exists($xUserCallable, 'setXajaxCallable'))
                    {
                        $xUserCallable->setXajaxCallable($xCallableObject);
                    }
                    // Save the global Xajax response into the user callable object
                    if(method_exists($xUserCallable, 'setGlobalResponse'))
                    {
                        $xUserCallable->setGlobalResponse();
                    }
                }
                if(count($aArgs) > 2 && is_array($aArgs[2]))
                {
                    foreach($aArgs[2] as $sKey => $aValue)
                    {
                        foreach($aValue as $sName => $sValue)
                        {
                            if($sName == 'classpath' && $sValue != '')
                                $this->aClassPaths[] = $sValue;
                            $xCallableObject->configure($sKey, $sName, $sValue);
                        }
                    }
                }
                $this->aCallableObjects[$xCallableObject->getName()] = $xCallableObject;

                return $xCallableObject->generateRequests();
            }
        }

        return false;
    }

    /**
     * Generate a hash for the registered callable objects
     *
     * @return string
     */
    public function generateHash()
    {
        $sHash = '';
        foreach($this->aCallableObjects as $xCallableObject)
        {
            $sHash .= $xCallableObject->getName();
            $sHash .= implode('|', $xCallableObject->getMethods());
        }
        return md5($sHash);
    }

    /**
     * Generate client side javascript code for the registered callable objects
     *
     * @return string
     */
    public function getScript()
    {
        $sXajaxPrefix = $this->getOption('core.prefix.class');
        // Generate code for javascript classes declaration
        $code = '';
        $classes = array();
        foreach($this->aClassPaths as $sClassPath)
        {
            $offset = 0;
            $sClassPath .= '.Null'; // This is a sentinel. The last token is not processed in the while loop.
            while(($dotPosition = strpos($sClassPath, '.', $offset)) !== false)
            {
                $class = substr($sClassPath, 0, $dotPosition);
                // Generate code for this class
                if(!array_key_exists($class, $classes))
                {
                    $code .= "$sXajaxPrefix$class = {};\n";
                    $classes[$class] = $class;
                }
                $offset = $dotPosition + 1;
            }
        }
        $classes = null;

        foreach($this->aCallableObjects as $xCallableObject)
        {
            $code .= $xCallableObject->getScript();
        }
        return $code;
    }

    /**
     * Check if this plugin can process the incoming Xajax request
     *
     * @return boolean
     */
    public function canProcessRequest()
    {
        // Check the validity of the class name
        if(($this->sRequestedClass) && !$this->validateClass($this->sRequestedClass))
        {
            $this->sRequestedClass = null;
            $this->sRequestedMethod = null;
        }
        // Check the validity of the method name
        if(($this->sRequestedMethod) && !$this->validateMethod($this->sRequestedMethod))
        {
            $this->sRequestedClass = null;
            $this->sRequestedMethod = null;
        }
        return ($this->sRequestedClass != null && $this->sRequestedMethod != null);
    }

    /**
     * Process the incoming Xajax request
     *
     * @return boolean
     */
    public function processRequest()
    {
        if(!$this->canProcessRequest())
            return false;

        $aArgs = RequestManager::getInstance()->process();

        // Try to register an instance of the requested class, if it isn't yet
        if(!array_key_exists($this->sRequestedClass, $this->aCallableObjects))
        {
            PluginManager::getInstance()->registerClass($this->sRequestedClass);
        }

        if(array_key_exists($this->sRequestedClass, $this->aCallableObjects))
        {
            $xCallableObject = $this->aCallableObjects[$this->sRequestedClass];
            if($xCallableObject->hasMethod($this->sRequestedMethod))
            {
                $xCallableObject->call($this->sRequestedMethod, $aArgs);
                return true;
            }
        }
        // Unable to find the requested object or method
        throw new \Xajax\Exception\Error('errors.objects.invalid',
            array('class' => $this->sRequestedClass, 'method' => $this->sRequestedMethod));
    }

    /**
     * Find a user registered callable object by class name
     *
     * @param string        $sClassName            The class name of the callable object
     *
     * @return object
     */
    public function getRegisteredObject($sClassName = null)
    {
        $sClassName = (string)$sClassName;
        if(!$sClassName)
        {
            $sClassName = $this->sRequestedClass;
        }
        if(!array_key_exists($sClassName, $this->aCallableObjects))
        {
            return null;
        }
        return $this->aCallableObjects[$sClassName]->getRegisteredObject();
    }
}
