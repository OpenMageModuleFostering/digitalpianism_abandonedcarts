<?php

/**
 * Class DigitalPianism_Abandonedcarts_Model_Observer
 */
class DigitalPianism_Abandonedcarts_Model_Observer extends Mage_Core_Model_Abstract
{

	protected $recipients = array();
	protected $saleRecipients = array();
	protected $today = "";
	
	public function setToday()
	{
		// Date handling	
		$store = Mage_Core_Model_App::ADMIN_STORE_ID;
		$timezone = Mage::app()->getStore($store)->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
		date_default_timezone_set($timezone);
	
		// Current date
		$currentdate = date("Ymd");

		$day = (int)substr($currentdate,-2);
		$month = (int)substr($currentdate,4,2);
		$year = (int)substr($currentdate,0,4);

		$date = array('year' => $year,'month' => $month,'day' => $day,'hour' => 23,'minute' => 59,'second' => 59);
		
		$today = new Zend_Date($date);
		$today->setTimeZone("UTC");

		date_default_timezone_set($timezone);

		$this->today = $today->toString("Y-MM-dd HH:mm:ss");
	}

    /**
     * @return string
     */
    public function getToday()
	{
		return $this->today;
	}

    /**
     * @return array
     */
    public function getRecipients()
	{
		return $this->recipients;
	}

    /**
     * @return array
     */
    public function getSaleRecipients()
	{
		return $this->saleRecipients;
	}

    /**
     * @param $args
     */
    public function generateRecipients($args)
	{
		// Test if the customer is already in the array
		if (!array_key_exists($args['row']['customer_email'], $this->recipients))
		{
			// Create an array of variables to assign to template 
			$emailTemplateVariables = array(); 
			
			// Array that contains the data which will be used inside the template
			$emailTemplateVariables['fullname'] = $args['row']['customer_firstname'].' '.$args['row']['customer_lastname']; 
			$emailTemplateVariables['firstname'] = $args['row']['customer_firstname'];
			$emailTemplateVariables['productname'] = $args['row']['product_name'];
					
			// Assign the values to the array of recipients
			$this->recipients[$args['row']['customer_email']]['cartId'] = $args['row']['cart_id'];
			
			$emailTemplateVariables['extraproductcount'] = 0;
		}
		else
		{
			// We create some extra variables if there is several products in the cart
			$emailTemplateVariables = $this->recipients[$args['row']['customer_email']]['emailTemplateVariables'];
			// We increase the product count
			$emailTemplateVariables['extraproductcount'] += 1;
		}
		// Assign the array of template variables
		$this->recipients[$args['row']['customer_email']]['emailTemplateVariables'] = $emailTemplateVariables;
	}

