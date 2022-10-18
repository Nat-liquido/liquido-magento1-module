<?php

use \LiquidoBrl\PayInPhpSdk\Util\PayInStatus;

class Liquido_Liquidobrlpaymentmethod_Helper_Data extends Mage_Core_Helper_Abstract
{
    // function getPaymentGatewayUrl()
    // {
    //     return Mage::getUrl('liquidobrlpaymentmethod/index/gateway', array('_secure' => false));
    // }

    public function getLiquidoBrazilPayInMethods()
    {
        $brazil_payin_methods = [
            Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::CREDIT_CARD,
            Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::PIX,
            Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::BOLETO
        ];
        return $brazil_payin_methods;
    }

    public function getPayInMethodViewRoute($_payin_method_title)
    {
        switch ($_payin_method_title) {
            case Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::PIX["title"]:
                /**
                 * it'll call the redirectForm method in PixcodeController class.
                 */
                return Mage::getUrl('liquidobrlpaymentmethod/pixcode/redirectForm', array('_secure' => false));
                break;
            case Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::BOLETO["title"]:
                return Mage::getUrl('liquidobrlpaymentmethod/boleto/redirectForm', array('_secure' => false));
                break;
            case Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::CREDIT_CARD["title"]:
                return Mage::getUrl('liquidobrlpaymentmethod/creditcard/redirectForm', array('_secure' => false));
                break;
            default:
                return "#";
        }
    }

    public function getWebhookUrl()
    {
        $baseUrl = Mage::getBaseUrl();
        return $baseUrl . "liquidobrlpaymentmethod/v1/liquidobrlwebhook";
    }

    public function getGeneratePixcodeUrl()
    {
        return Mage::getUrl('liquidobrlpaymentmethod/pixcode/generate', array('_secure' => false));
    }

    public function getGenerateBoleto()
    {
        return Mage::getUrl('liquidobrlpaymentmethod/boleto/generate', array('_secure' => false));
    }

    public function getGenerateCreditCardPayment()
    {
        return Mage::getUrl('liquidobrlpaymentmethod/creditcard/generate', array('_secure' => false));
    }

    public function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public function isProductionModeActived()
    {
        return Mage::getStoreConfig('payment/liquidobrlpaymentmethod/production_mode');;
    }

    public function getClientId()
    {
        if ($this->isProductionModeActived()) {
            return Mage::getStoreConfig('payment/liquidobrlpaymentmethod/prod_client_id');
        }
        return Mage::getStoreConfig('payment/liquidobrlpaymentmethod/sandbox_client_id');
    }

    public function getClientSecret()
    {
        if ($this->isProductionModeActived()) {
            return $this->decrypt(Mage::getStoreConfig('payment/liquidobrlpaymentmethod/prod_client_secret'));
        }
        return $this->decrypt(Mage::getStoreConfig('payment/liquidobrlpaymentmethod/sandbox_client_secret'));
    }

    public function getClientApiKey()
    {
        if ($this->isProductionModeActived()) {
            return Mage::getStoreConfig('payment/liquidobrlpaymentmethod/prod_api_key');
        }
        return Mage::getStoreConfig('payment/liquidobrlpaymentmethod/sandbox_api_key');
    }

    public function decrypt($encryptedValue)
    {
        return Mage::helper('core')->decrypt($encryptedValue);
    }

