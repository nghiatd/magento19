<?php
class SM_XPosAPI_Controller_AbstractController extends Mage_Core_Controller_Front_Action
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

    protected function _outputJSON($array, $responseCode = 200)
    {
        $this->getResponse()->setHttpResponseCode($responseCode);
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        echo json_encode($array);
    }

    /**
     * @param     $entity: product, category, customer
     * @param int $responseCode
     */
    protected function _outputCachedFile($entity, $responseCode = 200)
    {
        $file = Mage::helper('xposapi')->getCacheDir() . DS . $entity;
        if (file_exists($file) && is_readable($file)) {
            $this->getResponse()->setHttpResponseCode($responseCode);
            $this->getResponse()->setHeader('Content-Type', 'application/json', true);
            $this->getResponse()->setBody(file_get_contents($file));
        } else {
            $this->_outputJSON(array('error_message' => 'Cache file not found'), 404);
        }
    }
}
