<?php

/**
 * Class DigitalPianism_Abandonedcarts_Model_Resource_Log_Collection
 */
class DigitalPianism_Abandonedcarts_Model_Resource_Log_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract {

    public function _construct()
    {
        parent::_construct();
        $this->_init('abandonedcarts/log');
    }

}