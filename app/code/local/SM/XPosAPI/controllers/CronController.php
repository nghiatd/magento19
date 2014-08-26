<?php
class SM_XPosAPI_CronController extends Mage_Core_Controller_Front_Action
{
    function _construct()
    {
        $postData = json_decode(file_get_contents('php://input'));

        if (is_null($postData)) {
            throw new Exception("sending data is null");
        }

        if (is_null($postData->xpos_apikey)
            || ($postData->xpos_apikey != Mage::getStoreConfig(
                    "xpos/xposapi/xpos_apikey"
                ))
        ) {
            throw new Exception("API-key does not exist or is invalid.");
        }
    }

    public function productAction()
    {
        Mage::getModel('xposapi/observer')->generateProducts();
    }

    public function categoryAction()
    {
        Mage::getModel('xposapi/observer')->generateCategories();
    }

    public function customerAction()
    {
        Mage::getModel('xposapi/observer')->generateCustomers();
    }

    public function userAction()
    {
        Mage::getModel('xposapi/observer')->generateUsers();
    }

    public function tillAction()
    {
        Mage::getModel('xposapi/observer')->generateTills();
    }
}