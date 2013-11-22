<?php
/**
 * Created by Miljenko Rebernisak <miljenko.rebernisak@prelovac.com>.
 * Version: 1.0.0
 */

namespace ChurnBee\Library;

class CBConf
{

    /**
     * Your access token for comunication
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
     * Name and path of debuging file
     */

    private $debugFile = "cb.log";


    /**
     * Use parallel mode of execution. After calling event new process will be opened and execution will be in parallel.
     * You will need to call finish() at the end of your script.
     *
     */

    private $parallel = false;

    /**
     * Three modes
     * -silent, you need to call getErrors() and hasErrors()
     * -exception, exception will be thrown
     * -callback, user function will be called
     */

    private $errorHandling = self::E_SILENT;


    /**
     * Used in parallel mode. Maximum time to wait for process to finish. Set it to higher than curl time out.
     * Value is micro seconds
     */
    private $streamTimeout = 4000000;

    /**
     * When set to true after calling event request will me performed immediately.
     * If you set to false, explicit call to flush() is needed
     */
    private $autoFlush = true;


    /**
     * Set path to php
     */
    private $phpPath = "php";

    /*
     * @var callback object
     */
    private $callObj;

    /**
     * @var callback method
     */
    private $callMethod;

    /**
     * Max read buffer from stream
     */
    private $maxRead = 8192;


    #### Do not edit ####

    const E_SILENT = 0;
    const E_EXCEPTION = 1;
    const E_CALLBACK = 2;

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
     * @param mixed $maxRead
     */
    public function setMaxRead($maxRead)
    {
        $this->maxRead = $maxRead;
    }

    /**
     * @return mixed
     */
    public function getMaxRead()
    {
        return $this->maxRead;
    }

    /**
     * @param mixed $parallel
     */
    public function setParallel($parallel)
    {
        $this->parallel = $parallel;
    }

    /**
     * @return mixed
     */
    public function getParallel()
    {
        return $this->parallel;
    }

    /**
     * @param mixed $phpPath
     */
    public function setPhpPath($phpPath)
    {
        $this->phpPath = $phpPath;
    }

    /**
     * @return mixed
     */
    public function getPhpPath()
    {
        return $this->phpPath;
    }

    /**
     * @param mixed $streamTimeout
     */
    public function setStreamTimeout($streamTimeout)
    {
        $this->streamTimeout = $streamTimeout;
    }

    /**
     * @return mixed
     */
    public function getStreamTimeout()
    {
        return $this->streamTimeout;
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