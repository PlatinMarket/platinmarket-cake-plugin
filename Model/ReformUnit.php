<?php

App::uses('PlatinmarketAppModel', 'Platinmarket.Model');

class ReformUnit extends PlatinmarketAppModel
{

  // Remote Api Controller
  public $api_controller = 'units';

  public function index($options = array())
  {
    $result = Hash::get($this->sendRequest('units', 'index', $options), 'data');
    return $result;
  }

}
