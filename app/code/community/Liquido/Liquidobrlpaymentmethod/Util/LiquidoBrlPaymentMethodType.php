<?php

use \LiquidoBrl\PayInPhpSdk\Util\PaymentMethod;

abstract class Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPaymentMethodType
{
    public static function getPaymentMethodName($paymentMethodType)
    {
        switch ($paymentMethodType) {
            case PaymentMethod::CREDIT_CARD:
                return Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::CREDIT_CARD["title"];
                break;
            case PaymentMethod::PIX_STATIC_QR:
                return Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::PIX["title"];
                break;
            case PaymentMethod::BOLETO:
                return Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::BOLETO["title"];
                break;
            default:
                return "";
        }
    }
}
