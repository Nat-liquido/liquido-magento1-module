<?php

// app/code/local/Liquido/Liquidobrlpaymentmethod/controllers/IndexController.php
class Liquido_Liquidobrlpaymentmethod_IndexController extends Mage_Core_Controller_Front_Action
{

    // public function gatewayAction()
    // {
    //     if ($this->getRequest()->get("orderId")) {
    //         $arr_querystring = array(
    //             'flag' => 1,
    //             'orderId' => $this->getRequest()->get("orderId")
    //         );

    //         Mage_Core_Controller_Varien_Action::_redirect(
    //             'liquidobrlpaymentmethod/index/response',
    //             array('_secure' => false, '_query' => $arr_querystring)
    //         );
    //     }
    // }

    public function redirectAction()
    {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'liquidobrlpaymentmethod',
            array('template' => 'liquidobrlpaymentmethod/index.phtml')
        );
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    // public function responseAction()
    // {
    //     if ($this->getRequest()->get("flag") == "1" && $this->getRequest()->get("orderId")) {
    //         $orderId = $this->getRequest()->get("orderId");
    //         $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
    //         $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, 'Payment Success.');
    //         $order->save();

    //         Mage::getSingleton('checkout/session')->unsQuoteId();
    //         Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => false));
    //     } else {
    //         Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/error', array('_secure' => false));
    //     }
    // }
}
