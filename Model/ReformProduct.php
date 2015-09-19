<?php

App::uses('PlatinmarketAppModel', 'Platinmarket.Model');

class ReformProduct extends PlatinmarketAppModel
{

  // Remote Api Controller
  public $api_controller = 'products';

  public function index($options = array())
  {
    $result = Hash::get($this->sendRequest('products', 'index', $options), 'data');
    return $result;
  }

  public function getProductsByProId($productIds = array())
  {
    $options = array('conditions' => array('Product.pro_ID' => $productIds));
    $result = Hash::get($this->sendRequest('products', 'view_multiple', $options), 'data');
    return $result;
  }
}
