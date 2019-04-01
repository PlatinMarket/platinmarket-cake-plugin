<?php

App::uses('CakeTime', 'Utility');
App::uses('Hash', 'Utility');
App::uses('HttpSocket', 'Network/Http');
App::uses('ReformApiData', 'Platinmarket.Lib');
App::uses('ReformApiException', 'Platinmarket.Lib');

require_once "ReformApiData.php";

class ReformApi
{

  // Hold db class
  private $db = null;

  // Hold http class
  private $http = null;

  // Initialize
  public function __construct()
  {
    if (!$this->_validateConfig()) throw new ReformApiException('Bad Config');
    $dbFile = TMP . DS . 'reform_db' . DS . 'db.dat';
    if (Configure::read('PlatinMarket.DbFile')) $dbFile = Configure::read('PlatinMarket.DbFile');
    $this->db = new ReformApiData($dbFile);
    $this->http = new HttpSocket(array('ssl_verify_peer' => false));
  }

  // Sign Data
  public function signData($data)
  {
    $hash_map = implode(',', array_keys($data));
    $data['hash'] = $this->__hash(Configure::read('PlatinMarket.ClientID'), Configure::read('PlatinMarket.ClientSecret'), implode("", array_values($data)));
    $data['hash-map'] = $hash_map;
    $data['time'] = CakeTime::toRSS(new DateTime(), 'Europe/Istanbul');
    return $data;
  }

  // Check Response
  private function parseResponse($response)
  {
    // Check Response
    if ($response->code != 200)
    {
      $response_msg = array('message' => '');
      try
      {
        $tmp = json_decode($response->body, true);
        $response_msg = $tmp;
      }
      catch (Exception $err)
      {
        $response_msg['message'] = $response->body;
      }
      throw new ReformApiException($response_msg['message']);
    }

    // Parse Response
    try
    {
      $response_data = json_decode($response->body, true);
    }
    catch (Exception $err)
    {
      throw new ReformApiException($err->getMessage());
    }
    return $response_data;
  }

  // Get AccessToken
  public function getAccessToken($customer_uuid)
  {
    // Get AccessToken from db
    if (!empty($accessToken = $this->db->read('AccessToken.' . $customer_uuid))) return Hash::get($accessToken, 'token');

    // For Promise
    $this->db->remove('AccessToken.' . $customer_uuid);

    // Get From RefreshToken
    if ($refreshToken = $this->db->read('RefreshToken.' . $customer_uuid))
    {
      $refreshToken = Hash::get($refreshToken, 'token');
      if (($response = $this->getTokenFrom('refresh_token', $refreshToken, $customer_uuid)) === false)
      {
        $this->db->remove('RefreshToken.' . $customer_uuid);
        return false;
      }
      $response = $response['result'];

      // Delete Old Tokens
      $this->db->remove('AccessToken.' . $customer_uuid);
      $this->db->remove('RefreshToken.' . $customer_uuid);

      // Save Tokens
      $this->db->write('AccessToken.' . $customer_uuid, array('token' => $response['access_token'], 'lifetime' => $response['access_token_lifetime'], 'created' => $response['time']));
      $this->db->write('RefreshToken.' . $customer_uuid, array('token' => $response['refresh_token'], 'lifetime' => $response['refresh_token_lifetime'], 'created' => $response['time']));

      return $response['access_token'];
    }

    return false;
  }

  public function getCustomer($customer_uuid)
  {
    if (!($access_token = $this->getAccessToken($customer_uuid))) return false;

    // Prepare Data
    $data = $this->signData(array(
      'platform_uuid' => Configure::read('PlatinMarket.PlatformUUID'),
      'customer_uuid' => $customer_uuid,
      'application_uuid' => Configure::read('PlatinMarket.ApplicationUUID')
    ));
    $data['access_token'] = $access_token;

    // Make Request
    $response = $this->http->get($this->getOauthUrl("/customer_info.json"), $data);

    // Parse Response
    try
    {
      $response = $this->parseResponse($response);
      return $response['customer'];
    }
    catch (ReformApiException $e)
    {
      $this->db->remove('AccessToken.' . $customer_uuid);
      if (!($access_token = $this->getAccessToken($customer_uuid))) return false;
      return $this->getCustomer($customer_uuid);
    }
    catch (Exception $e)
    {
      throw $e;
    }
  }

