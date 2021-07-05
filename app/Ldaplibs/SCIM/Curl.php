<?php

namespace App\Ldaplibs\SCIM;

class Curl
{
    private $curl;
    private $response;
    private $exception;

    private const RESULT_CURL_ERROR = "curl_error";
    private const RESULT_HTTP_CODE_ERROR = "http_code_error";
    private const RESULT_SUCCESS = "success";
    private const RESULT_FAILED = "failed";
    private const RESULT_EXCEPTION = "exception";

    public function init($url, $request, $header = null, $fields = null) {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $request);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
        if ($header != null) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
        }
        if ($fields != null) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $fields);
        }
    }

    public function setOption($option, $value) {
        curl_setopt($this->curl, $option, $value);
    }

    public function execute($idKey = null) {
        try {
            $this->response = curl_exec($this->curl);
            if ($this->getErrorNo()) {
                return self::RESULT_CURL_ERROR;
            }

            $info = $this->getInfo();
            if ((int) $info["http_code"] >= 300) {
                return self::RESULT_HTTP_CODE_ERROR;
            }

            $response = json_decode($this->getResponse(), true);
            if ($idKey == null || array_key_exists($idKey, $response)) {
                return self::RESULT_SUCCESS;
            }
            return self::RESULT_FAILED;
        } catch (\Exception $exception) {
            $this->exception = $exception;
            return self::RESULT_EXCEPTION;
        }
    }

    public function getResponse() {
        return $this->response;
    }

    public function getException() {
        return $this->exception;
    }

    public function getInfo() {
        return curl_getinfo($this->curl);
    }

    public function getErrorNo() {
        return curl_errno($this->curl);
    }

    public function getError() {
        return curl_error($this->curl);
    }

    public function close() {
        curl_close($this->curl);
    }

}
