<?php
// app/code/local/Liquido/Liquidobrlpaymentmethod/Model/Paymentmethod.php
class Liquido_Liquidobrlpaymentmethod_Model_Paymentmethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'liquidobrlpaymentmethod';
    protected $_formBlockType = 'liquidobrlpaymentmethod/form_liquidobrlpaymentmethod';
    // protected $_infoBlockType = 'liquidobrlpaymentmethod/info_liquidobrlpaymentmethod';

    // public function assignData($data)
    // {
    //     $info = $this->getInfoInstance();

    //     if ($data->getCustomFieldOne()) {
    //         $info->setCustomFieldOne($data->getCustomFieldOne());
    //     }

    //     if ($data->getCustomFieldTwo()) {
    //         $info->setCustomFieldTwo($data->getCustomFieldTwo());
    //     }

    //     return $this;
    // }

    // public function validate()
    // {
    //     parent::validate();
    //     $info = $this->getInfoInstance();

    //     if (!$info->getCustomFieldOne()) {
    //         $errorCode = 'invalid_data';
    //         $errorMsg = $this->_getHelper()->__("CustomFieldOne is a required field.\n");
    //     }

    //     if (!$info->getCustomFieldTwo()) {
    //         $errorCode = 'invalid_data';
    //         $errorMsg .= $this->_getHelper()->__('CustomFieldTwo is a required field.');
    //     }

    //     if ($errorMsg) {
    //         Mage::throwException($errorMsg);
    //     }

    //     return $this;
    // }

    public function getOrderPlaceRedirectUrl()
    {
        /**
         * it'll call the redirectAction method in IndexController class.
         */
        return Mage::getUrl('liquidobrlpaymentmethod/index/redirect', array('_secure' => false));
    }
}
