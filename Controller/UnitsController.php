<?php

App::uses('PlatinmarketAppController', 'Platinmarket.Controller');

class UnitsController extends PlatinmarketAppController
{

  // Uses Reform Api Product
  public $uses = 'Platinmarket.ReformUnit';

  // Before Filter
  public function beforeFilter()
  {
    parent::beforeFilter();
    $this->ReformUnit->customer_uuid = $this->ReformApi->getCustomerUUID();
  }

  // Get Units
  public function index()
  {
    $this->autoRender = false;
    echo json_encode($this->ReformUnit->index($this->request->data));
  }

}
