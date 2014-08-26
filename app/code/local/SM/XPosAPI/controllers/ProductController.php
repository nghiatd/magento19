<?php
class SM_XPosAPI_ProductController extends SM_XPosAPI_Controller_AbstractController
{
    public function indexAction()
    {
        $lastUpdateTime = $this->getRequest()->getParam('last_update_time');
        if($lastUpdateTime && $lastUpdateTime != '0') {
            $updatedData = Mage::getModel('xposapi/observer')->generateProducts($lastUpdateTime);
            $this->_outputJSON($updatedData);
        } else {
            $this->_outputCachedFile('product');
        }
    }
    
    /**
     * #1281
     * API to get product's list
     * Call: POST /xposrest/product/list
     */
    public function listAction() {
        $postData = json_decode(file_get_contents('php://input'));
        if ($postData->update) {
            $product_list = Mage::getModel('xposapi/product')->getProductList();
            $this->_outputJSON($product_list);
        } else {
            $this->_outputCachedFile('product');
        }
    }
}