    /**
     * @param $args
     */
    public function generateSaleRecipients($args)
	{
		// Double check if the special from date is set
		if (!array_key_exists('product_special_from_date',$args['row']) || !$args['row']['product_special_from_date'])
		{
			// If not we use today for the comparison
			$fromDate = $this->getToday();
		}
		else $fromDate = $args['row']['product_special_from_date'];
		
		// Do the same for the special to date
		if (!array_key_exists('product_special_to_date',$args['row']) || !$args['row']['product_special_to_date'])
		{
			$toDate = $this->getToday();
		}
		else $toDate = $args['row']['product_special_to_date'];
		
		// We need to ensure that the price in cart is higher than the new special price
		// As well as the date comparison in case the sale is over or hasn't started
		if ($args['row']['product_price_in_cart'] > 0.00 
			&& $args['row']['product_special_price'] > 0.00 
			&& ($args['row']['product_price_in_cart'] > $args['row']['product_special_price'])
			&& ($fromDate <= $this->getToday())
			&& ($toDate >= $this->getToday()))
		{
			
			// Test if the customer is already in the array
			if (!array_key_exists($args['row']['customer_email'], $this->saleRecipients))
			{
				// Create an array of variables to assign to template 
				$emailTemplateVariables = array(); 
				
				// Array that contains the data which will be used inside the template
				$emailTemplateVariables['fullname'] = $args['row']['customer_firstname'].' '.$args['row']['customer_lastname']; 
				$emailTemplateVariables['firstname'] = $args['row']['customer_firstname'];
				$emailTemplateVariables['productname'] = $args['row']['product_name']; 
				$emailTemplateVariables['cartprice'] = number_format($args['row']['product_price_in_cart'],2); 
				$emailTemplateVariables['specialprice'] = number_format($args['row']['product_special_price'],2);
				
				// Assign the values to the array of recipients
				$this->saleRecipients[$args['row']['customer_email']]['cartId'] = $args['row']['cart_id'];
			}
			else
			{
				// We create some extra variables if there is several products in the cart
				$emailTemplateVariables = $this->saleRecipients[$args['row']['customer_email']]['emailTemplateVariables'];
				// Discount amount
				// If one product before
				if (!array_key_exists('discount',$emailTemplateVariables))
				{
					$emailTemplateVariables['discount'] = $emailTemplateVariables['cartprice'] - $emailTemplateVariables['specialprice'];
				}
				// We add the discount on the second product
				$moreDiscount = number_format($args['row']['product_price_in_cart'],2) - number_format($args['row']['product_special_price'],2);
				$emailTemplateVariables['discount'] += $moreDiscount;
				// We increase the product count
				if (!array_key_exists('extraproductcount',$emailTemplateVariables))
				{
					$emailTemplateVariables['extraproductcount'] = 0;
				}
				$emailTemplateVariables['extraproductcount'] += 1;
			}
			
			// Add currency codes to prices
			$emailTemplateVariables['cartprice'] = Mage::helper('core')->currency($emailTemplateVariables['cartprice'], true, false);
			$emailTemplateVariables['specialprice'] = Mage::helper('core')->currency($emailTemplateVariables['specialprice'], true, false);
			if (array_key_exists('discount',$emailTemplateVariables))
			{
				$emailTemplateVariables['discount'] = Mage::helper('core')->currency($emailTemplateVariables['discount'], true, false);
			}
	
			// Assign the array of template variables
			$this->saleRecipients[$args['row']['customer_email']]['emailTemplateVariables'] = $emailTemplateVariables;
		}
	}

    /**
     * @param $dryrun
     * @param $testemail
     */
    public function sendSaleEmails($dryrun,$testemail)
	{
		try
		{			
			// Get the transactional email template
			$templateId = Mage::getStoreConfig('abandonedcartsconfig/options/email_template_sale');
			// Get the sender
			$sender = array();
			$sender['email'] = Mage::getStoreConfig('abandonedcartsconfig/options/email');
			$sender['name'] = Mage::getStoreConfig('abandonedcartsconfig/options/name');
			
			// Send the emails via a loop
			foreach ($this->getSaleRecipients() as $email => $recipient)
			{
				// Don't send the email if dryrun is set
				if ($dryrun)
				{
					// Log data when dried run
					Mage::helper('abandonedcarts')->log(__METHOD__);
					Mage::helper('abandonedcarts')->log($recipient['emailTemplateVariables']);
					// If the test email is set and found
					if (isset($testemail) && $email == $testemail)
					{
						Mage::helper('abandonedcarts')->log(__METHOD__ . "sendAbandonedCartsSaleEmail test: " . $email);
						// Send the test email
						Mage::getModel('core/email_template')
								->sendTransactional(
										$templateId,
										$sender,
										$email,
										$recipient['emailTemplateVariables']['fullname'] ,
										$recipient['emailTemplateVariables'],
										null);
					}
				}
				else
				{
					Mage::helper('abandonedcarts')->log(__METHOD__ . "sendAbandonedCartsSaleEmail: " . $email);
					
					// Send the email
					Mage::getModel('core/email_template')
							->sendTransactional(
									$templateId,
									$sender,
									$email,
									$recipient['emailTemplateVariables']['fullname'] ,
									$recipient['emailTemplateVariables'],
									null);
				}
				
				// Load the quote
				$quote = Mage::getModel('sales/quote')->load($recipient['cartId']);

				// We change the notification attribute
				$quote->setAbandonedSaleNotified(1);
				
				// Save only if dryrun is false or if the test email is set and found
				if (!$dryrun || (isset($testemail) && $email == $testemail))
				{
					$quote->save();
				}
			}
		}
		catch (Exception $e)
		{
			Mage::helper('abandonedcarts')->log(__METHOD__ . " " . $e->getMessage());
		}
	}

