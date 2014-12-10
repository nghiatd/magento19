<?php
$upgrade = 0;
$installer = $this;
$installer->startSetup();

if($upgrade){

    $installer->run("
ALTER TABLE {$this->getTable('xpos/transaction')} ADD `warehouse_id` int(4) unsigned NULL DEFAULT 0;
ALTER TABLE {$this->getTable('xpos/transaction')} ADD `till_id` int(4) unsigned NULL DEFAULT 0;
");

    $installer->run("
ALTER TABLE {$this->getTable('xpos/user')} ADD `type` int(2) unsigned NULL DEFAULT 1;
");

}else{
    $installer->run("
DROP TABLE IF EXISTS {$this->getTable('xpos/transaction')};
CREATE TABLE {$this->getTable('xpos/transaction')} (
  `transaction_id` int(11) unsigned NOT NULL auto_increment,
  `cash_in` float NULL DEFAULT 0,
  `cash_out` float NULL DEFAULT 0,
  `type`  varchar(20) NULL,
  `user_id` int(4) unsigned NULL,
  `xpos_user_id` int(4) unsigned NULL,
  `created_time` datetime NULL,
  `order_id` varchar(20) NULL,
  `warehouse_id` int(4) unsigned NULL DEFAULT 0,
  `till_id` int(4) unsigned NULL DEFAULT 0,
  `previous_balance` float NULL DEFAULT 0,
  `current_balance` float NULL DEFAULT 0,
  `payment_method` varchar(255) NULL,
  `comment` varchar(255) NULL,
  PRIMARY KEY (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

    $installer->run("
DROP TABLE IF EXISTS {$this->getTable('xpos/user')};
CREATE TABLE {$this->getTable('xpos/user')} (
  `xpos_user_id` int(11) unsigned NOT NULL auto_increment,
  `user_id` int(4) unsigned NULL DEFAULT 0,
  `username` varchar(24) NULL,
  `password`  varchar(120) NULL,
  `email` varchar(255) NULL,
  `type` int(2) unsigned NULL DEFAULT 1,
  `firstname` varchar(255) NULL,
  `lastname` varchar(255) NULL,
  `created_time` datetime NULL,
  `modified_time` datetime NULL,
  `lastvisit` datetime NULL,
  `is_active` int(11) NULL,
  `role` varchar(255) NULL,
  PRIMARY KEY (`xpos_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

    $installer->run("
ALTER TABLE {$this->getTable('sales/invoice')} ADD `xpos_user_id` int( 4 ) unsigned NULL;
");

    $installer->run("
ALTER TABLE {$this->getTable('sales/order')} ADD `xpos` int( 2 ) unsigned NULL;
ALTER TABLE {$this->getTable('sales/order')} ADD `xpos_user_id` int( 4 ) unsigned NULL;
ALTER TABLE {$this->getTable('sales/order')} ADD `tzo_created_at` timestamp NULL;
ALTER TABLE {$this->getTable('sales/order')} ADD `tzo_updated_at` timestamp NULL;
");

}

$installer->run("
DROP TABLE IF EXISTS {$this->getTable('xpos/till')};
CREATE TABLE {$this->getTable('xpos/till')} (
  `till_id` int(11) unsigned NOT NULL auto_increment,
  `till_name` text NOT NULL,
  `warehouse_id` int(11) unsigned NOT NULL DEFAULT 0,
  `is_active` int(1) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`till_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();
	 