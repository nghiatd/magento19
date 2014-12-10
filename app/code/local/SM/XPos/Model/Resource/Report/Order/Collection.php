<?php
/**
 * Author: HieuNT
 * Email: hieunt@smartosc.com
 */

class SM_XPos_Model_Resource_Report_Order_Collection extends Mage_Sales_Model_Resource_Report_Collection_Abstract {
    /**
     * Aggregated Data Table
     *
     * @var string
     */
    protected $_aggregationTable = 'sales/order';

    protected $_mainTable = 'sales/order';

    protected $_typeDateRange = 'created_at';

    protected $_selectedColumns = array();

    /**
     * Initialize custom resource model
     *
     */
    public function __construct()
    {
        parent::_construct();
        $this->setModel('adminhtml/report_item');
        $this->_resource = Mage::getResourceModel('sales/report')->init($this->_aggregationTable);
        $this->setConnection($this->getResource()->getReadConnection());
    }

    /**
     * Apply order status filter
     *
     * @return Mage_Sales_Model_Resource_Report_Collection_Abstract
     */
    protected function _applyOrderStatusFilter()
    {
        if (is_null($this->_orderStatus)) {
            return $this;
        }
        $orderStatus = $this->_orderStatus;
        if (!is_array($orderStatus)) {
            $orderStatus = array($orderStatus);
        }
        $this->getSelect()->where('status IN(?)', $orderStatus);
        return $this;
    }
    /**
     * Set the date range type - it could be created_at and updated_at
     */

    public function setDateRangeType($type) {
        $this->_typeDateRange = $type;
        return $this;
    }
    /**
     * Apply date range filter
     *
     * @return Mage_Sales_Model_Resource_Report_Collection_Abstract
     */
    protected function _applyDateRangeFilter()
    {
        // Remember that field PERIOD is a DATE(YYYY-MM-DD) in all databases including Oracle
        if ($this->_typeDateRange == 'created_at') {
            if ($this->_from !== null) {
                $this->getSelect()->where('tzo_created_at >= ?', $this->_from .' 00:00:00');
            }
            if ($this->_to !== null) {
                $this->getSelect()->where('tzo_updated_at <= ?', $this->_to .' 23:59:59');
            }

            return $this;
        } else {
            if ($this->_from !== null) {
                $this->getSelect()->where('tzo_created_at >= ?', $this->_from .' 00:00:00');
            }
            if ($this->_to !== null) {
                $this->getSelect()->where('tzo_updated_at <= ?', $this->_to .' 23:59:59');
            }

            return $this;
        }
    }



    protected function _initSelect()
    {
        if (!$this->isTotals()) {
            $this->getSelect()->from($this->getResource()->getMainTable());
        } else {
            $this->_selectedColumns = array(
                'entity_id'                 => 'entity_id',
                'updated_at'                => 'updated_at',
                'created_at'                => 'created_at',
                'subtotal'                  => 'SUM(subtotal)',
                'tax_amount'                => 'SUM(tax_amount)',
                'grand_total'               => 'SUM(grand_total)',
                'discount_amount'           => 'SUM(discount_amount)',
                'total_paid'                => 'SUM(total_paid)',
                'total_refunded'            => 'SUM(total_refunded)',
                'shipping_amount'           => 'SUM(shipping_amount)',
                'xpos_user_id'              => 'xpos_user_id',
                'shipping_method'           => 'shipping_method',
                'store_name'                => 'store_name',
            );

            $this->getSelect()->from($this->getResource()->getMainTable(),$this->_selectedColumns);
        }

        return $this;
    }
}