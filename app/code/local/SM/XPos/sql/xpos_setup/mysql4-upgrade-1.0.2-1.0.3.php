<?php
$installer = $this;
$installer->startSetup();

$installer->run("
ALTER TABLE {$this->getTable('sales/order')} ADD `till_id` int( 2 ) unsigned NULL;
");


$installer->endSetup();