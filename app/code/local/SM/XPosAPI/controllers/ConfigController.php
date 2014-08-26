<?php
class SM_XPosAPI_ConfigController extends SM_XPosAPI_Controller_AbstractController
{
    public function indexAction()
    {
        $config = array();
        $config["tax_percent"] = Mage::getStoreConfig("xpos/xposapi/tax_percent");
        $config["company_info"] = Mage::getStoreConfig("xpos/xposapi/company_info");
        $config["company_name"] = Mage::getStoreConfig("xpos/xposapi/company_name");
        $config["currency_code"] = Mage::app()->getStore()->getCurrentCurrencyCode();
        $config["currency_symbol"] = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();;
        $this->_outputJSON($config);
    }
}