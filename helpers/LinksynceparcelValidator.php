<?php
class LinksynceparcelValidator
{
	public static function validateConfiguration($data)
	{
		$errors = array();
		if(empty($data['laid']))
			$errors[] = 'LAID is a required field.';
		if(empty($data['merchant_location_id']))
			$errors[] = 'eParcel Merchant Location ID is a required field.';
		if(empty($data['post_charge_to_account']))
			$errors[] = 'eParcel Post Charge to Account is a required field.';
		if(empty($data['merchant_id']))
			$errors[] = 'Merchant Id is a required field.';
		if(empty($data['lodgement_facility']))
			$errors[] = 'Lodgement facility is a required field.';
		if(empty($data['sftp_username']))
			$errors[] = 'SFTP username is a required field.';
		if(empty($data['sftp_password']))
			$errors[] = 'SFTP password is a required field.';
		if(empty($data['lps_username'])) {
			$errors[] = 'LPS username is a required field.';
		} else {
			if(is_email($data['lps_username'])) {
				if(strpos($data['lps_username'], '@auspost.com.au') === false) {
					$errors[] = 'LPS username is invalid. Similar to "6f4d9019-cxx4-4e5c-x786-0753b97d903e@auspost.com.au"';
				}
			} else {
				$errors[] = 'LPS username is invalid.';
			}
		}
		if(empty($data['lps_password']))
			$errors[] = 'LPS password is a required field.';
		if(empty($data['return_address_name']))
			$errors[] = 'Return Address Name is a required field.';
		if(empty($data['return_address_line1']))
			$errors[] = 'Return Address Line 1 is a required field.';
		if(empty($data['return_address_postcode']))
			$errors[] = 'Return Address Postcode is a required field.';
		else if(!is_numeric($data['return_address_postcode']) || $data['return_address_postcode'] < 0)
			$errors[] = 'Return Address Postcode is invalid.';
		else if(strlen($data['return_address_postcode']) < 4 || strlen($data['return_address_postcode']) > 4)
			$errors[] = 'Return Address Postcode should be in 4 digits.';
		if(empty($data['return_address_statecode']))
			$errors[] = 'Return Address State code is a required field.';
		if(empty($data['return_address_suburb']))
			$errors[] = 'Return Address Suburb is a required field.';
		if(!empty($data['default_insurance_value']) && (!is_numeric($data['default_insurance_value']) || $data['default_insurance_value'] < 0))
			$errors[] = 'Default Insurance value is invalid.';
			
		if(isset($data['allowance_value']) && !empty($data['allowance_value']) && (!is_numeric($data['allowance_value']) || $data['allowance_value'] < 0))
			$errors[] = 'Packaging Allowance Value is invalid.';
			
		if(!empty($data['default_article_weight']) && (!is_numeric($data['default_article_weight']) || $data['default_article_weight'] < 0))
			$errors[] = 'Default Article Weight is invalid.';
		if(!empty($data['default_article_height']) && (!is_numeric($data['default_article_height']) || $data['default_article_height'] < 0))
			$errors[] = 'Default Article Height is invalid.';
		if(!empty($data['default_article_width']) && (!is_numeric($data['default_article_width']) || $data['default_article_width'] < 0))
			$errors[] = 'Default Article Width is invalid.';
		if(!empty($data['default_article_length']) && (!is_numeric($data['default_article_length']) || $data['default_article_length'] < 0))
			$errors[] = 'Default Article Length is invalid.';
		if(!empty($data['from_email_address']) && !is_email($data['from_email_address']))
			$errors[] = 'From email address is invalid.';
		if(isset($data['declared_value']) && $data['declared_value']==0) {
			if(!empty($data['declared_value_text']) && (!is_numeric($data['declared_value_text']) || $data['declared_value_text'] < 0))
				$errors[] = 'Order value as Declared Value is invalid.';
		}
		if(!empty($data['has_commercial_value']) && $data['has_commercial_value']==1) {
			if(empty($data['product_classification_text']))
				$errors[] = 'Classification Explanation is a required field.';
		}
		if(!empty($data['hs_tariff']))
			if(is_numeric($data['hs_tariff'])) {
				$count_digits = strlen($data['hs_tariff']);
				if($count_digits < 6 || $count_digits > 12)
					$errors[] = 'HS Tariffs must be between 6 - 12 digits.';
			} else {
				$errors[] = 'HS Tariffs must be a number.';
			}
			
		if(empty($data['default_contents']))
			$errors[] = 'Default Contents is a required field.';
			
		if(count($errors) > 0)
			return $errors;
		return false;
	}
	
	public static function validateArticlePresets($data)
	{
		$errors = array();
		if(empty($data['name']))
			$errors[] = 'Preset Name is a required field.';
		if(empty($data['weight']))
			$errors[] = 'Weight is a required field.';
		else if(!is_numeric($data['weight']) || $data['weight'] < 0)
			$errors[] = 'Weight is invalid.';
		if(!is_numeric($data['height']) || $data['height'] < 0)
			$errors[] = 'Height is invalid.';
		if(!is_numeric($data['width']) || $data['width'] < 0)
			$errors[] = 'Width is invalid.';
		if(!is_numeric($data['length']) || $data['length'] < 0)
			$errors[] = 'Length is invalid.';
		if(count($errors) > 0)
			return $errors;
		return false;
	}
	
	public static function validateAssignShippingTypes($data)
	{
		$errors = array();
		if(empty($data['method']))
			$errors[] = 'Method is a required field.';
		if(empty($data['charge_code']))
			$errors[] = 'Charge code is a required field.';
		if(count($errors) > 0)
			return $errors;
		return false;
	}
	
