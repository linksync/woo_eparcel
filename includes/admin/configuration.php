<?php
class LinksynceparcelAdminConfiguration
{
	public static function output()
	{
		global $is_greater_than_21;
		if (isset($_POST['submitConfiguration'])) 
		{
			$data = $_POST['linksynceparcel'];
			$data['email_body'] = $_POST['linksynceparcel_email_body'];
			if(!isset($data['declared_value'])) {
				$data['declared_value'] = 0;
			}
			if(!isset($data['has_commercial_value'])) {
				$data['has_commercial_value'] = 0;
			}
			if(!isset($data['product_classification'])) {
				$data['product_classification'] = 991;
			}
			$errors = LinksynceparcelValidator::validateConfiguration($data);
			if($errors)
			{
				$error = implode('<br/>',$errors);
			}
			else
			{
				LinksynceparcelHelper::saveConfiguration($data);
				LinksynceparcelHelper::updateShippingChargecode($data);
				
				$result = __( 'Configuration updated successfully.', 'linksynceparcel' );
				
				try
				{
					LinksynceparcelApi::seteParcelMerchantDetails();
					$result .= '<br/>'.__( 'eParcel Merchant Details updated successfully.', 'linksynceparcel' );
				}
				catch(Exception $e)
				{
					$message = 'Updating Merchant Details, Error:'.$e->getMessage();
					$error = $message;
					LinksynceparcelHelper::log($message);
				}
				
				try
				{
					LinksynceparcelApi::setReturnAddress();
					$result .= '<br/>'. __( 'Return Address updated successfully.', 'linksynceparcel' );
				}
				catch(Exception $e)
				{
					$message = 'Set Return Address, Error:'.$e->getMessage();
					$error = ($error ? '<br>':'').$message;
					LinksynceparcelHelper::log($message);
				}
			}
		}
				
		$statuses = LinksynceparcelHelper::getOrderStatuses();
		$states = LinksynceparcelHelper::getStates();
		$countries = LinksynceparcelHelper::getWooCountries();
		$formats = LinksynceparcelHelper::getLabelFormats();
		
		include_once(linksynceparcel_DIR.'views/admin/configuration.php');
	}
}
?>