    private function findLiquidoSalesOrderByOrderId($orderId)
    {
        try {
            $foundLiquidoSalesOrder = Mage::getModel('liquidobrlpaymentmethod/liquidobrlsalesorder')
                ->getCollection()
                ->addFieldToFilter('order_id', $orderId)
                ->getFirstItem();
            return $foundLiquidoSalesOrder;
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function findLiquidoSalesOrderByIdempotencyKey($idempotencyKey)
    {
        try {
            $foundLiquidoSalesOrder = Mage::getModel('liquidobrlpaymentmethod/liquidobrlsalesorder')
                ->getCollection()
                ->addFieldToFilter('idempotency_key', $idempotencyKey)
                ->getFirstItem();
            return $foundLiquidoSalesOrder;
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getAlreadyRegisteredIdempotencyKey($orderId)
    {

        $foundLiquidoSalesOrder = $this->findLiquidoSalesOrderByOrderId($orderId);

        $liquidoSalesOrderAlreadyExists = $foundLiquidoSalesOrder->getData('order_id') != null;
        $liquidoSalesOrderAlreadyExistsAndResponseFailed = $liquidoSalesOrderAlreadyExists
            && ($foundLiquidoSalesOrder->getData('transfer_status') == null
                || $foundLiquidoSalesOrder->getData('transfer_status') == PayInStatus::FAILED
            );

        if ($liquidoSalesOrderAlreadyExists && !$liquidoSalesOrderAlreadyExistsAndResponseFailed) {
            $liquidoIdempotencyKey = $foundLiquidoSalesOrder->getData('idempotency_key');
            return $liquidoIdempotencyKey;
        }

        return null;
    }

    private function createNewLiquidoSalesOrder($orderData)
    {
        try {
            $model = Mage::getModel('liquidobrlpaymentmethod/liquidobrlsalesorder');
            $environment = $this->isProductionModeActived() ? "PRODUCTION" : "STAGING";
            $dateTimeNow = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
            $model->setData(array(
                'order_id' => $orderData->getData("orderId"),
                'idempotency_key' => $orderData->getData("idempotencyKey"),
                'transfer_status' => $orderData->getData("transferStatus"),
                'payment_method' => $orderData->getData("paymentMethod"),
                'environment' => $environment,
                'created_at' => $dateTimeNow,
                'updated_at' => $dateTimeNow,
            ));
            $model->save();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function createOrUpdateLiquidoSalesOrder($orderData)
    {
        try {

            $orderId = $orderData->getData("orderId");
            $idempotencyKey = $orderData->getData("idempotencyKey");
            $transferStatus = $orderData->getData("transferStatus");
            $paymentMethod = $orderData->getData("paymentMethod");

            $foundLiquidoSalesOrder = $this->findLiquidoSalesOrderByOrderId($orderId);
            $liquidoSalesOrderAlreadyExists = $foundLiquidoSalesOrder->getData('order_id') != null;

            /** -------------- Liquido Sales Order ("liquido_payin_sales_order" table)-------------- */
            if (!$liquidoSalesOrderAlreadyExists) {
                $this->createNewLiquidoSalesOrder($orderData);
            } else {

                if ($foundLiquidoSalesOrder->getData('idempotency_key') != $idempotencyKey) {
                    $foundLiquidoSalesOrder->setIdempotencyKey($idempotencyKey);
                    $dateTimeNow = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
                    $foundLiquidoSalesOrder->setUpdatedAt($dateTimeNow);
                    $foundLiquidoSalesOrder->save();
                }

                if ($foundLiquidoSalesOrder->getData('transfer_status') != $transferStatus) {
                    $foundLiquidoSalesOrder->setTransferStatus($transferStatus);
                    $dateTimeNow = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
                    $foundLiquidoSalesOrder->setUpdatedAt($dateTimeNow);
                    $foundLiquidoSalesOrder->save();
                }

                if ($foundLiquidoSalesOrder->getData('payment_method') != $paymentMethod) {
                    $foundLiquidoSalesOrder->setPaymentMethod($paymentMethod);
                    $dateTimeNow = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
                    $foundLiquidoSalesOrder->setUpdatedAt($dateTimeNow);
                    $foundLiquidoSalesOrder->save();
                }
            }
            /** -------------- Liquido Sales Order -------------- */

            /** -------------- Magento Sales Order ("sales_flat_order" table) -------------- */
            $magentoSalesOrder = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            $magentoOrderStatus = Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInStatus::mapToMagentoSaleOrderStatus($transferStatus);
            if ($magentoSalesOrder->getStatus() != $magentoOrderStatus) {
                $magentoSalesOrder->setStatus($magentoOrderStatus);
                $magentoSalesOrder->save();
            }
            /** -------------- Magento Sales Order -------------- */
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getInstallments()
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
        $orderTotal = $order->getGrandTotal();
        $textsForOptionsArray = array();

        if ($orderTotal >= 1) {
            for ($i = 1; $i <= 12; $i++) {

                $installmentAmount = $orderTotal / $i;
                $installmentAmountRound = round($installmentAmount, 2);
                if (floatval($installmentAmountRound) == 0.01) {
                    break;
                }

                $installmentValue = number_format($installmentAmountRound, 2, ',', '.');
                $optionInfo = $i . "x de R$ " . $installmentValue;
                array_push($textsForOptionsArray, $optionInfo);
            }
        } else {
            $orderTotal = number_format($orderTotal, 2, ',', '.');
            $optionInfo = "1 x de R$ {$orderTotal}";
            array_push($textsForOptionsArray, $optionInfo);
        }

        return $textsForOptionsArray;
    }
}
