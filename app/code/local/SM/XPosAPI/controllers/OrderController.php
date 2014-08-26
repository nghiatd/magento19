<?php
class SM_XPosAPI_OrderController extends SM_XPosAPI_Controller_AbstractController
{
    public function indexAction()
    {
        $postData = json_decode(file_get_contents('php://input'));
        
        try {
            $this->validateData($postData);
            $return_data = Mage::getModel('xposapi/order')->generateOrderInvoiceShipment($postData); // #1281
            $this->_outputJSON(
                array('order_id' => $return_data['order_id'],
                      'order_increment_id' => $return_data['order_increment_id']), 201
            );
        } catch (Exception $e) {
            $this->_outputJSON(
                array("error_message" => "There's an error when creating order. Reason: {$e->getMessage()}"), 500
            );
        }
    }

    private function validateData($postData)
    {

        if (is_null($postData)) {
            throw new Exception("sending data is null");
        }

        if (is_null($postData->products) && !is_array($postData->products)) {
            throw new Exception("products field is not array");
        }

        foreach ($postData->products as $item) {
            if (is_null($item->product_id) || is_null($item->qty) || is_null($item->price)) {
                throw new Exception("there are some products that are not correct format");
            }
        }

        if (is_null($postData->user_id)) {
            throw new Exception("userid field is null");
        }

        if (is_null($postData->subtotal)) {
            throw new Exception("subtotal field is null");
        }

        if (is_null($postData->discount_percent)) {
            throw new Exception("discount percent field is null");
        }

        if (is_null($postData->discount_amount)) {
            throw new Exception("discount amount field is null");
        }

        if (is_null($postData->tax_percent)) {
            throw new Exception("tax percent field is null");
        }

        if (is_null($postData->tax_amount)) {
            throw new Exception("tax amount field is null");
        }

        if (is_null($postData->grand_total)) {
            throw new Exception("grand total field is null");
        }

        if (is_null($postData->customer_email)) {
            throw new Exception("customer email field is null");
        }

        if (is_null($postData->send_email)) {
            throw new Exception("send email field is null");
        }
        
        // #1278
        if (is_null($postData->xpos_app_order_id)) {
            throw new Exception("xpos app order id is null");
        }
        // #1278 end
    }
    
    /**
     * #1284
     * API to fetch order's list which match conditions
     * Call: POST /xposrest/order/search?id=<<<id>>>&email=<<<email>>>&customer_name=<<<customer_name>>>
     */
    public function searchAction() {
        $request = $this->getRequest();
        $id = $request->getParam('id');
        $email = $request->getParam('email');
        $customer_name = $request->getParam('customer_name');
        
        $params = array(
            'id' => $id,
            'email' => $email,
            'customer_name' => $customer_name,
        );
        
        // use data from cache file first if allowed
        $postData = json_decode(file_get_contents('php://input'));
        $orderModel = Mage::getModel('xposapi/order');
        $cache_filename = $orderModel->generateOrderListCacheFileName($params);
        $file = Mage::helper('xposapi')->getCacheDir() . DS . $cache_filename;
        if (!$postData->update && file_exists($file) && is_readable($file)) {
            $this->getResponse()->setHttpResponseCode(200);
            $this->getResponse()->setHeader('Content-Type', 'application/json', true);
            $data = file_get_contents($file);
            echo $data;
            exit(0);
        }
        
        try {
            $order_list = $orderModel->getOrderList($params);
            $this->_outputJSON($order_list, 200);
        } catch (Exception $ex) {
            $this->_outputJSON(
                array("error_message" => $ex->getMessage()), 500
            );
        }
    }
    
    /**
     * #1279
     * API to generate refund for order
     * Call: POST /xposrest/order/refund
     */
    public function refundAction() {
        $postData = json_decode(file_get_contents('php://input'));
        
        try {
            $creditmemo_id = Mage::getModel('xposapi/order')->generateRefund($postData);
            $this->_outputJSON(array('creditmemo_id' => $creditmemo_id), 201);
        } catch (Exception $e) {
            $this->_outputJSON(
                array("error_message" => $e->getMessage()), 500
            );
        }
    }
    
    /**
     * #1282
     * API to return price rule from coupon code
     * Call: POST /xposrest/order/coupon
     */
    public function couponAction() {
        $postData = json_decode(file_get_contents('php://input'));
        
        try {
            $data = Mage::getModel('xposapi/order')->getRuleFromCouponCode($postData->coupon_code);
            if (empty($data['coupon_code'])) {
                $this->_outputJSON(
                    array("error_message" => 'Coupon code "' . $postData->coupon_code . '" is not valid.'), 500
                );
            } else {
                $this->_outputJSON($data, 200);
            }
        } catch (Exception $ex) {
            $this->_outputJSON(
                array("error_message" => $ex->getMessage()), 500
            );
        }
    }
}