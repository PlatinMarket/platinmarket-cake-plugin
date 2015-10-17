<?php

App::uses('Component', 'Controller');
App::uses('Hash', 'Utility');
App::uses('ReformApi', 'Platinmarket.Lib');

class ReformApiComponent extends Component
{

  // Load Core Components
  public $components = array('Session');

  // Hold Customer UUID
  private $customer_uuid = null;
  public function getCustomerUUID()
  {
    return $this->customer_uuid;
  }

  // Get AccessToken
  public function getAccessToken()
  {
    return $this->ReformApi->getAccessToken($this->customer_uuid);
  }

  // Hold Customer Data
  public $customer_data = null;

  // Hold Session Id
  private $session_id = null;
  public function getSessionId()
  {
    return $this->session_id;
  }

  // App Main Page
  public $main_page = null;
  public function redirectMainPage()
  {
    if (is_null($this->main_page)) return $this->Controller->redirect('/' . $this->session_id);
    if (is_string($this->main_page)) return $this->Controller->redirect($this->main_page);
    if (!isset($this->main_page['plugin'])) $this->main_page['plugin'] = null;
    $this->main_page['session_id'] = $this->session_id;
    $this->Controller->redirect($this->main_page);
  }

  // Reform Api Redirect
  public function redirect($url)
  {
    if (is_array($url)) $url['session_id'] = $this->session_id;
    if (is_string($url)) $url = "/" . $this->session_id . $url;
    $this->Controller->redirect($url);
  }

  // Reform Api Router
  public function url($url, $full = false)
  {
    if (is_array($url)) $url['session_id'] = $this->session_id;
    if (is_string($url)) $url = "/" . $this->session_id . $url;
    return Router::url($url, $full);
  }

  // Holds Current Controller
  private $Controller = null;

  // Holds ReformApi Class
  private $ReformApi = null;

  // Before before_filter
  public function initialize(Controller $controller)
  {
    // Hold controller instance
    $this->Controller = $controller;

    // Create 'ReformApi' instance
    $this->ReformApi = new ReformApi();

    // Check Controller If Error
    if ($this->Controller->name == 'CakeError') return;

    // Check session_id
    if (empty($this->session_id)) $this->session_id = Hash::get($this->Controller->params->data, "session_id");
    if (empty($this->session_id)) $this->session_id = Hash::get($this->Controller->params->query, "session_id");
    if (empty($this->session_id)) $this->session_id = !empty($this->Controller->params->session_id) ? $this->Controller->params->session_id : null;
    if (empty($this->session_id)) throw new ReformApiException("Session id required");

    // Check If Request Session Start
    if (strpos($this->Controller->request->url, "session_start") !== false)
    {
      $this->Session->write('PlatinMarket.Customer.' . $this->session_id, null);
      $this->Controller->redirect($this->Controller->request->data['redirect_uri']);
      return;
    }

    // Check hash if set
    if (isset($this->Controller->request->data['hash']))
    {
      // Check hash map
      if (!isset($this->Controller->request->data['hash-map'])) throw new ReformApiException("Bad hash-map");
      // Validate hash
      if (!$this->ReformApi->validateHash($this->Controller->request->data)) throw new ReformApiException("Hash not validated");
    }

    // Get Customer UUID
    $this->customer_uuid = $this->Session->read('PlatinMarket.Customer.' . $this->session_id);
    if (empty($this->customer_uuid)) $this->customer_uuid = Hash::get($this->Controller->request->data, "customer_uuid");
    if (!empty($this->customer_uuid)) $this->Session->write('PlatinMarket.Customer.' . $this->session_id, $this->customer_uuid);
    if (empty($this->customer_uuid)) throw new ReformApiException("Customer uuid not found");

    // Write globally customer uuid
    Configure::write('customer_uuid', $this->customer_uuid);

    // Get Customer Data
    if (($this->customer_data = $this->ReformApi->getCustomer($this->customer_uuid)) === false)
    {
      // Get Authorize
      if (!$this->isPage(array('action' => 'authorize')) && !$this->isPage(array('action' => 'callback'))) $this->authorize();
    }

    // Check Request is authorize answer
    if (strpos($this->Controller->request->url, "oauth/callback") !== false)
    {
      // Check Request
      if (
          !isset($this->Controller->request->data['customer_uuid']) ||
          !isset($this->Controller->request->data['platform_uuid']) ||
          !isset($this->Controller->request->data['auth_code']) ||
          !isset($this->Controller->request->data['lifetime']) ||
          !isset($this->Controller->request->data['hash']) ||
          !isset($this->Controller->request->data['hash-map']) ||
          !isset($this->Controller->request->data['time'])
        )
          throw new UnauthorizedException("Missing callback post parameters");

      $this->saveAuthCode($this->Controller->request->data['auth_code']);
      $this->redirectMainPage();
    }
  }

  private function isPage($params = array())
  {
    return Router::url($this->Controller->request->params) == Router::url(array_merge($this->Controller->request->params, $params));
  }

  public function saveAuthCode($authCode)
  {
    $this->ReformApi->saveAuthCode($authCode, $this->customer_uuid);
  }

  public function authorize()
  {
    // Setting Layout to 'form'
    $this->Controller->layout = "Platinmarket.form";

    // Set Method
    $method = 'POST';

    // Set Action Url
    $action = $this->ReformApi->getAuthorizeUrl();

    // Prepare Hash Data
    $data = $this->ReformApi->signData(array(
      'customer_uuid' => $this->customer_uuid,
      'application_uuid' => Configure::read('PlatinMarket.ApplicationUUID'),
      'platform_uuid' => Configure::read('PlatinMarket.PlatformUUID'),
      'scope' => Configure::read('PlatinMarket.Scope'),
      'redirect_uri' => Router::url(array('plugin' => 'platinmarket', 'session_id' => null, 'controller' => 'oauth', 'action' => 'callback'), true)
    ));
    $data['session_id'] = $this->session_id;

    $this->Controller->set(compact('data', 'method', 'action'));
    $this->Controller->view = 'Platinmarket.Oauth/authorize';
  }

  // Before Render Page
  public function beforeRender(Controller $controller)
  {
    // Check Page is error
    if ($this->Controller->name == 'CakeError') return;
    $this->Controller->set('customer_uuid', $this->customer_uuid); // Set Customer UUID
    $this->Controller->set('customer_data', $this->customer_data); // Set Customer Data
    $this->Controller->set('session_id', $this->session_id); // Set Session Id
  }
}