	public static function requiredConsignmentsField() {
		$errors = array();
		if((isset($_POST['has_commercial_value']) && $_POST['has_commercial_value'] == 1) || (isset($_POST['product_classification']) && $_POST['product_classification'] == 991)) {
			if(empty($_POST['product_classification_text']))
				$errors[] = '<strong>Product Classification text field</strong> is a required field.';
		}
		$country_origin = $_POST['country_origin'];
		if(!isset($country_origin)) {
			$country_origin = get_option('linksynceparcel_country_origin');
		}
		if(empty($country_origin))
			$errors[] = '<strong>Country of Origin</strong> is a required field.';
		
		if((isset($_POST['has_commercial_value']) && $_POST['has_commercial_value'] == 1) && empty($_POST['hs_tariff']))
			$errors[] = '<strong>HS Tarrif Number</strong> is a required field.';
		
		if(!empty($_POST['hs_tariff']))
			if(is_numeric($_POST['hs_tariff'])) {
				$count_digits = strlen($_POST['hs_tariff']);
				if($count_digits < 6 || $count_digits > 12)
					$errors[] = '<strong>HS Tariffs</strong> must be between 6 - 12 digits.';
			} else {
				$errors[] = '<strong>HS Tariffs</strong> must be a number.';
			}
		
		if(count($errors) > 0)
			return $errors;
		
		return false;
	}
	
	public static function validateInternationalCosignmentsValue($data, $chargecodedata, $country=false, $weight = false, $totalcost = false) {

		if($chargecodedata['serviceType'] == 'international' && $country == 'AU') {
			return array('error_msg' => 'International chargecode could not be use for domestic country. Please check and try again.');
		}
		
		if($chargecodedata['serviceType'] != 'international' && $country != 'AU') {
			return array('error_msg' => 'Domestic chargecode could not be use for international. Please check and try again.');
		}
		
		if($country != 'AU') {
			if($data['number_of_articles'] > 1) {
				return array('error_msg' => 'International article cannot be more/less than 1.');
			}
			
			// All validated International Articles
			$intArticle = array(
				'Int. Economy Air' 	=> array('weight' => 20, 'insurance' => 5000),
				'Int. Express Courier' => array('weight' => 20, 'insurance' => 5000),
				'Int. Express Courier Document' => array('weight' => 0.5, 'insurance' => 5000),
				'Int. Express Post' => array('weight' => 20, 'insurance' => 5000),
				'Int. Pack & Track' => array('weight' => 2, 'insurance' => 500),
				'Int. Registered' 	=> array('weight' => 2, 'insurance' => 5000),
			);
			
			$label = $chargecodedata['labelType'];
			if($chargecodedata['key'] == 'int_pack_track') {
				$isvalidCountries = self::validCountry();
				if(!array_key_exists($country,$isvalidCountries)) {
					return array('error_msg' => 'Pack & Track service is not permitted for this order. Valid countries for Pack & Track service are '. implode(', ', $isvalidCountries));
				}
				if($intArticle[$label]['weight'] < $weight && $intArticle[$label]['insurance'] < $totalcost) {
					return array('error_msg' => $chargecodedata['name'] .' reached the maximum article weight of '. $intArticle[$label]['weight'] .'kg and maximum cost of $'. number_format($intArticle[$label]['insurance'], 2) .'.');
				}
			}
			
			if(!empty($intArticle[$label]['weight'])){	
				if($intArticle[$label]['weight'] < $weight) {
					return array('error_msg' => $chargecodedata['name'] .' reached the maximum article weight of '. $intArticle[$label]['weight'] .'kg.');
				}
			}
			
			if($data['insurance'] == 1 && $intArticle[$label]['insurance'] < $data['insurance_value']) {
				return array('error_msg' => $chargecodedata['name'] .' reached the maximum insurance value of $'. number_format($intArticle[$label]['insurance'], 2) .'.');
			}
		}
		return true;
	}
	
	public static function validCountry() {
		$countries = array(
			'BE' => 'Belgium',
			'CA' => 'Canada',
			'CN' => 'China',
			'HR' => 'Croatia',
			'DK' => 'Denmark',
			'EE' => 'Estonia',
			'FR' => 'France',
			'DE' => 'Germany',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IE' => 'Ireland',
			'IL' => 'Israel',
			'KR' => 'Korea, Republic of (South Korea)',
			'LT' => 'Lithuania',
			'MY' => 'Malaysia',
			'MT' => 'Malta',
			'NL' => 'Netherlands',
			'NZ' => 'New Zealand',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'SG' => 'Singapore',
			'SI' => 'Slovenia',
			'ES' => 'Spain',
			'SE' => 'Sweden',
			'GB' => 'United Kingdom',
			'US' => 'USA'
		);
		
		return $countries;
	}
	
	public static function validateCombination($data, $combinations, $chargecode)
	{
		$delivery_signature = $data['delivery_signature_allowed'];
		$partial_delivery = $data['partial_delivery_allowed'];
		if($combinations) {
			if($combinations['delivery_signature_allowed'] != $delivery_signature || $combinations['partial_delivery_allowed'] != $partial_delivery) {
				$pda = ($combinations['partial_delivery_allowed']==1)?'Yes':'No';
				$dsa = ($combinations['delivery_signature_allowed']==1)?'Yes':'No';
				return array('error_msg' => 'You current chargecode <strong>'. $chargecode .'</strong> has invalid combination of data. Please make the <strong>Partial Delivery allowed?</strong> to <strong>'. $pda .'</strong> value and <strong>Delivery signature required?</strong> to <strong>'. $dsa .'</strong> value' );
			}
		}
		return true;
	}
}
?>