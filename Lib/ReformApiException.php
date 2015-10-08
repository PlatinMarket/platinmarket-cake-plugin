<?php

class ReformApiException extends CakeException {

  // public toString
  public function __toString()
  {
    if (!empty($this->message)) return $this->message;
    if (isset($this->_attributes['response']['error']['message'])) return $this->_attributes['response']['error']['message'];
    return "";
  }

  // retry Flag
  private $retryFlag = false;
  public function setRetryFlag($flag)
  {
    $this->retryFlag = $flag;
  }

};
