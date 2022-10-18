<?php

abstract class Liquido_Liquidobrlpaymentmethod_Util_LiquidoBrlPayInMethod
{

    const PIX = [
        "title" => "Pix",
        "description" => "O pagamento será aprovado na hora.",
        "image" => "liquidobrlpaymentmethod/images/pix.png"
        // "image" => Mage::getDesign()->getSkinUrl('liquidobrlpaymentmethod/images/pix.png');
    ];
    const BOLETO = [
        "title" => "Boleto",
        "description" => "O pagamento será aprovado em até 3 dias úteis.",
        "image" => "liquidobrlpaymentmethod/images/boleto.png"
    ];
    const CREDIT_CARD = [
        "title" => "Cartão de Crédito",
        "description" => "O pagamento poderá ser aprovado na hora.",
        "image" => "liquidobrlpaymentmethod/images/credit-card.png"
    ];
}
