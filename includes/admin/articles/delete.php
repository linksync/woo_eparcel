<?php
class LinksynceparcelAdminArticlesDelete
{
	public static function save()
	{
		$order_id = (int)($_GET['order_id']);
		$consignmentNumber = trim($_GET['consignment_number']);
		$articleNumber = trim($_GET['article_number']);
		$data = $_REQUEST;
		try
		{
			$order = new WC_Order( $order_id );
			$articles = LinksynceparcelHelper::getArticles($order_id, $consignmentNumber);
			if($articles && count($articles) > 1 )
			{
				$deleteArticle = LinksynceparcelHelper::deleteArticle($order_id,$consignmentNumber,$articleNumber);
				$articleData = LinksynceparcelHelper::prepareModifiedArticleData($order, $consignmentNumber);
				if(!empty($articleData) && isset($articleData['error_msg'])) {
                    $error = $articleData['error_msg'];
                    update_option('linksynceparcel_order_view_error',$error);
                } else {
					$content = $articleData['content'];
					$chargeCode = $articleData['charge_code'];
					$total_weight = $articleData['total_weight'];
					$consignmentData = LinksynceparcelApi::modifyConsignment($content,$consignmentNumber,$chargeCode);
					if($consignmentData)
					{
						LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'weight', $total_weight);
						$consignmentNumber = $consignmentData->consignmentNumber;
						$manifestNumber = $consignmentData->manifestNumber;
						LinksynceparcelHelper::updateArticles($order_id,$consignmentNumber,$consignmentData->articles,$data,$content);
						LinksynceparcelHelper::insertManifest($manifestNumber);
						
						$labelContent = $consignmentData->lpsLabels->labels->label;
						LinksynceparcelHelper::generateDocument($consignmentNumber,$labelContent,'label');
					
						update_option('linksynceparcel_order_view_success','Article #'.$articleNumber.' has been deleted from consignment #'.$consignmentNumber.' successfully.');
						wp_redirect(admin_url('post.php?post='.$order_id.'&action=edit'));
					}
				}
			}
			else
			{
				self::deleteConsignmentArticleAction();
			}
		}
		catch(Exception $e)
		{
			$error = 'Could not delete article, Error: '.$e->getMessage();
			update_option('linksynceparcel_order_view_error',$error);
			LinksynceparcelHelper::log($error);
            wp_redirect(admin_url('post.php?post='.$order_id.'&action=edit'));
		}
	}
	
	public static function deleteConsignmentArticleAction()
    {
		$order_id = (int)($_GET['order_id']);
		$consignmentNumber = trim($_GET['consignment_number']);
		$consignment = LinksynceparcelHelper::getConsignment($consignmentNumber);
		try
		{
			$ok = LinksynceparcelApi::deleteConsignment($consignmentNumber);
			if($ok)
			{
				$filename = $consignmentNumber.'.pdf';
				$filepath = linksynceparcel_DIR.'assets/label/consignment/'.$filename;
				if(file_exists($filepath))
				{
					unlink($filepath);
				}
				$filepath_1 = linksynceparcel_UPLOAD_DIR.'consignment/'.$filename;
				if(file_exists($filepath))
				{
					unlink($filepath);
				}
				
				$filepath2 = linksynceparcel_DIR.'assets/label/returnlabels/'.$filename;
				if(file_exists($filepath2))
				{
					unlink($filepath2);
				}
				$filepath2_1 = linksynceparcel_UPLOAD_DIR.'returnlabels/'.$filename;
				if(file_exists($filepath2))
				{
					unlink($filepath2);
				}
				
				LinksynceparcelHelper::deleteConsignment($consignmentNumber);
				LinksynceparcelHelper::deleteManifest2($consignment->manifest_number);
				update_option('linksynceparcel_order_view_success','Article has been deleted from consignment #'.$consignmentNumber.' successfully.<br/>Consignment #'.$consignmentNumber.' has been deleted successfully.');
				wp_redirect(admin_url('post.php?post='.$order_id.'&action=edit'));
			}
		}
		catch(Exception $e)
		{
			$error = 'Could not delete consignment. Error: '.$e->getMessage();
			update_option('linksynceparcel_order_view_error',$error);
			LinksynceparcelHelper::log($error);
			LinksynceparcelHelper::deleteManifest2($consignment->manifest_number);
            wp_redirect(admin_url('post.php?post='.$order_id.'&action=edit'));
		}
	}
}
?>