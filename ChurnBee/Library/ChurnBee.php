<?php
/**
 * Created by Miljenko Rebernisak <miljenko.rebernisak@prelovac.com>.
 * Version: 1.0.0
 */

namespace ChurnBee\Library;

if (!function_exists('curl_init')) {
    throw new \Exception('ChurnBee needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
    throw new \Exception('ChurnBee needs the JSON PHP extension.');
}

/**
 * Class ChurnBee
 * @package ChurnBee
 */
class ChurnBee
{
    /**
     * @var CBConf configuration object
     */
    private $cbconf;

    ######## DO NOT EDIT BELLOW #############


    const CODE_OK = 200;
    const CODE_NO_CONTENT = 204;
    const CODE_CREATED = 201;
    const CODE_UNAUTHORIZED = 401;
    const CODE_BADREQUEST = 400;


    /**
     * @var null | array
     */
    private $result = array();
    /**
     * @var string
     */
    private $rawResult = array();
    /**
     * @var array
     */
    private $headers = array();

    /**
     * @var array
     */
    private $sentHeaders = array();

    /**
     * @var array
     */
    private $errors = array();

    /**
     * @var int
     */
    private $statusCode = array();

    /**
     * Request queue
     * @var array
     */

    private $queue = array();

    /**
     * Parallel request stuff
     */

    private $pipes = array();
    private $process = array();
    private $streams = array();


    /**
     *
     * @param array | CBConf $data
     */
    public function __construct($data = null)
    {

        if (is_array($data)) {
            $this->cbconf = new CBConf($data);
        } elseif ($data instanceof CBConf) {
            $this->cbconf = $data;
        } else {
            $this->cbconf = new CBConf();
        }


    }

    /**
     * Static constructor / factory
     * @param array | CBConf $data
     * @return ChurnBee
     *
     */
    public static function create($data = null)
    {
        $instance = new self($data);

        return $instance;
    }

    /**
     * Perform request and populate data from response
     * @throws ChurnBeeException
     */
    public function flush()
    {

        if ($this->cbconf->getParallel() == false) {
            foreach ($this->queue as $k => $r) {
                $r[0] = $this->appendTo($r[0], "accessToken", $this->cbconf->getAccessToken());
                $curl = new CurlUtil($r[1], $r[0]);
                $curl->setTimeOut($this->cbconf->getCurlTimeout());
                $curl->send();
                $this->splitHeaderAndResponse($k, $curl->getResult());
                $this->sentHeaders[$k] = $curl->getSentHeaders();
                $this->statusCode[$k] = $curl->getStatusCode();
                $this->addError($k, $curl->getError());

            }
            $this->handleErrors();
            $this->debug();
            $this->clearQueue();

        } else {
            foreach ($this->queue as $k => $r) {
                $r[0] = $this->appendTo($r[0], "accessToken", $this->cbconf->getAccessToken());
                $this->flushAsync($k, $r[1], $r[0]);
            }
        }

    }

    /**
     * Black magic happen hear, don't touch
     */
    private function flushAsync($k, $method, $url)
    {
        //stdin,stdout,stderr
        $descriptor = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
        //Open the resource to execute $command
        $command = $this->cbconf->getPhpPath() . " " . __DIR__ . "/async.php";
        $this->process[$k] = proc_open($command, $descriptor, $this->pipes[$k], __DIR__);

        if (!is_resource($this->process[$k])) {
            $this->addError($k, "Can not create new process");

            return;
        }


        //Set STDOUT and STDERR to non-blocking
        stream_set_blocking($this->pipes[$k][1], 0);
        stream_set_blocking($this->pipes[$k][2], 0);

        fwrite($this->pipes[$k][0], $this->cbconf->getCurlTimeout() . "\n");
        fwrite($this->pipes[$k][0], $method . "\n");
        fwrite($this->pipes[$k][0], $url . "\n");
        fclose($this->pipes[$k][0]);


    }

    /**
     * This method should be called in parallel mode to get data.
     * Typically you want to call this when you finish your processing and page generation
     */
    public function finish()
    {

        if ($this->cbconf->getParallel()) {
            foreach ($this->queue as $k => $v) {
                $buffers = array();
                //stdout,stderr
                $this->streams[$k] = array(1, 2);
                $buffers[$k][1] = "";
                $buffers[$k][2] = "";
                $code = null;

                $status = proc_get_status($this->process[$k]);
                if ($status === false) {
                    $this->addError($k, "Can not get process info");
                    continue;
                }

                while (!empty($this->streams[$k])) {
                    //Check pipes for data
                    foreach ($this->streams[$k] as $i => $pipe) {
                        $read = array($this->pipes[$k][$pipe]);
                        $write = null;
                        $except = null;
                        $tv = null;
                        $utv = $this->cbconf->getStreamTimeout();

                        $n = stream_select($read, $write, $except, $tv, $utv);
                        $j = 0;
                        if ($n > 0) {
                            do {
                                $str = fgets($this->pipes[$k][$pipe], $this->cbconf->getMaxRead());
                                if ($j == 0 && $pipe == 1) {
                                    $this->statusCode[$k] = $str;
                                } else {
                                    $buffers[$k][$pipe] .= $str;
                                }

                                $j++;

                            } while (strlen($str));

                        }

                        unset($this->streams[$k][$i]);
                    }
                }
                // Close all pipes
                foreach ($this->pipes[$k] as $pipe => $desc) {
                    if (is_resource($desc)) {
                        fclose($desc);
                    }
                }

                $this->splitHeaderAndResponse($k, $buffers[$k][1]);
                $this->addError($k, array($buffers[$k][2]));
            }

            $this->handleErrors();
            $this->debug();
            $this->clearQueue();
        }

    }

    /**
     * Do error handling
     * @throws ChurnBeeException
     */
    protected function handleErrors()
    {

        if (is_array($this->result)) {
            foreach ($this->result as $k => $v) {
                if ($this->statusCode[$k] != self::CODE_OK && count($this->errors[$k]) == 0) {
                    if (isset($v->error)) {
                        $this->addError($k, $v->error);
                    }
                }
            }
        }

        if ($this->cbconf->getErrorHandling() == CBConf::E_EXCEPTION) {

            $buffer = "";
            foreach ($this->errors as $k => $v) {
                if (count($v) > 0) {
                    $buffer .= implode(";", $v);
                }
            }

            if (strlen($buffer) > 0) {
                throw new ChurnBeeException($buffer);
            }

        } elseif ($this->cbconf->getErrorHandling() == CBConf::E_CALLBACK) {

            foreach ($this->errors as $k => $v) {
                if (count($v) > 0) {
                    $this->callUserHandler(
                        $this->statusCode[$k],
                        $this->result[$k],
                        $this->rawResult[$k],
                        $v
                    );
                }

            }
        }


    }

    /**
     * Clear queue after requests finish
     */
    private function clearQueue()
    {
        $this->queue = array();
    }

    private function debug()
    {

        if ($this->cbconf->getDebug()) {
            foreach ($this->queue as $k => $r) {
                if ($this->cbconf->getParallel() == false) {
                    $this->log("\r\n" . "[SENT REQUEST]\r\n");
                    $this->log($this->sentHeaders[$k] . "\r\n");
                }
                $this->log("[RECEIVED RESPONSE]\r\n");
                $this->log(implode("\r\n", $this->headers[$k]) . "\r\n");
                $this->log($this->rawResult[$k]);
            }

        }
    }


    protected function callUserHandler($statuscode, $result, $rawresult, $errors)
    {
        if ($this->cbconf->getCallObj() != null && $this->cbconf->getCallMethod() != null) {
            call_user_func(
                array($this->cbconf->getCallObj(), $this->cbconf->getCallMethod()),
                $statuscode,
                $result,
                $rawresult,
                $errors
            );
        }
    }

    /**
     * Append to url
     * @param $url
     * @param $key
     * @param $value
     * @return string
     */
    protected function  appendTo($url, $key, $value)
    {
        if (preg_match("/\?/", $url)) {
            $url .= "&";
        } else {
            $url .= "?";
        }

        $url .= $key . "=" . urlencode($value);

        return $url;
    }

    /**
     * Populate  $rawResult, $result, $headers from curl response
     * @param $k
     * @param $response
     */
    protected function splitHeaderAndResponse($k, $response)
    {
        $this->headers[$k] = array();
        $divider = strpos($response, "\r\n\r\n");
        if (false !== $divider) {
            $header_text = substr($response, 0, $divider);
            foreach (explode("\r\n", $header_text) as $i => $line) {
                if ($i > 0) {
                    list ($key, $value) = explode(': ', $line);
                    $this->headers[$k][$key] = $value;
                }
            }
            $this->rawResult[$k] = substr($response, $divider, strlen($response));
            $this->result[$k] = json_decode($this->rawResult[$k]);
            if (json_last_error() || null == $this->result[$k]) {
                $this->addError($k, "Invalid JSON");
            }
        }

    }

    /**
     * Add error to que for current request
     * @param $k
     * @param $error
     */
    protected function addError($k, $error)
    {
        if (!isset($this->errors[$k])) {
            $this->errors[$k] = array();
        }
        if (is_array($error)) {
            foreach ($error as $e) {
                $this->errors[$k][] = $e;
            }
        } else {
            $this->errors[$k][] = $error;
        }
    }

    /**
     * Implode key/value array to string, and do url encode
     * @param $arr
     * @return string
     */
    protected function implode_encode_assoc($arr)
    {
        $ret_str = "";

        $char = "&";
        foreach ($arr as $k => $v) {
            $ret_str .= $char . "custom[" . $k . "]=" . urlencode($v);
        }

        return $ret_str;
    }

    /**
     * Returns array with headers data
     * @return array
     */
    public function getHeaders()
    {

        return $this->headers;
    }

    /**
     * Return object if request succeeded and there
     * was not errors in json output.
     * @return array
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Return array of errors string.
     * May contain errors from curl and from response
     * eq. Wrong protocol or Supplied id does not exist
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;

    }

    /**
     * @param mixed $cbconf
     */
    public function setCbconf($cbconf)
    {
        $this->cbconf = $cbconf;
    }

    /**
     * @return mixed
     */
    public function getCbconf()
    {
        return $this->cbconf;
    }

    /**
     * Clear errors stack. Useful if performing multiple request from single instance
     *
     */
    public function clearErrors()
    {
        $this->errors = array();
    }

    /**
     * Write message to log file
     *
     * @param $message
     */
    protected function log($message)
    {
        $date = new \DateTime();
        file_put_contents(
            $this->cbconf->getDebugFile(),
            "[" . $date->format(\DateTime::ISO8601) . "]" . $message,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Return HTTP status code from  requests
     *
     * @return array of int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }


    /**
     * Check if there is errors on stack
     * @return bool
     */
    public function haveErrors()
    {

        foreach ($this->errors as $e) {
            if (count($e) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return raw output from server. It is always array of string
     * @return array
     */
    public function getRawResult()
    {
        return $this->rawResult;
    }


    /**
     * Format date to ISO8601 string.
     * Input can be timestamp,\DateTime object or already formatted string
     * @param $dateTime
     * @return string
     */
    protected function formatDate($dateTime)
    {
        if ($dateTime instanceof  \DateTime) {
            return $dateTime->format(\DateTime::ISO8601);

        } else {
            if (is_numeric($dateTime)) {
                $date = new \DateTime();
                $date->setTimestamp($dateTime);

                return $date->format(\DateTime::ISO8601);

            } else {
                return $dateTime;
            }

        }
    }

    /**
     * Send registration request to ChurnBee server.
     * Mandatory field is $userId
     * Plan will be set to "free" if no specified.
     * dateTime is optional and can be timestamp or \DateTime() object
     *
     * @param string $userId
     * @param null $plan
     * @param null $dateTime
     * @param array $custom
     */
    public function register($userId, $plan = null, $dateTime = null, $custom = array())
    {
        $data = array();
        $data["userId"] = $userId;

        if (!empty($plan)) {
            $data['plan'] = $plan;
        }

        if ($dateTime != null) {
            $data['dateTime'] = $this->formatDate($dateTime);
        }

        if (is_array($custom) && !empty($custom)) {
            $data['custom'] = $custom;
        }

        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/";
        if (!empty($plan)) {
            $url = $this->appendTo($url, "plan", $plan);
        }
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request[0] = $url;
        $request[1] = CurlUtil::METHOD_GET;


        $this->queue[] = $request;

        if ($this->cbconf->getAutoFlush() == true) {
            $this->flush();
        }

    }

    /**
     * Send login request to ChurnBee server.
     * Mandatory field is $userId
     * dateTime is optional and can be timestamp or \DateTime() object
     *
     * @param string $userId
     * @param null $dateTime
     * @param array $custom
     */
    public function login($userId, $dateTime = null, $custom = array())
    {
        $data = array();

        if ($dateTime != null) {
            $data['dateTime'] = $this->formatDate($dateTime);
        }
        if (is_array($custom) && !empty($custom)) {
            $data['custom'] = $custom;
        }
        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/login";
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request[0] = $url;
        $request[1] = CurlUtil::METHOD_GET;


        $this->queue[] = $request;

        if ($this->cbconf->getAutoFlush()) {
            $this->flush();
        }

    }

    /**
     * Send cancellation request to ChurnBee server.
     * Mandatory field is $userId
     * Reason is optional.
     * dateTime is optional and can be timestamp or \DateTime() object
     *
     * @param string $userId
     * @param null $reason
     * @param null $dateTime
     * @param array $custom
     */
    public function cancel($userId, $reason = null, $dateTime = null, $custom = array())
    {

        $data = array();

        if (!empty($reason)) {
            $data['reason'] = $reason;
        }
        if ($dateTime != null) {
            $data['dateTime'] = $this->formatDate($dateTime);
        }
        if (is_array($custom) && !empty($custom)) {
            $data['custom'] = $custom;
        }

        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/cancel";
        if (!empty($reason)) {
            $url = $this->appendTo($url, "reason", $reason);
        }
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request[0] = $url;
        $request[1] = CurlUtil::METHOD_GET;

        $this->queue[] = $request;

        if ($this->cbconf->getAutoFlush()) {
            $this->flush();
        }

    }

    /**
     * Send changePlan request to ChurnBee server.
     * Mandatory field is $userId and $plan
     * dateTime is optional and can be timestamp or \DateTime() object
     *
     * @param string $userId
     * @param string $plan
     * @param null $dateTime
     * @param array $custom
     */
    public function changePlan($userId, $plan, $dateTime = null, $custom = array())
    {

        $data = array();
        $data['plan'] = $plan;
        if ($dateTime != null) {
            $data['dateTime'] = $this->formatDate($dateTime);
        }
        if (is_array($custom) && !empty($custom)) {
            $data['custom'] = $custom;
        }
        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/changeplan";
        if (!empty($plan)) {
            $url = $this->appendTo($url, "plan", $plan);
        }
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request[0] = $url;
        $request[1] = CurlUtil::METHOD_GET;


        $this->queue[] = $request;

        if ($this->cbconf->getAutoFlush()) {
            $this->flush();
        }

    }


    /**
     * Send charge request to ChurnBee server.
     * Mandatory field is $userId, $amount and $plan
     * dateTime is optional and can be timestamp or \DateTime() object
     * duration is optional and represent number of months that you are charging eg 6,12,24
     *
     * @param $userId
     * @param $amount
     * @param $plan
     * @param int $duration
     * @param null $dateTime
     * @param array $custom
     */
    public function charge($userId, $amount, $plan, $duration = 1, $dateTime = null, $custom = array())
    {

        $data = array();
        $data['amount'] = $amount;
        $data['plan'] = $plan;
        $data['duration'] = $duration;
        if ($dateTime != null) {
            $data['dateTime'] = $this->formatDate($dateTime);
        }
        if (is_array($custom) && !empty($custom)) {
            $data['custom'] = $custom;
        }
        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/charge";
        if (!empty($amount)) {
            $url = $this->appendTo($url, "amount", $amount);
        }
        if (!empty($plan)) {
            $url = $this->appendTo($url, "plan", $plan);
        }
        if (!empty($duration)) {
            $url = $this->appendTo($url, "duration", $duration);
        }
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request[0] = $url;
        $request[1] = CurlUtil::METHOD_GET;


        $this->queue[] = $request;

        if ($this->cbconf->getAutoFlush()) {
            $this->flush();
        }
    }


    /**
     * Send refund request to ChurnBee server.
     * Mandatory field is $userId and  $amount
     * dateTime is optional and can be timestamp or \DateTime() object
     * duration is optional and represent number of month that you are refunding
     *
     * @param string $userId
     * @param float $amount
     * @param int $duration
     * @param null $dateTime
     * @param array $custom
     */
    public function refund($userId, $amount,$duration = 1, $dateTime = null, $custom = array())
    {

        $data = array();
        $data['amount'] = $amount;
        $data['duration'] = $duration;

        if ($dateTime != null) {
            $data['dateTime'] = $this->formatDate($dateTime);
        }
        if (is_array($custom) && !empty($custom)) {
            $data['custom'] = $custom;
        }

        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/refund";
        if (!empty($amount)) {
            $url = $this->appendTo($url, "amount", $amount);
        }
        if (!empty($duration)) {
            $url = $this->appendTo($url, "duration", $duration);
        }
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request[0] = $url;
        $request[1] = CurlUtil::METHOD_GET;


        $this->queue[] = $request;

        if ($this->cbconf->getAutoFlush()) {
            $this->flush();
        }
    }


    /**
     * Send activation event for your product to ChurnBee server.
     * Mandatory field is $userId
     * dateTime is optional and can be timestamp or \DateTime() object
     *
     *
     * @param string $userId
     * @param null $dateTime
     * @param array $custom
     */
    public function activation($userId, $dateTime = null, $custom = array())
    {

        $data = array();

        if ($dateTime != null) {
            $data['dateTime'] = $this->formatDate($dateTime);
        }
        if (is_array($custom) && !empty($custom)) {
            $data['custom'] = $custom;
        }

        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/activation";
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request[0] = $url;
        $request[1] = CurlUtil::METHOD_GET;


        $this->queue[] = $request;

        if ($this->cbconf->getAutoFlush()) {
            $this->flush();
        }
    }


}
