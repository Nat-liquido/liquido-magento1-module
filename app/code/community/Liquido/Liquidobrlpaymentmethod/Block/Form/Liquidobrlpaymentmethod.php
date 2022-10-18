<?php

// app/code/local/Envato/Liquidobrlpaymentmethod/Block/Form/Liquidobrlpaymentmethod.php
class Liquido_Liquidobrlpaymentmethod_Block_Form_Liquidobrlpaymentmethod extends Mage_Payment_Block_Form
{
  protected function _construct()
  {
    parent::_construct();
    $this->setTemplate('liquidobrlpaymentmethod/form/liquidobrlpaymentmethod.phtml');
  }
}
