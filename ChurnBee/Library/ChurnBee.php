<?php
/**
 * Created by Miljenko Rebernisak <miljenko.rebernisak@churnbee.com>.
 * Version: 1.0.1
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
    const CODE_OK = 200;
    const CODE_NO_CONTENT = 204;
    const CODE_CREATED = 201;
    const CODE_UNAUTHORIZED = 401;
    const CODE_BADREQUEST = 400;

    /**
     * @var CBConf configuration object
     */
    private $cbconf;

    /**
     * @var null | array
     */
    private $result = array();
    /**
     * @var array
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
     * @var array
     */
    private $statusCode = array();
    /**
     * Request queue
     * @var array
     */
    private $queue = array();

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
     *
     * @param array | CBConf $data
     *
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
     *
     * @throws ChurnBeeException
     */
    public function flush()
    {
        if ($this->cbconf->getParallel() == false) {
            foreach ($this->queue as $k => $r) {
                $r['url'] = $this->appendTo($r['url'], "accessToken", $this->cbconf->getAccessToken());
                $curl = new CurlUtil($r['method'], $r['url']);
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
                $r['url'] = $this->appendTo($r['url'], "accessToken", $this->cbconf->getAccessToken());
                $this->flushAsync($k, $r['method'], $r['url']);
            }
        }
    }

    /**
     * Asynchronous flush
     */
    private function flushAsync($current, $method, $url)
    {
        # Escape for shell usage.
        $url = escapeshellarg($url);
        $cmd = "curl -X POST -H 'Content-Type: application/json'";
        $cmd .= '-A "ChurnBee Agent v1.0"';
        $cmd .= " '" . $url . "'";
        $cmd .= " > /dev/null 2>&1 &";

        exec($cmd, $output, $exit);

        if (0 != $exit) {
            $this->addError($current, $output);
        }
        $this->clearQueue();
    }

    /**
     * Do error handling
     *
     * @throws ChurnBeeException
     */
    protected function handleErrors()
    {
        /**
         * Add errors from json response
         */
        if (is_array($this->result)) {
            foreach ($this->result as $k => $v) {
                if ($this->statusCode[$k] != self::CODE_OK && count($this->errors[$k]) == 0 && isset($v->error)) {
                    $this->addError($k, $v->error);
                }
            }
        }
        /**
         * Check if we need to do callback or throw exception
         */
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

    /**
     * Write down debugging information
     */
    private function debug()
    {
        if ($this->cbconf->getDebug() && $this->cbconf->getParallel() == false) {
            foreach ($this->queue as $k => $r) {
                $this->log("\r\n" . "[SENT REQUEST]\r\n");
                $this->log($this->sentHeaders[$k] . "\r\n");
                $this->log("[RECEIVED RESPONSE]\r\n");
                $this->log(implode("\r\n", $this->headers[$k]) . "\r\n");
                $this->log($this->rawResult[$k]);
            }
        }
    }

    /**
     * Method to call user error handler
     *
     * @param $statuscode
     * @param $result
     * @param $rawresult
     * @param $errors
     */
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
     * Append key,value to url
     *
     * @param string  $url
     * @param integer $key
     * @param string  $value
     *
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
     *
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
     *
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
     *
     * @param $arr
     *
     * @return string
     */
    protected function implode_encode_assoc($arr)
    {
        $ret_str = "";
        $char = "&";
        if (is_array($arr)) {
            foreach ($arr as $k => $v) {
                $ret_str .= $char . "custom[" . $k . "]=" . urlencode($v);
            }
        }

        return $ret_str;
    }

    /**
     * Returns array with headers data
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Return object if request succeeded and there
     * was not errors in json output.
     *
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
     *
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
     *
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
     *
     * @param $dateTime
     *
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
     * @param string    $userId
     * @param string    $plan
     * @param \DateTime $dateTime
     * @param array     $custom
     */
    public function register($userId, $plan = null, $dateTime = null, $custom = array())
    {
        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/";
        if (!empty($plan)) {
            $url = $this->appendTo($url, "plan", $plan);
        }
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request['url'] = $url;
        $request['method'] = CurlUtil::METHOD_GET;
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
     * @param string    $userId
     * @param \DateTime $dateTime
     * @param array     $custom
     */
    public function login($userId, $dateTime = null, $custom = array())
    {
        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/login";
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request['url'] = $url;
        $request['method'] = CurlUtil::METHOD_GET;
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
     * @param string    $userId
     * @param string    $reason
     * @param \DateTime $dateTime
     * @param array     $custom
     */
    public function cancel($userId, $reason = null, $dateTime = null, $custom = array())
    {
        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/cancel";
        if (!empty($reason)) {
            $url = $this->appendTo($url, "reason", $reason);
        }
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request['url'] = $url;
        $request['method'] = CurlUtil::METHOD_GET;
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
     * @param string    $userId
     * @param string    $plan
     * @param \DateTime $dateTime
     * @param array     $custom
     */
    public function changePlan($userId, $plan, $dateTime = null, $custom = array())
    {
        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/changeplan";
        if (!empty($plan)) {
            $url = $this->appendTo($url, "plan", $plan);
        }
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request['url'] = $url;
        $request['method'] = CurlUtil::METHOD_GET;
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
     * @param string    $userId
     * @param float     $amount
     * @param string    $plan
     * @param int       $duration
     * @param \DateTime $dateTime
     * @param array     $custom
     */
    public function charge($userId, $amount, $plan, $duration = 1, $dateTime = null, $custom = array())
    {
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
        $request['url'] = $url;
        $request['method'] = CurlUtil::METHOD_GET;
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
     * @param string    $userId
     * @param float     $amount
     * @param int       $duration
     * @param \DateTime $dateTime
     * @param array     $custom
     */
    public function refund($userId, $amount, $duration = 1, $dateTime = null, $custom = array())
    {
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
        $request['url'] = $url;
        $request['method'] = CurlUtil::METHOD_GET;
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
     * @param string    $userId
     * @param \DateTime $dateTime
     * @param array     $custom
     */
    public function activation($userId, $dateTime = null, $custom = array())
    {
        $request = array();
        $url = $this->cbconf->getApiUrl() . "user/" . urlencode($userId) . "/activation";
        if (!empty($dateTime)) {
            $url = $this->appendTo($url, "dateTime", $dateTime->format(\DateTime::ISO8601));
        }
        $url .= $this->implode_encode_assoc($custom);
        $request['url'] = $url;
        $request['method'] = CurlUtil::METHOD_GET;
        $this->queue[] = $request;

        if ($this->cbconf->getAutoFlush()) {
            $this->flush();
        }
    }
}
