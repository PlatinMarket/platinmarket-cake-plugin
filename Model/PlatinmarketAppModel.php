<?php

App::uses('ReformApiException', 'Platinmarket.Lib');
App::uses('Xml', 'Utility');
App::uses('HttpSocket', 'Network/Http');
App::uses('CakeTime', 'Utility');
App::uses('ReformApi', 'Platinmarket.Lib');
App::uses('AppModel', 'Model');

class PlatinmarketAppModel extends AppModel
{
  // General Model Not use table
  public $useTable = false;

  // Generate http object
  private $http = null;

  // Holds Reform Api
  private $ReformApi;

  // Holds Customer Uuid
  public $customer_uuid = null;
  public function setCustomer($customer)
  {
    if ($this->isCustomer($customer)) return;
    $this->customer_uuid = Hash::get($customer, 'Customer.uuid');
    $this->accessToken = null;
  }
  public function isCustomer($customer)
  {
    return $this->customer_uuid == Hash::get($customer, 'Customer.uuid');
  }
  private function getCustomerUuid()
  {
    if (!is_null($this->customer_uuid)) return $this->customer_uuid;
    $this->customer_uuid = !is_null($this->customer_uuid) ? $this->customer_uuid : Configure::read('customer_uuid');
    if (empty($this->customer_uuid))
    {
      $this->customer_uuid = null;
      throw new ReformApiException('PlatinmarketAppModel customer_uuid not set');
    }
    return $this->customer_uuid;
  }

  // Constructor
  public function __construct($id = false, $table = null, $ds = null) {
    parent::__construct($id, $table, $ds);
    $this->http = new HttpSocket();
    $this->ReformApi = new ReformApi();
  }

  // get AccessToken
  private $accessToken = null;
  protected function getAccessToken()
  {
    if (!is_null($this->accessToken)) return $this->accessToken;
    if (empty($accessToken = $this->ReformApi->getAccessToken($this->getCustomerUuid()))) throw new ReformApiException('PlatinmarketAppModel cannot access AccessToken via customer_uuid \'' . $this->customer_uuid .'\'');
    $this->accessToken = $accessToken;
    return $this->accessToken;
  }

  // refresh AccessToken
  private function refreshToken()
  {
    $this->accessToken = null;
    if (empty($accessToken = $this->ReformApi->refreshToken($this->customer_uuid))) throw new ReformApiException('PlatinmarketAppModel cannot access generate AccessToken via customer_uuid \'' . $this->customer_uuid .'\' with RefreshToken');
    $this->accessToken = $accessToken;
    return $this->accessToken;
  }

  // Generate Call url
  protected function getApiUrl($controller = null, $action = 'index', $ext = 'json')
  {
    if (!Configure::read('PlatinMarket.Api.Base') || !Configure::read('PlatinMarket.Api.BasePath')) throw new ReformApiException('ApiBase url and ApiBasePath configuration failure');
    $baseUrl = Configure::read('PlatinMarket.Api.Base') . Configure::read('PlatinMarket.Api.BasePath');
    if (is_null($controller)) return $baseUrl;
    return $baseUrl . '/' . $controller . '/' . $action . (!empty($ext) ? '.' . $ext : '') . '?access_token=' . $this->getAccessToken();
  }

  // General Send Request
  protected function sendRequest($controller, $action = 'index', $data = array(), $retryFlag = false)
  {
    // Get Url
    $url = $this->getApiUrl($controller, $action, 'json');

    // Get Response From Api
    if (is_array($data) && !empty($data))
      $response = $this->http->post($url, $data);
    else
      $response = $this->http->get($url, $data);

    $response_arr = array();
    if (strpos($response->getHeader('Content-Type'), 'application/json') !== false)
      $response_arr = json_decode($response->body(), true);
    elseif ($response->getHeader('Content-Type') == 'text/xml')
      $response_arr = Xml::toArray(Xml::build($response->body()));
    else
      $response_arr = array('header' => $response->headers, 'data' => $response->body(), 'error' => array('code' => null, 'message' => null, 'scope' => null));

    if (isset($response_arr['response'])) $response_arr = $response_arr['response'];

    if (isset($response_arr['name']) && isset($response_arr['message']) && isset($response_arr['url']))
      $response_arr = array('header' => $response->headers, 'data' => $response->code . ' ' . $response_arr['name'], 'error' => array('code' => $response->code, 'message' => $response_arr['message'], 'scope' => 'LOCAL'));

    // Check Response Error
    if (!$response->isOk()) throw new ReformApiException(array('url' => $url, 'request' => $data, 'response' => $response_arr), $response->code);

    if (!is_null($response_arr['error']['code']))
    {
      if ($response_arr['error']['code'] == 102 && $response_arr['error']['message'] == "TokenExpired" && $retryFlag == false)
      {
        $this->refreshToken();
        return $this->sendRequest($controller, $action, $data, true);
      }
      throw new ReformApiException(array('url' => $url, 'request' => $data, 'response' => $response_arr), $response_arr['error']['code']);
    }

    return $response_arr;
  }

  // Magic general call method
  public function __call($method, $args)
  {
    if (!isset($args[0])) $args = array(0 => array());
    $method = Inflector::underscore($method);
    if (!property_exists($this, 'api_controller')) throw new BadMethodCallException('Method \'' . $method . '\' not exists in current context');
    return Hash::get($this->sendRequest($this->api_controller, $method, $args[0]), 'data');
  }

}