    /**
     * @param $dryrun
     * @param $testemail
     */
    public function sendEmails($dryrun,$testemail)
	{
		try
		{		
			// Get the transactional email template
			$templateId = Mage::getStoreConfig('abandonedcartsconfig/options/email_template');
			// Get the sender
			$sender = array();
			$sender['email'] = Mage::getStoreConfig('abandonedcartsconfig/options/email');
			$sender['name'] = Mage::getStoreConfig('abandonedcartsconfig/options/name');
			
			// Send the emails via a loop
			foreach ($this->getRecipients() as $email => $recipient)
			{
				// Don't send the email if dryrun is set
				if ($dryrun)
				{
					// Log data when dried run
					Mage::helper('abandonedcarts')->log(__METHOD__);
					Mage::helper('abandonedcarts')->log($recipient['emailTemplateVariables']);
					// If the test email is set and found
					if (isset($testemail) && $email == $testemail)
					{
						Mage::helper('abandonedcarts')->log(__METHOD__ . "sendAbandonedCartsEmail test: " . $email);
						// Send the test email
						Mage::getModel('core/email_template')
								->sendTransactional(
										$templateId,
										$sender,
										$email,
										$recipient['emailTemplateVariables']['fullname'] ,
										$recipient['emailTemplateVariables'],
										null);
					}
				}
				else
				{
					Mage::helper('abandonedcarts')->log(__METHOD__ . "sendAbandonedCartsEmail: " . $email);
					
					// Send the email
					Mage::getModel('core/email_template')
							->sendTransactional(
									$templateId,
									$sender,
									$email,
									$recipient['emailTemplateVariables']['fullname'] ,
									$recipient['emailTemplateVariables'],
									null);
				}
				
				// Load the quote
				$quote = Mage::getModel('sales/quote')->load($recipient['cartId']);

				// We change the notification attribute
				$quote->setAbandonedNotified(1);
				
				// Save only if dryrun is false or if the test email is set and found
				if (!$dryrun || (isset($testemail) && $email == $testemail))
				{
					$quote->save();
				}
			}
		}
		catch (Exception $e)
		{
			Mage::helper('abandonedcarts')->log(__METHOD__ . " " . $e->getMessage());
		}
	}

