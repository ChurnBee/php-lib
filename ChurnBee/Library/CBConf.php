<?php
/**
 * Created by Miljenko Rebernisak <miljenko.rebernisak@prelovac.com>.
 * Version: 1.0.1
 */

namespace ChurnBee\Library;

/**
 * Class CBConf
 * @package ChurnBee\Library
 */
class CBConf
{

    /**
     * Your access token for communication
     */
    private $accessToken = "";

    /**
     * Base url of api
     */
    private $apiUrl = "https://api.churnbee.com/v1/";

    /**
     * Maximum time out for curl connection
     */
    private $curlTimeout = 3;


    /**
     * Enable logging requests to file
     */
    private $debug = false;

    /**
     * Name and path of debugging file
     */

    private $debugFile = "cb.log";

    /**
     * Use async mode of execution. You need command shell and unix like system
     */
    private $async = false;

    /**
     * Three modes
     * -silent, you need to call getErrors() and hasErrors()
     * -exception, exception will be thrown
     * -callback, user function will be called
     */

    private $errorHandling = self::E_SILENT;

    /**
     * When set to true after calling event request will me performed immediately.
     * If you set to false, explicit call to flush() is needed
     */
    private $autoFlush = true;

    /**
     * @var callback object
     */
    private $callObj;

    /**
     * @var callback method
     */
    private $callMethod;


    const E_SILENT = 0;
    const E_EXCEPTION = 1;
    const E_CALLBACK = 2;

    /**
     * @param array $data
     */
    public function __construct($data = array())
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }


    /**
     * @param mixed $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @return mixed
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param mixed $apiUrl
     */
    public function setApiUrl($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * @return mixed
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * @param mixed $autoFlush
     */
    public function setAutoFlush($autoFlush)
    {
        $this->autoFlush = $autoFlush;
    }

    /**
     * @return mixed
     */
    public function getAutoFlush()
    {
        return $this->autoFlush;
    }

    /**
     * @return bool
     */
    public function isAutoFlush()
    {
        return $this->autoFlush;
    }

    /**
     * @param mixed $curlTimeout
     */
    public function setCurlTimeout($curlTimeout)
    {
        $this->curlTimeout = $curlTimeout;
    }

    /**
     * @return mixed
     */
    public function getCurlTimeout()
    {
        return $this->curlTimeout;
    }

    /**
     * @param mixed $debugFile
     */
    public function setDebugFile($debugFile)
    {
        $this->debugFile = $debugFile;
    }

    /**
     * @return mixed
     */
    public function getDebugFile()
    {
        return $this->debugFile;
    }

    /**
     * @param mixed $errorHandling
     */
    public function setErrorHandling($errorHandling)
    {
        $this->errorHandling = $errorHandling;
    }

    /**
     * @return mixed
     */
    public function getErrorHandling()
    {
        return $this->errorHandling;
    }

    /**
     * @param mixed $async
     */
    public function setAsync($async)
    {
        $this->async = $async;
    }

    /**
     * @return mixed
     */
    public function getAsync()
    {
        return $this->async;
    }

    /**
     * @return bool
     */
    public function isAsync()
    {
        return $this->async;
    }

    /**
     * @param mixed $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @return mixed
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @param callable $callMethod
     */
    public function setCallMethod($callMethod)
    {
        $this->callMethod = $callMethod;
    }

    /**
     * @return callable
     */
    public function getCallMethod()
    {
        return $this->callMethod;
    }

    /**
     * @param mixed $callObj
     */
    public function setCallObj($callObj)
    {
        $this->callObj = $callObj;
    }

    /**
     * @return mixed
     */
    public function getCallObj()
    {
        return $this->callObj;
    }


}