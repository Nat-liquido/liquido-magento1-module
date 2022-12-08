<?php

require_once Mage::getBaseDir('lib') . '/Liquidobrl/vendor/autoload.php';

use \LiquidoBrl\PayInPhpSdk\Util\Config;
use \LiquidoBrl\PayInPhpSdk\Util\Country;
use \LiquidoBrl\PayInPhpSdk\Util\Currency;
use \LiquidoBrl\PayInPhpSdk\Util\Brazil\PaymentMethod;
use \LiquidoBrl\PayInPhpSdk\Util\PaymentFlow;
use \LiquidoBrl\PayInPhpSdk\Util\PayInStatus;
use \LiquidoBrl\PayInPhpSdk\Model\PayInRequest;
use \LiquidoBrl\PayInPhpSdk\Service\PayInService;

// app/code/local/Liquido/Liquidobrlpaymentmethod/controllers/PixcodeController.php
class Liquido_Liquidobrlpaymentmethod_PixcodeController extends Mage_Core_Controller_Front_Action
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

    public function redirectFormAction()
    {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'liquidobrlpaymentmethod',
            array('template' => 'liquidobrlpaymentmethod/pixcode/form.phtml')
        );
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    private function validateInputPixData()
    {

        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        if ($orderId == null) {
            $this->errorMessage = __('Erro ao obter o número do pedido.');
            return false;
        }

        $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
        $grandTotal = $order->getGrandTotal();
        if ($grandTotal == 0 || null) {
            $this->errorMessage = __('O valor da compra deve ser maior que R$0,00.');
            return false;
        }

        $customerCpf = $this->getRequest()->getPost('customer-cpf');
        if ($customerCpf == null) {
            $this->errorMessage = __('Erro ao obter o CPF do cliente.');
            return false;
        }

        // echo "<pre>";
        // print_r($order->getShippingAddress()->getData());
        // echo "</pre>";

        $this->pixInputData = new Varien_Object(array(
            'orderId' => $orderId,
            'grandTotal' => $grandTotal,
            "payer" => [
                "name" => $order->getCustomerName(),
                "document" => [
                    "documentId" => $customerCpf,
                    "type" => "CPF"
                ],
                "billingAddress" => [
                    "zipCode" => $order->getShippingAddress()->getData('postcode'),
                    "state" => $order->getShippingAddress()->getData('region'),
                    "city" => $order->getShippingAddress()->getData('city'),
                    "district" => "Unknown",
                    "street" => $order->getShippingAddress()->getData('street'),
                    "number" => "Unknown",
                    "country" => $order->getShippingAddress()->getData('country_id')
                ],
                "email" => $order->getCustomerEmail()
            ],
        ));

        return true;
    }

    private function managePixResponse($pixResponse)
    {
        if (
            $pixResponse != null
            && property_exists($pixResponse, 'transferStatusCode')
            && $pixResponse->transferStatusCode == 200
        ) {
            if (
                $pixResponse->paymentMethod == PaymentMethod::PIX_STATIC_QR
                && $pixResponse->transferStatus == PayInStatus::IN_PROGRESS
            ) {
                $successMessage = __('Código PIX gerado.');
                Mage::getSingleton('core/session')->addSuccess($successMessage);
            }

            if ($pixResponse->transferStatus == PayInStatus::SETTLED) {
                $successMessage = __('Pagamento aprovado.');
                Mage::getSingleton('core/session')->addSuccess($successMessage);
            }

            $this->pixResultData->setData('paymentMethod', $pixResponse->paymentMethod);

            if ($pixResponse->paymentMethod == PaymentMethod::PIX_STATIC_QR) {
                $this->pixResultData->setData('pixCode', $pixResponse->transferDetails->pix->qrCode);
            }

            $this->pixResultData->setData('transferStatus', $pixResponse->transferStatus);
        } else {
            $this->pixResultData->setData('hasFailed', true);

            $errorMsg = "Falha.";
            if (
                $pixResponse != null
                && property_exists($pixResponse, 'status')
                && $pixResponse->status != 200
            ) {
                $errorMsg .= " ($pixResponse->status - $pixResponse->error)";
            } else if (
                $pixResponse != null
                && property_exists($pixResponse, 'transferStatusCode')
                && $pixResponse->transferStatusCode != 200
            ) {
                $errorMsg .= " ($pixResponse->transferStatusCode - $pixResponse->transferErrorMsg)";
            } else {
                $errorMsg .= " (Erro ao tentar gerar o pagamento)";
            }

            Mage::getSingleton('core/session')->addError($errorMsg);
        }
    }

    public function generateAction()
    {

        $this->pixResultData = new Varien_Object(array(
            'orderId' => null,
            'pixCode' => null,
            'transferStatus' => null,
            'paymentMethod' => null,
            'hasFailed' => false
        ));

        $areValidData = $this->validateInputPixData();
        if (!$areValidData) {
            $this->pixResultData->setData('hasFailed', true);
            Mage::getSingleton('core/session')->addError($this->errorMessage);
        } else {

            $orderId = $this->pixInputData->getData("orderId");
            $this->pixResultData->setData('orderId', $orderId);

            /**
             * Don't generate a new idempotency key if a request was already done successfuly before.
             */
            $idempotencyKey = Mage::helper('liquidobrlpaymentmethod')
                ->getAlreadyRegisteredIdempotencyKey($orderId);
            if ($idempotencyKey == null) {
                $idempotencyKey = Mage::helper('liquidobrlpaymentmethod')->gen_uuid();
            }

            if (
                Mage::helper('liquidobrlpaymentmethod')->getClientId() != null
                && Mage::helper('liquidobrlpaymentmethod')->getClientSecret() != null
                && Mage::helper('liquidobrlpaymentmethod')->getClientApiKey() != null
            ) {

                $config = new Config(
                    [
                        'clientId' => Mage::helper('liquidobrlpaymentmethod')->getClientId(),
                        'clientSecret' => Mage::helper('liquidobrlpaymentmethod')->getClientSecret(),
                        'apiKey' => Mage::helper('liquidobrlpaymentmethod')->getClientApiKey()
                    ],
                    Mage::helper('liquidobrlpaymentmethod')->isProductionModeActived()
                );

                $payInRequest = new PayInRequest([
                    "idempotencyKey" => $idempotencyKey,
                    "amount" => $this->pixInputData->getData('grandTotal') * 100,
                    "currency" => Currency::BRL,
                    "country" => Country::BRAZIL,   
                    "paymentMethod" => PaymentMethod::PIX_STATIC_QR,
                    "paymentFlow" => PaymentFlow::DIRECT,
                    "callbackUrl" => Mage::helper('liquidobrlpaymentmethod')->getWebhookUrl(),
                    "payer" => $this->pixInputData->getData('payer'),
                    "description" => "Magento1.x-Module-PIX-Request",
                ]);

                $payInService = new PayInService();
                $payInResponse = $payInService->createPayIn($config, $payInRequest);

                $this->managePixResponse($payInResponse);

                if (
                    $payInResponse != null
                    && property_exists($payInResponse, 'transferStatus')
                    && $payInResponse->transferStatus != null
                    && property_exists($payInResponse, 'paymentMethod')
                    && $payInResponse->paymentMethod != null
                ) {
                    $orderData = new Varien_Object(array(
                        "orderId" => $orderId,
                        "idempotencyKey" => $idempotencyKey,
                        "transferStatus" => $payInResponse->transferStatus,
                        "paymentMethod" => $payInResponse->paymentMethod
                    ));
                    Mage::helper('liquidobrlpaymentmethod')->createOrUpdateLiquidoSalesOrder($orderData);
                }
            } else {
                Mage::getSingleton('core/session')->addError("Sem credenciais cadastradas");
            }
        }

        $this->loadLayout();
        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'liquidobrlpaymentmethod',
            array('template' => 'liquidobrlpaymentmethod/pixcode/pixresult.phtml')
        );
        Mage::register('pixResultData', $this->pixResultData);
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
