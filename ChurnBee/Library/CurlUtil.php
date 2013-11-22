<?php
/**
 * Created by Miljenko Rebernisak <miljenko.rebernisak@churnbee.com>.
 * Version: 1.0.0
 */

namespace ChurnBee\Library;

/**
 * Class CurlUtil
 * @package ChurnBee\Library
 */
class CurlUtil
{

    /**
     *
     */
    const METHOD_POST = "POST";
    /**
     *
     */
    const METHOD_GET = "GET";
    /**
     *
     */
    const METHOD_PUT = "PUT";
    /**
     *
     */
    const METHOD_DELETE = "DELETE";

    /**
     * @var
     */
    private $method;

    /**
     * @var
     */
    private $url;

    /**
     * @var
     */
    private $data;

    /**
     * @var
     */
    private $result;

    /**
     * @var
     */
    private $statusCode;

    /**
     * @var array
     */
    private $error = array();

    /**
     * @var
     */
    private $sentHeaders;

    /**
     * @var
     */
    private $timeOut;

    /**
     * @param       $method
     * @param       $url
     * @param array $headers
     */
    public function __construct($method, $url, $headers = null)
    {
        $this->method = $method;
        $this->url = $url;

        if ($headers == null) {
            $this->headers = array(
                'Content-Type: application/json',
                'Accept: application/json',
            );
        }
    }

    /**
     *Perform real request
     */
    public function send()
    {

        if ($this->method == self::METHOD_POST || $this->method == self::METHOD_PUT) {
            $headers[] = 'Content-Length: ' . strlen($this->data);
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeOut);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeOut);
        curl_setopt($ch, CURLOPT_USERAGENT, "ChurnBee Agent v1.0");
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        if ($this->method == self::METHOD_POST || $this->method == self::METHOD_PUT) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data);
        }
        $this->result = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->error = array(curl_error($ch));
        } else {
            $this->statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->sentHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);

        }
        curl_close($ch);

    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }


    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }


    /**
     * @return mixed
     */
    public function getSentHeaders()
    {
        return $this->sentHeaders;
    }

    /**
     * @param mixed $timeOut
     */
    public function setTimeOut($timeOut)
    {
        $this->timeOut = $timeOut;
    }

    /**
     * @return mixed
     */
    public function getTimeOut()
    {
        return $this->timeOut;
    }


}