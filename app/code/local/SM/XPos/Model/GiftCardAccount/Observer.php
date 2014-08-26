<?php
/**
 * Created by PhpStorm.
 * User: Le Nam
 * Date: 7/16/14
 * Time: 9:55 AM
 */
class SM_XPos_Model_GiftCardAccount_Observer extends Mage_Core_Model_Abstract
{
    protected function _getModel($modelClass = '', $arguments = array())
    {
        return Mage::getModel($modelClass, $arguments);
    }

    public function processOrderCreationData(Varien_Event_Observer $observer){
        $model = $observer->getEvent()->getOrderCreateModel();
        $request = $observer->getEvent()->getRequest();
        $quote = $model->getQuote();
        if (isset($request['giftcard_add'])) {
            $code = $request['giftcard_add'];
            try {
                $this->_getModel('enterprise_giftcardaccount/giftcardaccount')
                    ->loadByCode($code)
                    ->addToCart(true, $quote);
                Mage::throwException(
                    Mage::helper('enterprise_giftcardaccount')->__('Appy Gift Card Sucessfully..')
                );
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session_quote')->addError(
                    $e->getMessage()
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session_quote')->addException(
                    $e, Mage::helper('enterprise_giftcardaccount')->__('Cannot apply Gift Card')
                );
            }
        }

        if (isset($request['giftcard_remove'])) {
            $code = $request['giftcard_remove'];

            try {
                $this->_getModel('enterprise_giftcardaccount/giftcardaccount')
                    ->loadByCode($code)
                    ->removeFromCart(false, $quote);
                Mage::throwException(
                    Mage::helper('enterprise_giftcardaccount')->__('Remove Gift Card Sucessfully..')
                );
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session_quote')->addError(
                    $e->getMessage()
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session_quote')->addException(
                    $e, Mage::helper('enterprise_giftcardaccount')->__('Cannot remove Gift Card')
                );
            }
        }
        return $this;
    }
}