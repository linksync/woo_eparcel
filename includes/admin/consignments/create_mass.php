<?php
class LinksynceparcelAdminConsignmentsCreateMass
{
	public static function save()
	{
		$ids = $_REQUEST['order'];
		$data = $_REQUEST;
		try 
		{
			if(is_array($ids))
			{
				$success = 0;
				foreach ($ids as $id) 
				{
					$values = explode('_',$id);
					$orderId = (int)($values[0]);
					$order = new WC_Order($orderId);
					$shipping_country = get_post_meta($orderId,'_shipping_country',true);
					$consignmentNumber = $values[1];
					$incrementId = $orderId;

					// Validate consignments fields
					$validateFields = LinksynceparcelValidator::requiredConsignmentsField();
					if($validateFields != false && $shipping_country != 'AU') {
						$errors = implode('<br>', $validateFields);
						update_option('linksynceparcel_consignment_error',$errors);
					} else {
						if($shipping_country == 'AU' && !LinksynceparcelHelper::getAddressValid($orderId))
						{
							$error = sprintf('Order #%s: Please validate the address before creating consignment', $incrementId);
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
						}
						else
						{
							try 
							{
								if($data['partial_delivery_allowed'])
								{
									if(LinksynceparcelHelper::isDisablePartialDeliveryMethod($orderId))
									{
										$data['partial_delivery_allowed'] = 0;
									}
								}
								$articleData = LinksynceparcelHelper::prepareArticleDataBulk($data, $order, $shipping_country);
								if(!empty($articleData) && isset($articleData['error_msg'])) {
									$error = $articleData['error_msg'];
									update_option('linksynceparcel_consignment_error',$error);
								} else {
									$content = $articleData['content'];
									$chargeCode = $articleData['charge_code'];
									$total_weight = $articleData['total_weight'];
									$consignmentData = LinksynceparcelApi::createConsignment($content, 0, $chargeCode);
									if($consignmentData)
									{
										$consignmentNumber = $consignmentData->consignmentNumber;
										$manifestNumber = $consignmentData->manifestNumber;
									
										LinksynceparcelHelper::insertConsignment($orderId,$consignmentNumber,$data,$manifestNumber,$chargeCode,$total_weight,$shipping_country );
										LinksynceparcelHelper::updateArticles($orderId,$consignmentNumber,$consignmentData->articles,$data,$content);
										LinksynceparcelHelper::insertManifest($manifestNumber);
								
										if($shipping_country == 'AU') {
											$labelContent = $consignmentData->lpsLabels->labels->label;
											LinksynceparcelHelper::generateDocument($consignmentNumber,$labelContent,'label');
										} else {
											$labelContent = $consignmentData->lpsLabels->labels->label;
											$docsContent = $consignmentData->lpsLabels->labels->customDocs;
											LinksynceparcelHelper::generateDocument($consignmentNumber,$labelContent,'label');
											LinksynceparcelHelper::generateDocument($consignmentNumber,$docsContent,'customdocs');
										}
										
										$successmsg = sprintf('Order #%s: Consignment #%s created successfully', $incrementId,$consignmentNumber);
										LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$successmsg);
									}
									else
									{
										$error = sprintf('Order #%s: Failed to create consignment',$incrementId);
										LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
									}
								}
							}
							catch (Exception $e) 
							{
								$error = sprintf('Consignment #%s, Error: %s', $consignmentNumber, $e->getMessage());
								LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
							}
						}
					}
				}
			}
			else
			{
				throw new Exception("Please select items");
			}
		} 
		catch (Exception $e) 
		{
			LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$e->getMessage());
			LinksynceparcelHelper::deleteManifest();
		}
		wp_redirect(admin_url('admin.php?page=linksynceparcel'));
	}
}
?>