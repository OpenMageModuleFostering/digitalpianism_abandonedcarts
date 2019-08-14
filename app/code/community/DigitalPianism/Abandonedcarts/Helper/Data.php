<?php

class DigitalPianism_Abandonedcarts_Helper_Data extends Mage_Core_Helper_Abstract 
{
	protected $logFileName = 'digitalpianism_abandonedcarts.log';
	
	/**
	 * Log data
	 * @param string|object|array data to log
	 */
	public function log($data) 
	{
		Mage::log($data, null, $this->logFileName);
	}
	
	public function isEnabled()
	{
		return Mage::getStoreConfig('abandonedcartsconfig/options/enable');
	}
	
	public function isSaleEnabled()
	{
		return Mage::getStoreConfig('abandonedcartsconfig/options/enable_sale');
	}
	
	public function getDryRun()
	{
		return Mage::getStoreConfig('abandonedcartsconfig/options/dryrun');
	}
	
	public function getTestEmail()
	{
		return Mage::getStoreConfig('abandonedcartsconfig/options/testemail');
	}
    
}