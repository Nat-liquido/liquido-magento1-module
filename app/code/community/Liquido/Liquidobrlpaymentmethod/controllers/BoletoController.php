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

// app/code/local/Liquido/Liquidobrlpaymentmethod/controllers/BoletoController.php
class Liquido_Liquidobrlpaymentmethod_BoletoController extends Mage_Core_Controller_Front_Action
{

    public function redirectFormAction()
    {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'liquidobrlpaymentmethod',
            array('template' => 'liquidobrlpaymentmethod/boleto/form.phtml')
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

        $customerState = $this->getRequest()->getPost('customer-state');
        if ($customerState == null) {
            $this->errorMessage = __('Erro ao obter o Estado do cliente.');
            return false;
        }

        // Boleto date expiration (timestamp)
        $dateDeadline = date('Y-m-d H:i:s', strtotime('+5 days', time()));
        $timestampDeadline = strtotime($dateDeadline);

        $this->boletoInputData = new Varien_Object(array(
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
                    "state" => $customerState,
                    "city" => $order->getShippingAddress()->getData('city'),
                    "district" => "Unknown",
                    "street" => $order->getShippingAddress()->getData('street'),
                    "number" => "Unknown",
                    "country" => $order->getShippingAddress()->getData('country_id')
                ],
                "email" => $order->getCustomerEmail()
            ],
            'paymentDeadline' => $timestampDeadline
        ));

        return true;
    }

    private function manageBoletoResponse($boletoResponse)
    {
        if (
            $boletoResponse != null
            && property_exists($boletoResponse, 'transferStatusCode')
            && $boletoResponse->transferStatusCode == 200
        ) {
            if (
                $boletoResponse->paymentMethod == PaymentMethod::BOLETO
                && $boletoResponse->transferStatus == PayInStatus::IN_PROGRESS
            ) {
                $successMessage = __('Boleto gerado.');
                Mage::getSingleton('core/session')->addSuccess($successMessage);
            }

            if ($boletoResponse->transferStatus == PayInStatus::SETTLED) {
                $successMessage = __('Pagamento aprovado.');
                Mage::getSingleton('core/session')->addSuccess($successMessage);
            }

            $this->boletoResultData->setData('paymentMethod', $boletoResponse->paymentMethod);

            if ($boletoResponse->paymentMethod == PaymentMethod::BOLETO) {
                $this->boletoResultData->setData(
                    'boletoDigitalLine',
                    $boletoResponse->transferDetails->boleto->digitalLine
                );
            }

            $this->boletoResultData->setData('transferStatus', $boletoResponse->transferStatus);
            $this->boletoResultData->setData('boletoUrl', $boletoResponse->boletoUrl->path);
        } else {
            $this->boletoResultData->setData('hasFailed', true);

            $errorMsg = "Falha.";
            if (
                $boletoResponse != null
                && property_exists($boletoResponse, 'status')
                && $boletoResponse->status != 200
            ) {
                $errorMsg .= " ($boletoResponse->status - $boletoResponse->error)";
            } else if (
                $boletoResponse != null
                && property_exists($boletoResponse, 'transferStatusCode')
                && $boletoResponse->transferStatusCode != 200
            ) {
                $errorMsg .= " ($boletoResponse->transferStatusCode - $boletoResponse->transferErrorMsg)";
            } else {
                $errorMsg .= " (Erro ao tentar gerar o pagamento)";
            }

            Mage::getSingleton('core/session')->addError($errorMsg);
        }
    }

    public function generateAction()
    {

        $this->boletoResultData = new Varien_Object(array(
            'orderId' => null,
            'boletoDigitalLine' => null,
            'boletoUrl' => null,
            'transferStatus' => null,
            'paymentMethod' => null,
            'hasFailed' => false
        ));

        $areValidData = $this->validateInputPixData();
        if (!$areValidData) {
            $this->boletoResultData->setData('hasFailed', true);
            Mage::getSingleton('core/session')->addError($this->errorMessage);
        } else {

            $orderId = $this->boletoInputData->getData("orderId");
            $this->boletoResultData->setData('orderId', $orderId);

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

                $payload = [
                    "idempotencyKey" => $idempotencyKey,
                    "amount" => $this->boletoInputData->getData('grandTotal') * 100,
                    "paymentMethod" => PaymentMethod::BOLETO,
                    "paymentFlow" => PaymentFlow::DIRECT,
                    "currency" => Currency::BRL,
                    "country" => Country::BRAZIL,
                    "callbackUrl" => Mage::helper('liquidobrlpaymentmethod')->getWebhookUrl(),
                    "payer" => $this->boletoInputData->getData('payer'),
                    "description" => "Magento1.x-Module-Boleto-Request",
                    "paymentTerm" => [
                        "paymentDeadline" => $this->boletoInputData->getData("paymentDeadline")
                    ]
                ];

                Mage::log('Boleto Payload Request: '. json_encode($payload), null, 'liquido.log', true);

                $payInRequest = new PayInRequest($payload);
                $payInService = new PayInService();
                $payInResponse = $payInService->createPayIn($config, $payInRequest);

                Mage::log('Boleto Response: '. json_encode($payInResponse), null, 'liquido.log', true);

                $this->manageBoletoResponse($payInResponse);

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
            array('template' => 'liquidobrlpaymentmethod/boleto/boletoresult.phtml')
        );
        Mage::register('boletoResultData', $this->boletoResultData);
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }
}
