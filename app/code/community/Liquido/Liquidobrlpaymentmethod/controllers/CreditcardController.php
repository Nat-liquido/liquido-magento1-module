<?php

require_once Mage::getBaseDir('lib') . '/Liquidobrl/vendor/autoload.php';

use \LiquidoBrl\PayInPhpSdk\Util\Config;
use \LiquidoBrl\PayInPhpSdk\Util\Country;
use \LiquidoBrl\PayInPhpSdk\Util\Currency;
use \LiquidoBrl\PayInPhpSdk\Util\Common\PaymentMethod;
use \LiquidoBrl\PayInPhpSdk\Util\PaymentFlow;
use \LiquidoBrl\PayInPhpSdk\Util\PayInStatus;
use \LiquidoBrl\PayInPhpSdk\Model\PayInRequest;
use \LiquidoBrl\PayInPhpSdk\Service\PayInService;

class Liquido_Liquidobrlpaymentmethod_CreditCardController extends Mage_Core_Controller_Front_Action
{
    public function redirectFormAction()
    {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'liquidobrlpaymentmethod',
            array('template' => 'liquidobrlpaymentmethod/creditcard/form.phtml')
        );
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    public function validateCreditCardData()
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

        $customerCardNumber = $this->getRequest()->getPost('customer-card-number');
        if ($customerCardNumber == null) {
            $this->errorMessage = __('Erro ao obter o Numero do Cartao do cliente.');
            return false;
        }

        $customerCardName = $this->getRequest()->getPost('customer-card-name');
        if ($customerCardName == null) {
            $this->errorMessage = __('Erro ao obter o nome do cliente.');
            return false;
        }

        $customerCardDate = $this->getRequest()->getPost('customer-card-date');
        $customerCardDateArray = explode('/', $customerCardDate);
        if ($customerCardDate == null) {
            $this->errorMessage = __('Erro ao obter a data de validade do cartao.');
            return false;
        }

        $customerCardCvv = $this->getRequest()->getPost('customer-card-cvv');
        if ($customerCardCvv == null) {
            $this->errorMessage = __('Erro ao obter o CVV do cartao.');
            return false;
        }

        $customerCardInstallments = $this->getRequest()->getPost('customer-installments');
        if ($customerCardInstallments == null) {
            $this->errorMessage = __('Erro ao obter o CVV do cartao.');
            return false;
        }

        $this->creditCardInputData = new Varien_Object([
            "orderId" => $orderId,
            "grandTotal" => $grandTotal,
            "payer" => [
                "name" => $order->getCustomerName(),
                "email" => $order->getCustomerEmail(),
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
                ]
            ],
            "card" => [
                "cardHolderName" => $customerCardName,
                "cardNumber" => $customerCardNumber,
                "expirationMonth" => $customerCardDateArray[0],
                "expirationYear" => $customerCardDateArray[1],
                "cvc" => $customerCardCvv
            ],
            "riskData" => [
                "ipAddress" => $_SERVER['REMOTE_ADDR']
            ],
            "installments" => $customerCardInstallments
        ]);

        return true;
    }

    private function manageCreditCardResponse($creditCardResponse)
    {
        if (
            $creditCardResponse != null
            && property_exists($creditCardResponse, 'transferStatusCode')
            && $creditCardResponse->transferStatusCode == 200
        ) {
            if (
                $creditCardResponse->transferStatus == PayInStatus::IN_PROGRESS
            ) {
                $successMessage = __('Pagamento em analise, em breve sera atualizado.');
                Mage::getSingleton('core/session')->addSuccess($successMessage);
            }

            if ($creditCardResponse->transferStatus == PayInStatus::SETTLED) {
                $successMessage = __('Pagamento aprovado!');
                Mage::getSingleton('core/session')->addSuccess($successMessage);
            }

            $this->creditCardResultData->setData('paymentMethod', $creditCardResponse->paymentMethod);

            $this->creditCardResultData->setData('transferStatus', $creditCardResponse->transferStatus);

            $integerAmount = $creditCardResponse->amount / 100;
            $this->creditCardResultData->setData('amount', number_format($integerAmount, 2, ',', '.'));

            $installmentValue = $integerAmount / $creditCardResponse->transferDetails->card->installments;
            $installments = $creditCardResponse->transferDetails->card->installments
                . " x de R$ " . number_format($installmentValue, 2, ',', '.');

            $this->creditCardResultData->setData('installments', $installments);
        } else {
            $this->creditCardResultData->setData('hasFailed', true);

            $errorMsg = "Falha.";
            if (
                $creditCardResponse != null
                && property_exists($creditCardResponse, 'status')
                && $creditCardResponse->status != 200
            ) {
                $errorMsg .= " ($creditCardResponse->status - $creditCardResponse->error)";
            } else if (
                $creditCardResponse != null
                && property_exists($creditCardResponse, 'transferStatusCode')
                && $creditCardResponse->transferStatusCode != 200
            ) {
                $errorMsg .= " ($creditCardResponse->transferStatusCode - $creditCardResponse->transferErrorMsg)";
            } else {
                $errorMsg .= " (Erro ao tentar gerar o pagamento)";
            }

            Mage::getSingleton('core/session')->addError($errorMsg);
        }
    }

    public function generateAction()
    {
        $this->creditCardResultData = new Varien_Object(array(
            'orderId' => null,
            'transferStatus' => null,
            'paymentMethod' => null,
            'hasFailed' => false
        ));

        $areValidData = $this->validateCreditCardData();
        if (!$areValidData) {
            $this->creditCardResultData->setData('hasFailed', true);
            Mage::getSingleton('core/session')->addError($this->errorMessage);
        } else {

            $orderId = $this->creditCardInputData->getData("orderId");
            $this->creditCardResultData->setData('orderId', $orderId);

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
                    "amount" => $this->creditCardInputData->getData('grandTotal') * 100,
                    "paymentMethod" => PaymentMethod::CREDIT_CARD,
                    "paymentFlow" => PaymentFlow::DIRECT,
                    "currency" => Currency::BRL,
                    "country" => Country::BRAZIL,
                    "payer" => $this->creditCardInputData->getData('payer'),
                    "card" => $this->creditCardInputData->getData('card'),
                    "installments" => $this->creditCardInputData->getData('installments'),
                    "riskData" => $this->creditCardInputData->getData('riskData'),
                    "description" => "Magento1.x-Module-Credit-Card-Request",
                    "callbackUrl" => Mage::helper('liquidobrlpaymentmethod')->getWebhookUrl()
                ];

                Mage::log('Credit Card Payload Request: '. json_encode($payload), null, 'liquido.log', true);

                $payInRequest = new PayInRequest($payload);
                $payInService = new PayInService();
                $payInResponse = $payInService->createPayIn($config, $payInRequest);

                Mage::log('Credit Card Response: '. json_encode($payInResponse), null, 'liquido.log', true);

                $this->manageCreditCardResponse($payInResponse);

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
            array('template' => 'liquidobrlpaymentmethod/creditcard/creditcardresult.phtml')
        );
        Mage::register('creditCardResultData', $this->creditCardResultData);
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }
}
