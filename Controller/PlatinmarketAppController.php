<?php

App::uses('ReformApi', 'Platinmarket.Lib');
App::uses('AppController', 'Controller');

class PlatinmarketAppController extends AppController
{
  public $components = array('Platinmarket.ReformApi', 'RequestHandler');

  public function beforeFilter()
  {
    parent::beforeFilter();
  }

}