  // Get Access Token From AuthCode
  public function saveAuthCode($authCode, $customer_uuid)
  {
    if (($response = $this->getTokenFrom('auth_code', $authCode, $customer_uuid)) === false)
    {
      throw new ReformApiException("Error getting AccessToken from AuthCode");
      return false;
    }
    $response = $response['result'];
    // Delete Old Tokens
    $this->db->remove('AccessToken.' . $customer_uuid);
    $this->db->remove('RefreshToken.' . $customer_uuid);

    // Save Tokens
    $this->db->write('AccessToken.' . $customer_uuid, array('token' => $response['access_token'], 'lifetime' => $response['access_token_lifetime'], 'created' => $response['time']));
    $this->db->write('RefreshToken.' . $customer_uuid, array('token' => $response['refresh_token'], 'lifetime' => $response['refresh_token_lifetime'], 'created' => $response['time']));

    return true;
  }

  // Get Token From
  private function getTokenFrom($grant_type = 'refresh_token', $token, $customer_uuid)
  {
    // Prepare Data
    $data = $this->signData(array(
      'platform_uuid' => Configure::read('PlatinMarket.PlatformUUID'),
      'customer_uuid' => $customer_uuid,
      'token' => $token,
      'grant_type' => $grant_type
    ));
    $data['application_uuid'] = Configure::read('PlatinMarket.ApplicationUUID');

    // Make Request
    $response = $this->http->get($this->getOauthUrl("/access_token.json"), $data);

    // Parse Response
    try
    {
      $response = $this->parseResponse($response);
    }
    catch (ReformApiException $e)
    {
      return false;
    }
    catch (Exception $e)
    {
      throw $e;
    }

    return $response;
  }

  // Get OauthUrl
  private function getOauthUrl($path = null)
  {
    $url = Configure::read('PlatinMarket.Api.Base') . Configure::read('PlatinMarket.Api.OauthPath');
    if (!empty($path) && is_string($path)) $url .= $path;
    return $url;
  }

  // Get Authorize Url
  public function getAuthorizeUrl()
  {
    return $this->getOauthUrl('/authorize');
  }

  // Get Api Url
  private function getApiUrl($path = null)
  {
    $url = Configure::read('PlatinMarket.Api.Base') . Configure::read('PlatinMarket.Api.BasePath');
    if (!empty($path) && is_string($path)) $url .= $path;
    return $url;
  }

  // Make Request
  public function getResponse($method = 'GET', $uri)
  {
    // Make Request
    $HttpSocket = new HttpSocket(array('ssl_verify_peer' => false));
    $response = $HttpSocket->get($action, $data);

    // Check Response
    if ($response->code != 200)
    {
      try { $response_msg = json_decode($response->body); } catch (Exception $err) { $response_msg = $response->body; }
      throw new Exception("Request for 'customer_info' failed. " . $response_msg, $response->code);
    }

    // Parse Response
    try
    {
      $response_data = json_decode($response->body, true);
      $response_data = $response_data['customer'];
      extract($response_data);
    }
    catch (Exception $err)
    {
      throw new Exception("Parse failed for 'customer_info' failed", $response->code);
    }
  }

  // Generate hash from data array
  private function __hash($clientId, $clientSecret, $hashStr, $enc = 'sha256') {
    $SecurityData = strtoupper(hash($enc, $clientSecret . $clientId, false));
    return strtoupper(hash($enc, $hashStr . $SecurityData, false));
  }

  // Validate remote hash with ClientSecret
  public function validateHash($dataArr = array())
  {
    if (!$this->_validateConfig()) throw new ReformApiException('Bad Config');
    if (!($hashMap = Hash::get($dataArr, "hash-map"))) throw new ReformApiException('hash-map not found');
    if (!($hash = Hash::get($dataArr, "hash"))) throw new ReformApiException('hash not found');
    $hashMap = explode(",", $hashMap);
    $hashStr = "";
    foreach ($hashMap as $key) $hashStr .= $dataArr[$key];
    return $hash === $this->__hash(Configure::read("PlatinMarket.ClientID"), Configure::read("PlatinMarket.ClientSecret"), $hashStr);
  }

  // Check config is valid returns boolean
  private function _validateConfig()
  {
    return !empty(Configure::read("PlatinMarket.ClientID")) &&
        !empty(Configure::read("PlatinMarket.ClientSecret")) &&
        !empty(Configure::read("PlatinMarket.ApplicationUUID")) &&
        !empty(Configure::read("PlatinMarket.PlatformUUID")) &&
        !empty(Configure::read("PlatinMarket.Scope")) &&
        !empty(Configure::read("PlatinMarket.Api"));
  }

}