	/**
	 * Send notification email to customer with abandoned cart containing sale products
	 * @param boolean $dryrun if dryrun is set to true, it won't send emails and won't alter quotes
	 * @param string $testemail email to test
	 */
	public function sendAbandonedCartsSaleEmail($dryrun = false, $testemail = null) 
	{
		try
		{
			if (Mage::helper('abandonedcarts')->getDryRun()) $dryrun = true;
			if (Mage::helper('abandonedcarts')->getTestEmail()) $testemail = Mage::helper('abandonedcarts')->getTestEmail();
			if (Mage::helper('abandonedcarts')->isSaleEnabled())
			{
				$this->setToday();
				
				// Get the attribute id for the status attribute
				$eavAttribute = Mage::getModel('eav/entity_attribute');
				$statusId = $eavAttribute->getIdByCode('catalog_product', 'status');
				
				// Loop through the stores
				foreach (Mage::app()->getWebsites() as $website) {
					// Get the website id
					$websiteId = $website->getWebsiteId();
					foreach ($website->getGroups() as $group) {
						$stores = $group->getStores();
						foreach ($stores as $store) {
						
							// Get the store id
							$storeId = $store->getStoreId();
				
							// Init the store to be able to load the quote and the collections properly
							Mage::app()->init($storeId,'store');
							
							// Get the product collection
							$collection = Mage::getResourceModel('catalog/product_collection')->setStore($storeId);
							
							// First collection: carts with products that became on sale
							// Join the collection with the required tables
							$collection->getSelect()
								->reset(Zend_Db_Select::COLUMNS)
								->columns(array('e.entity_id AS product_id',
												'e.sku',
												'catalog_flat.name as product_name',
												'catalog_flat.price as product_price',
												'catalog_flat.special_price as product_special_price',
												'catalog_flat.special_from_date as product_special_from_date',
												'catalog_flat.special_to_date as product_special_to_date',
												'quote_table.entity_id as cart_id',
												'quote_table.updated_at as cart_updated_at',
												'quote_table.abandoned_sale_notified as has_been_notified',
												'quote_items.price as product_price_in_cart',
												'quote_table.customer_email as customer_email',
												'quote_table.customer_firstname as customer_firstname',
												'quote_table.customer_lastname as customer_lastname'
												)
											)
								->joinInner(
									array('quote_items' => Mage::getSingleton("core/resource")->getTableName('sales_flat_quote_item')),
									'quote_items.product_id = e.entity_id AND quote_items.price > 0.00',
									null)
								->joinInner(
									array('quote_table' => Mage::getSingleton("core/resource")->getTableName('sales_flat_quote')),
									'quote_items.quote_id = quote_table.entity_id AND quote_table.items_count > 0 AND quote_table.is_active = 1 AND quote_table.customer_email IS NOT NULL AND quote_table.abandoned_sale_notified = 0 AND quote_table.store_id = '.$storeId,
									null)
								->joinInner(
									array('catalog_flat' => Mage::getSingleton("core/resource")->getTableName('catalog_product_flat_'.$storeId)),
									'catalog_flat.entity_id = e.entity_id',
									null)
								->joinInner(
									array('catalog_enabled'	=>	Mage::getSingleton("core/resource")->getTableName('catalog_product_entity_int')),
									'catalog_enabled.entity_id = e.entity_id AND catalog_enabled.attribute_id = '.$statusId.' AND catalog_enabled.value = 1',
									null)
								->joinInner(
									array('inventory' => Mage::getSingleton("core/resource")->getTableName('cataloginventory_stock_status')),
									'inventory.product_id = e.entity_id AND inventory.stock_status = 1 AND inventory.website_id = '.$websiteId,
									null)
								->order('quote_table.updated_at DESC');
													
							//echo $collection->printlogquery(true);
							$collection->load();
							
							// Skip the rest of the code if the collection is empty
							if ($collection->getSize() == 0)    continue;
							
							// Call iterator walk method with collection query string and callback method as parameters
							// Has to be used to handle massive collection instead of foreach
							Mage::getSingleton('core/resource_iterator')->walk($collection->getSelect(), array(array($this, 'generateSaleRecipients')));
							
							// Send the emails
							$this->sendSaleEmails($dryrun,$testemail);
						}
					}
				}
			}
		}
		catch (Exception $e)
		{
			Mage::helper('abandonedcarts')->log(__METHOD__ . " " . $e->getMessage());
		}
	}

