<?php
/**
 * Copyright © 2018 Ekky Software Pty Ltd. All rights reserved.
 * Please visit http://www.ekkysoftware.com
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace GoPeople\Shipping\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

/**
 * @codeCoverageIgnore
 */
class InstallSchema 
implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'gopeople_cart_id',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => 64,
                'comment'  => 'Go People\'s internal shopping cart id'
            ]
        );
        $setup->getConnection()->addIndex(
            $setup->getTable('sales_order'),
            $setup->getIdxName('sales_order', ['shipping_method','gopeople_cart_id']),
            ['shipping_method','gopeople_cart_id']
        );
        $setup->endSetup();
    }

}