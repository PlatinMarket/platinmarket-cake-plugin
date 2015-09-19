<?php

App::uses('PlatinmarketAppController', 'Platinmarket.Controller');

class ProductsController extends PlatinmarketAppController
{

  // Uses Reform Api Product
  public $uses = 'Platinmarket.ReformProduct';

  // Before Filter
  public function beforeFilter()
  {
    parent::beforeFilter();
    $this->ReformProduct->customer_uuid = $this->ReformApi->getCustomerUUID();
  }

  // Get Products
  public function index()
  {
    $this->autoRender = false;
    echo json_encode($this->ReformProduct->index($this->request->data));
  }

}
