<?php
class SM_XPosAPI_EmailController extends SM_XPosAPI_Controller_AbstractController
{
    public function indexAction()
    {
        $data = json_decode(file_get_contents('php://input'));

        if (empty($data) || !isset($data->order_id) || !is_int($data->order_id)) {
            $this->_outputJSON(array('error_message' => "Wrong data format"), 500);
        }

        $order = Mage::getModel('sales/order')->load($data->order_id);
        try {
            $order->sendNewOrderEmail();
            $this->getResponse()->setHttpResponseCode(204);
        } catch (Exception $e) {
            $this->_outputJSON(
                array('error_message' => "There's an error when sending email. Reason:" . $e->getMessage()), 500
            );
        }
    }
}