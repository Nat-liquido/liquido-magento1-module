<?php

use \LiquidoBrl\PayInPhpSdk\Util\PayInStatus;

abstract class Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInStatus
{
    public static function mapToMagentoSaleOrderStatus($liquidoPayInStatus)
    {
        switch ($liquidoPayInStatus) {
            case PayInStatus::SETTLED:
                return Liquido_Liquidobrlpaymentmethod_Util_MagentoSaleOrderStatus::COMPLETE;
                break;
            case PayInStatus::IN_PROGRESS:
                return Liquido_Liquidobrlpaymentmethod_Util_MagentoSaleOrderStatus::PENDING_PAYMENT;
                break;
            case PayInStatus::CANCELLED || PayInStatus::FAILED:
                return Liquido_Liquidobrlpaymentmethod_Util_MagentoSaleOrderStatus::CANCELLED;
                break;
            default:
                return Liquido_Liquidobrlpaymentmethod_Util_MagentoSaleOrderStatus::PENDING_PAYMENT;
                break;
        }
    }
}
