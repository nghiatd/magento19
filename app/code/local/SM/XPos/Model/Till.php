<?php

class SM_XPos_Model_Till extends Mage_Core_Model_Abstract{
    public function _construct(){
        parent::_construct();
        $this->_init('xpos/till');
    }

    public function getType(){
        return $this->getData('type');
    }
}