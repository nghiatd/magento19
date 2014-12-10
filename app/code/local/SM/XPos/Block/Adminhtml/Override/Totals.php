<?php
class SM_XPos_Block_Adminhtml_Override_Totals extends Mage_Adminhtml_Block_Sales_Order_Create_Totals
{
    public function getTotalData($total_code){
        $totals = $this->getTotals();
        $value = 0;
        if($totals[$total_code]){
            return $totals[$total_code]->getData('value');
        }
        return $value;
    }

}
?>
