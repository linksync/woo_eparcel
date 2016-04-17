<?php
class LinksynceparcelAdminConsignmentsCreate
{
	public static function save()
	{
		$order_id = (int)($_POST['post_ID']);
		$shipping_country = $_POST['_shipping_country'];
		if($shipping_country != 'AU') {
			$_POST['number_of_articles'] = 1;
		}
		$number_of_articles = (int)trim($_POST['number_of_articles']);
		$shipping_country = $_POST['_shipping_country'];
		$data = $_POST;
		$order = new WC_Order( $order_id );
		$tempCanConsignments = (int)($number_of_articles/20);
		$canConsignments = $tempCanConsignments;
		$remainArticles = $number_of_articles % 20;

		// Validate consignments fields
		$validateFields = LinksynceparcelValidator::requiredConsignmentsField();
		if($validateFields != false && $shipping_country != 'AU') {
			$errors = implode('<br>', $validateFields);
			update_option('linksynceparcel_order_view_error',$errors);
		} else {
			if( $remainArticles > 0)
			{
				$canConsignments++;
			}
			
			for($i=0;$i<$canConsignments;$i++)
			{
				$data['start_index'] = ($i * 20 ) + 1;
				if( ($i+1) <= $tempCanConsignments)
				{
					$data['end_index'] = ($i * 20 ) + 20;
				}
				else
				{
					$data['end_index'] = ($i * 20 ) + $remainArticles;
				}
				
				try
				{
					$articleData = LinksynceparcelHelper::prepareArticleData($data, $order, '', $shipping_country);
					if(!empty($articleData) && isset($articleData['error_msg'])) {
						$error = $articleData['error_msg'];
						update_option('linksynceparcel_order_view_error',$error);
					} else {
						$content = $articleData['content'];
						$chargeCode = $articleData['charge_code'];
						$consignmentData = LinksynceparcelApi::createConsignment($content, 0, $chargeCode);
						$total_weight = $articleData['total_weight'];
						if($consignmentData)
						{
							$consignmentNumber = $consignmentData->consignmentNumber;
							$manifestNumber = $consignmentData->manifestNumber;
							LinksynceparcelHelper::insertConsignment($order_id,$consignmentNumber,$data,$manifestNumber,$chargeCode,$total_weight,$shipping_country);
							LinksynceparcelHelper::updateArticles($order_id,$consignmentNumber,$consignmentData->articles,$data,$content);
							LinksynceparcelHelper::insertManifest($manifestNumber);
							
							$labelContent = $consignmentData->lpsLabels->labels->label;
							LinksynceparcelHelper::generateDocument($consignmentNumber,$labelContent,'label');
							
							update_option('linksynceparcel_order_view_success','The consignment has been created successfully.');
							if(isset($data['totalOrderWeight']) && $data['totalOrderWeight'] == 1 && $shipping_country != 'AU') {
								update_option('linksynceparcel_order_view_error','You need to create another consignments because the weight of the order is over the maximum weight.');
							}
						}
						else
						{
							throw new Exception("createConsignment returned empty result");
						}
					}
				}
				catch(Exception $e)
				{
					$error = 'Cannot create consignment, Error: '.$e->getMessage();
					update_option('linksynceparcel_order_view_error',$error);
					LinksynceparcelHelper::log($error);
				}
			}
		}
	}
	