    /**
     * Send notification email to customer with abandoned carts after the number of days specified in the config
     * @param bool $nodate
     * @param boolean $dryrun if dryrun is set to true, it won't send emails and won't alter quotes
     * @param string $testemail email to test
     */
	public function sendAbandonedCartsEmail($nodate = false, $dryrun = false, $testemail = null)
	{
		if (Mage::helper('abandonedcarts')->getDryRun()) $dryrun = true;
		if (Mage::helper('abandonedcarts')->getTestEmail()) $testemail = Mage::helper('abandonedcarts')->getTestEmail();
		try
		{
			if (Mage::helper('abandonedcarts')->isEnabled())
			{
				// Date handling	
				$store = Mage_Core_Model_App::ADMIN_STORE_ID;
				$timezone = Mage::app()->getStore($store)->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
				date_default_timezone_set($timezone);
				
				// If the nodate parameter is set to false
				if (!$nodate)
				{
					// Get the delay provided and convert it to a proper date
					$delay = Mage::getStoreConfig('abandonedcartsconfig/options/notify_delay');
					$delay = date('Y-m-d H:i:s', time() - $delay * 24 * 3600);
				}
				else
				{
					// We create a date in the future to handle all abandoned carts
					$delay = date('Y-m-d H:i:s', strtotime("+7 day"));
				}
				
				// Get the attribute id for the status attribute
				$eavAttribute = Mage::getModel('eav/entity_attribute');
				$statusId = $eavAttribute->getIdByCode('catalog_product', 'status');
				
				// Loop through the stores
				foreach (Mage::app()->getWebsites() as $website) {
					// Get the website id
					$websiteId = $website->getWebsiteId();
					foreach ($website->getGroups() as $group) {
						$stores = $group->getStores();
						foreach ($stores as $store) {
						
							// Get the store id
							$storeId = $store->getStoreId();
							// Init the store to be able to load the quote and the collections properly
							Mage::app()->init($storeId,'store');
							
							// Get the product collection
							$collection = Mage::getResourceModel('catalog/product_collection')->setStore($storeId);
							
							// First collection: carts with products that became on sale
							// Join the collection with the required tables
							$collection->getSelect()
								->reset(Zend_Db_Select::COLUMNS)
								->columns(array('e.entity_id AS product_id',
												'e.sku',
												'catalog_flat.name as product_name',
												'catalog_flat.price as product_price',
												'quote_table.entity_id as cart_id',
												'quote_table.updated_at as cart_updated_at',
												'quote_table.abandoned_notified as has_been_notified',
												'quote_table.customer_email as customer_email',
												'quote_table.customer_firstname as customer_firstname',
												'quote_table.customer_lastname as customer_lastname'
												)
											)
								->joinInner(
									array('quote_items' => Mage::getSingleton("core/resource")->getTableName('sales_flat_quote_item')),
									'quote_items.product_id = e.entity_id AND quote_items.price > 0.00',
									null)
								->joinInner(
									array('quote_table' => Mage::getSingleton("core/resource")->getTableName('sales_flat_quote')),
									'quote_items.quote_id = quote_table.entity_id AND quote_table.items_count > 0 AND quote_table.is_active = 1 AND quote_table.customer_email IS NOT NULL AND quote_table.abandoned_notified = 0 AND quote_table.updated_at < "'.$delay.'" AND quote_table.store_id = '.$storeId,
									null)
								->joinInner(
									array('catalog_flat' => Mage::getSingleton("core/resource")->getTableName('catalog_product_flat_'.$storeId)),
									'catalog_flat.entity_id = e.entity_id',
									null)
								->joinInner(
									array('catalog_enabled'	=>	Mage::getSingleton("core/resource")->getTableName('catalog_product_entity_int')),
									'catalog_enabled.entity_id = e.entity_id AND catalog_enabled.attribute_id = '.$statusId.' AND catalog_enabled.value = 1',
									null)
								->joinInner(
									array('inventory' => Mage::getSingleton("core/resource")->getTableName('cataloginventory_stock_status')),
									'inventory.product_id = e.entity_id AND inventory.stock_status = 1 AND website_id = '.$websiteId,
									null)
								->order('quote_table.updated_at DESC');
							
							// echo $collection->printlogquery(true);
							$collection->load();
							
							// Call iterator walk method with collection query string and callback method as parameters
							// Has to be used to handle massive collection instead of foreach
							Mage::getSingleton('core/resource_iterator')->walk($collection->getSelect(), array(array($this, 'generateRecipients')));
							
							// Send the emails
							$this->sendEmails($dryrun,$testemail);
						}
					}
				}
			}
		}
		catch (Exception $e)
		{
			Mage::helper('abandonedcarts')->log(__METHOD__ . " " . $e->getMessage());
		}
	}
}