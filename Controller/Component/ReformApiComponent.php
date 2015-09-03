<?php

App::uses('Component', 'Controller');
App::uses('Hash', 'Utility');
App::uses('ReformApi', 'Platinmarket.Lib');

class ReformApiComponent extends Component
{

  // Load Core Components
  public $components = array('Session');

  // Hold Customer Data
  private $customer_uuid = null;

  // Hold Session Id
  private $session_id = null;
  public function getSessionId()
  {
    return $this->session_id;
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
    if ($this->Controller->request->url == "session_start")
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

    // Get Customer Data
    if (($this->customer_data = $this->ReformApi->getCustomer($this->customer_uuid)) === false)
    {
      // Get Authorize
      if (!$this->isPage(array('action' => 'authorize')) && !$this->isPage(array('action' => 'callback'))) $this->authorize();
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

  // Init Customer Data
  private function __init_customer_data()
  {
    // Return if already set or at during install
    if (!empty($this->customer_data)) return;

    // Check Params Page -> Page
    if (!empty($this->session_id) && !empty($this->Controller->Session->read('session_map.' . $this->session_id)))
    {
      $this->customer_data = ClassRegistry::init('Customer')->findByUuid($this->Controller->Session->read('session_map.' . $this->session_id));
      return;
    }
    elseif (!empty($this->params->customer_id)) // Check customer_id already set probably 404
    {
      throw new NotFoundException("Customer id not found");
    }

    // Check Request
    if (
        !isset($this->Controller->request->data['command']) ||
        !isset($this->Controller->request->data['customer_uuid']) ||
        !isset($this->Controller->request->data['platform_uuid']) ||
        !isset($this->Controller->request->data['success_url']) ||
        !isset($this->Controller->request->data['fail_url']) ||
        !isset($this->Controller->request->data['hash']) ||
        !isset($this->Controller->request->data['hash-map']) ||
        !isset($this->Controller->request->data['time'])
      )
        throw new UnauthorizedException("Missing post parameters");
    else
      extract($this->Controller->request->data);

    // Write Session -> CustomerUUID Map
    $this->Controller->Session->write('session_map.' . $this->session_id, $customer_uuid);

    // Check Hash
    if ($hash != $this->__hash(Configure::read("PlatinMarket.ClientID"), Configure::read("PlatinMarket.ClientSecret"), array($command, $customer_uuid, $platform_uuid, $success_url, $fail_url)))
      throw new BadRequestException("Invalid Hash");

    // Get Data
    $this->customer_data = ClassRegistry::init('Customer')->findByUuid($customer_uuid);
  }

  // Before Render Page
  public function beforeRender(Controller $controller)
  {
    // Check Page is error
    if ($this->Controller->name == 'CakeError') return;
    pr($this->customer_data);
    $this->Controller->set('customer_data', $this->customer_data); // Set Customer Data
    $this->Controller->set('session_id', $this->session_id); // Set Session Id
  }
}