	public static function saveOrderWeight()
	{
		$order_id = (int)($_POST['post_ID']);
		$shipping_country = $_POST['_shipping_country'];
		if($shipping_country != 'AU') {
			$_POST['number_of_articles'] = 1;
		}
		$number_of_articles = (int)trim($_POST['number_of_articles']);
		$data = $_POST;
		$order = new WC_Order( $order_id );
		$tempCanConsignments = (int)($number_of_articles/20);
		$canConsignments = $tempCanConsignments;
		$remainArticles = $number_of_articles % 20;

		if( $remainArticles > 0)
		{
			$canConsignments++;
		}
		
		for($i=0;$i<$canConsignments;$i++)
		{
			$data['start_index'] = ($i * 20 ) + 1;
			if( ($i+1) <= $tempCanConsignments)
			{
				$data['end_index'] = ($i * 20 ) + 20;
			}
			else
			{
				$data['end_index'] = ($i * 20 ) + $remainArticles;
			}
			
			try
			{
				$articleData = LinksynceparcelHelper::prepareOrderWeightArticleData($data, $order, '', $shipping_country);
				$content = $articleData['content'];
				$chargeCode = $articleData['charge_code'];
				$total_weight = $articleData['total_weight'];
				$consignmentData = LinksynceparcelApi::createConsignment($content, 0, $chargeCode);

				if($consignmentData)
				{
					$consignmentNumber = $consignmentData->consignmentNumber;
					$manifestNumber = $consignmentData->manifestNumber;
					LinksynceparcelHelper::insertConsignment($order_id,$consignmentNumber,$data,$manifestNumber,$chargeCode,$total_weight,$shipping_country);
					LinksynceparcelHelper::updateArticles($order_id,$consignmentNumber,$consignmentData->articles,$data,$content);
					LinksynceparcelHelper::insertManifest($manifestNumber);
			
					$labelContent = $consignmentData->lpsLabels->labels->label;
					LinksynceparcelHelper::generateDocument($consignmentNumber,$labelContent,'label');
						
					update_option('linksynceparcel_order_view_success','The consignment has been created successfully.');
					if(isset($data['totalOrderWeight']) && $data['totalOrderWeight'] == 1 && $shipping_country != 'AU') {
						update_option('linksynceparcel_order_view_error','You need to create another consignments because the weight of the order is over the maximum weight.');
					}
				}
				else
				{
					throw new Exception("createConsignment returned empty result");
				}
			}
			catch(Exception $e)
			{
				$error = 'Cannot create consignment, Error: '.$e->getMessage();
				update_option('linksynceparcel_order_view_error',$error);
				LinksynceparcelHelper::log($error);
			}
		}
	}
	
	public static function saveDefaultWeight()
	{
		$order_id = (int)($_POST['post_ID']);
		$shipping_country = $_POST['_shipping_country'];
		if($shipping_country != 'AU') {
			$_POST['number_of_articles'] = 1;
		}
		$number_of_articles = (int)trim($_POST['number_of_articles']);
		$shipping_country = $_POST['_shipping_country'];
		$data = $_POST;
		$order = new WC_Order( $order_id );
		$tempCanConsignments = (int)($number_of_articles/20);
		$canConsignments = $tempCanConsignments;
		$remainArticles = $number_of_articles % 20;

		if( $remainArticles > 0)
		{
			$canConsignments++;
		}
		
		for($i=0;$i<$canConsignments;$i++)
		{
			$data['start_index'] = ($i * 20 ) + 1;
			if( ($i+1) <= $tempCanConsignments)
			{
				$data['end_index'] = ($i * 20 ) + 20;
			}
			else
			{
				$data['end_index'] = ($i * 20 ) + $remainArticles;
			}
			
			try
			{
				$articleData = LinksynceparcelHelper::prepareOrderWeightArticleData($data, $order, '', $shipping_country);
				$content = $articleData['content'];
				$chargeCode = $articleData['charge_code'];
				$total_weight = $articleData['total_weight'];
				$consignmentData = LinksynceparcelApi::createConsignment($content, 0, $chargeCode);

				if($consignmentData)
				{
					$consignmentNumber = $consignmentData->consignmentNumber;
					$manifestNumber = $consignmentData->manifestNumber;
					LinksynceparcelHelper::insertConsignment($order_id,$consignmentNumber,$data,$manifestNumber,$chargeCode,$total_weight,$shipping_country);
					LinksynceparcelHelper::updateArticles($order_id,$consignmentNumber,$consignmentData->articles,$data,$content);
					LinksynceparcelHelper::insertManifest($manifestNumber);
			
					$labelContent = $consignmentData->lpsLabels->labels->label;
					LinksynceparcelHelper::generateDocument($consignmentNumber,$labelContent,'label');
					
					update_option('linksynceparcel_order_view_success','The consignment has been created successfully.');
					if(isset($data['totalOrderWeight']) && $data['totalOrderWeight'] == 1 && $shipping_country != 'AU') {
						update_option('linksynceparcel_order_view_error','You need to create another consignments because the weight of the order is over the maximum weight.');
					}
				}
				else
				{
					throw new Exception("createConsignment returned empty result");
				}
			}
			catch(Exception $e)
			{
				$error = 'Cannot create consignment, Error: '.$e->getMessage();
				update_option('linksynceparcel_order_view_error',$error);
				LinksynceparcelHelper::log($error);
			}
		}
	}
}
?>