<?php

require_once Mage::getBaseDir('lib') . '/Liquidobrl/vendor/autoload.php';

class Liquido_Liquidobrlpaymentmethod_V1Controller extends Mage_Core_Controller_Front_Action
{
    public function liquidoBRLWebhookAction()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                $idempotencykey = $data['data']['chargeDetails']['idempotencyKey'];
                $foundLiquidoSalesOrder = Mage::helper('liquidobrlpaymentmethod')
                    ->findLiquidoSalesOrderByIdempotencyKey($idempotencykey);

                $orderId = $foundLiquidoSalesOrder->getData('order_id');
                $liquidoSalesOrderAlreadyExists = $orderId != null;

                if ($liquidoSalesOrderAlreadyExists) {
                    $orderData = new Varien_Object(array(
                        "orderId" => $orderId,
                        "idempotencyKey" => $idempotencykey,
                        "transferStatus" => $data['data']['chargeDetails']['transferStatus'],
                        "paymentMethod" => $data['data']['chargeDetails']['paymentMethod']
                    ));
                    Mage::helper('liquidobrlpaymentmethod')->createOrUpdateLiquidoSalesOrder($orderData);
                }

                $response = array("code" => 200, "status" => "SUCCESS", "message" => "Success");
                echo json_encode($response);
            } catch (Exception $e) {
                $response = array("code" => 400, "status" => "FAILED", "message" => $e);
                echo json_encode($response);
            }
        } else {
            $response = array("code" => 400, "status" => "FAILED", "message" => "Bad Request");
            echo json_encode($response);
        }
    }
}
