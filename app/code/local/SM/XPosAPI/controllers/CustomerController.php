<?php
class SM_XPosAPI_CustomerController extends SM_XPosAPI_Controller_AbstractController
{
    public function indexAction()
    {
        $this->_outputCachedFile('customer');
    }

    /**
     * #1275
     * API to create a new customer
     * Call: POST /xposrest/customer/create
     * @return boolean
     */
    public function createAction() {
        $postData = json_decode(file_get_contents('php://input'));
        $customerData = $postData->customerData;
        if (is_null($customerData)) {
            $this->_outputJSON(array('error_message' => 'Data not found'), 404);
            return false;
        }
        
        try {
            $result = Mage::getModel('xposapi/customer')->create($customerData);
            if ($result) {
                $this->_outputJSON(
                    array('customer_id' => $result), 200
                );
            }
        } catch (Exception $ex) {
            $this->_outputJSON(
                array("error_message" => $ex->getMessage()), 500
            );
        }
        
        return true;
    }

    /**
     * #1275
     * API to update an existing customer
     * Call: POST /xposrest/customer/update/id/<<<customer_id>>>
     * @return boolean
     */
    public function updateAction() {
        $postData = json_decode(file_get_contents('php://input'));
        $customerData = $postData->customerData;
        if (is_null($customerData)) {
            $this->_outputJSON(array('error_message' => 'Data not found'), 404);
            return false;
        }
        $id = $this->getRequest()->getParam('id');
        
        try {
            $result = Mage::getModel('xposapi/customer')->update($id, $customerData);
            if ($result) {
                $this->_outputJSON(
                    array('customer_id' => $id), 200
                );
            }
        } catch (Exception $ex) {
            $this->_outputJSON(
                array("error_message" => $ex->getMessage()), 500
            );
        }
        
        return true;
    }
}