<?php

$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable('liquido_brl_payin_sales_order');

$queueTable = $installer->getConnection()->newTable($tableName)
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'auto_increment' => true,
        'nullable' => false,
        'primary' => true
    ), 'Id')
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
        'length' => 255,
        'nullable' => false,
        'primary' => false
    ), 'Order Id')
    ->addColumn('idempotency_key', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
        'length' => 255,
        'nullable' => false,
        'primary' => false
    ), 'Idempotency Key')
    ->addColumn('transfer_status', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => false,
        'primary' => false
    ), 'Transfer Status')
    ->addColumn('payment_method', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => false,
        'primary' => false
    ), 'Payment Method')
    ->addColumn('environment', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => false,
        'primary' => false
    ), 'Environment')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => false,
        'primary' => false
    ), 'Created at')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => false,
        'primary' => false
    ), 'Updated at')
    ->addIndex(
        $installer->getIdxName(
            'liquido_brl_payin_sales_order',
            array(
                'order_id',
                'idempotency_key'
            ),
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
        ),
        array(
            'order_id',
            'idempotency_key'
          ),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
    )
    ->setComment('Liquido Brl Pay In Sales Order');

$installer->getConnection()->createTable($queueTable);

$installer->endSetup();