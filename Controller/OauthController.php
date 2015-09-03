<?php

App::uses('PlatinmarketAppController', 'Platinmarket.Controller');
App::uses('CakeTime', 'Utility');
App::uses('HttpSocket', 'Network/Http');

class OauthController extends PlatinmarketAppController
{

  public $components = array('Platinmarket.ReformApi', 'Session');

  public $uses = null;

  // Authorize Callback Method
  public function callback()
  {
    // Check Request
    if (
        !isset($this->request->data['customer_uuid']) ||
        !isset($this->request->data['platform_uuid']) ||
        !isset($this->request->data['auth_code']) ||
        !isset($this->request->data['lifetime']) ||
        !isset($this->request->data['hash']) ||
        !isset($this->request->data['hash-map']) ||
        !isset($this->request->data['time'])
      )
        throw new UnauthorizedException("Missing callback post parameters");

    $this->ReformApi->saveAuthCode($this->request->data['auth_code']);
    $this->redirect(array('plugin' => null, 'controller' => 'config', 'action' => 'index', 'session_id' => $this->ReformApi->getSessionId()));
  }

}
