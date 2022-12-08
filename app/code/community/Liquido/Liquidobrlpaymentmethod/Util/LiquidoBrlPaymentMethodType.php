<?php

use \LiquidoBrl\PayInPhpSdk\Util\Common\PaymentMethod as CommonPaymentMethod;
use \LiquidoBrl\PayInPhpSdk\Util\Brazil\PaymentMethod as BrazilPaymentMethod;

abstract class Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPaymentMethodType
{
    public static function getPaymentMethodName($paymentMethodType)
    {
        switch ($paymentMethodType) {
            case CommonPaymentMethod::CREDIT_CARD:
                return Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::CREDIT_CARD["title"];
                break;
            case BrazilPaymentMethod::PIX_STATIC_QR:
                return Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::PIX["title"];
                break;
            case BrazilPaymentMethod::BOLETO:
                return Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod::BOLETO["title"];
                break;
            default:
                return "";
        }
    }
}
