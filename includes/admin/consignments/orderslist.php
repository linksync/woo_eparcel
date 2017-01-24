<?php
class LinksynceparcelAdminConsignmentsOrdersList
{
	public static function output()
	{
		global $is_greater_than_21;
		include_once(linksynceparcel_DIR.'model/Consignment/OrdersList.php');
		include_once(linksynceparcel_DIR.'views/admin/consignments/orderslist.php');
	}
	
	public static function in_plugin_update_message()
	{
		$pluginMessage =  '';
		$currentTime = time();
		$lastCheckedTime = get_option('linksynceparcel_last_version_check_time');
		$version = get_option('linksynceparcel_version');
		$notsame = get_option('linksynceparcel_notsame');
		
		if(!$lastCheckedTime)
		{
			$lastCheckedTime = 0;
		}
		$elapsedTime = $currentTime - $lastCheckedTime;
		$elapsedDay = (int)($elapsedTime/86400);
		if($elapsedDay > 0)
		{
			$currentVersion = '0.3.3';
			$result = LinksynceparcelApi::getVersionNumber();
			if($result)
			{
				$latestVersion = isset($result->version_number) ? $result->version_number : '0.0.7';
				update_option('linksynceparcel_last_version_check_time',$currentTime);
				update_option('linksynceparcel_version',$latestVersion);
				update_option('linksynceparcel_notsame',0);
				if( intval(str_replace('.','',$currentVersion)) < intval(str_replace('.','',$latestVersion)) )
				{
					update_option('linksynceparcel_notsame',1);
					$pluginMessage = '<div class="updated" style="border-left: 4px solid rgb(255, 192, 58);">';
					$pluginMessage .= '<p>linksync eParcel '.$latestVersion.' is available! <a href="http://www.linksync.com/help/releases-eparcel-woocommerce" target="_blank">Please update now.</a></p>';
					$pluginMessage .= '</div>';
				}
			}
		}
		else if($notsame == 1)
		{
			$pluginMessage = '<div class="updated" style="border-left: 4px solid rgb(255, 192, 58);">';
			$pluginMessage .= '<p>linksync eParcel '.$version.' is available! <a href="http://www.linksync.com/help/releases-eparcel-woocommerce" target="_blank">Please update now.</a></p>';
			$pluginMessage .= '</div>';
		}
		
		return $pluginMessage;
	}
	
	public static function massCreateConsignment()
	{
		$ids = $_REQUEST['order'];
		if(is_array($ids))
		{
			require_once(linksynceparcel_DIR.'model/ArticlePreset/Model.php' );
			$articlePreset = new ArticlePreset();
			$presets = $articlePreset->get_by(array('status' => 1));
			$orders = $ids;
			$consignment = false;
			$countries = WC()->countries->countries;
			include_once(linksynceparcel_DIR.'views/admin/consignments/create_mass.php');
		}
		else
		{
			LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error','Please select item(s)');
			wp_redirect(admin_url('admin.php?page=linksynceparcel'));
		}
	}
	
	public static function massAssignConsignment()
	{
		$ids = $_REQUEST['order'];
		try 
		{
			if(is_array($ids))
			{
				$success = 0;
				$consignmentNumbers = array();
                foreach ($ids as $id) 
				{
					$values = explode('_',$id);
					$orderId = (int)($values[0]);
					$consignmentNumber = $values[1];
					$incrementId = $orderId;
					if($consignmentNumber == '0')
					{
						$error = sprintf('Order #%s: does not have consignment', $incrementId);
						LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
					}
					else
					{
						try 
						{
							$status = LinksynceparcelApi::assignConsignmentToManifest($consignmentNumber);
							$status = trim(strtolower($status));
							if($status == 'ok')
							{
								$success++;
								$consignmentNumbers[] = $consignmentNumber;
								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'is_next_manifest', 1);
								$successmsg = sprintf('Consignment #%s: successfully assigned', $consignmentNumber);
								LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$successmsg);
							}
							else
							{
								$error = sprintf('Consignment #%s: failed to assign', $consignmentNumber);
								LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
							}
						}
						catch (Exception $e) 
						{
							$error = sprintf('Consignment #%s, Error: %s', $consignmentNumber, $e->getMessage());
							$error = sprintf('Consignment #%s: failed to assign', $consignmentNumber);
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
						}
					}
					
                }
				
				if($orderId > 0 && $success > 0)
				{
					$manifestNumber = LinksynceparcelHelper::getManifestNumber();
					if($manifestNumber)
					{
						foreach($consignmentNumbers as $consignmentNumber)
						{
							LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'manifest_number', $manifestNumber);
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
		}
		wp_redirect(admin_url('admin.php?page=linksynceparcel'));
	}
	
	public static function massUnassignConsignment()
	{
		$ids = $_REQUEST['order'];
		try 
		{
			if(is_array($ids))
			{
				$success = 0;
                foreach ($ids as $id) 
				{
					$values = explode('_',$id);
					$orderId = (int)($values[0]);
					$consignmentNumber = $values[1];
					$incrementId = $orderId;
					if($consignmentNumber == '0')
					{
						$error = sprintf('Order #%s: does not have consignment', $incrementId);
						LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
					}
					else
					{
						try 
						{
							$status = LinksynceparcelApi::unAssignConsignment($consignmentNumber);
							$status = trim(strtolower($status));
							if($status == 'ok')
							{
								$success++;
								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'manifest_number', '');
								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'is_next_manifest', 0);
								$successmsg = sprintf('Consignment #%s: successfully unassigned', $consignmentNumber);
								LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$successmsg);
							}
							else
							{
								$error = sprintf('Consignment #%s: failed to unassign', $consignmentNumber);
								LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
							}
						}
						catch (Exception $e) 
						{
							$error = sprintf('Consignment #%s, Error: %s', $consignmentNumber, $e->getMessage());
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
						}
					}
                }
				
				if($orderId > 0 && $success > 0)
				{
					LinksynceparcelHelper::getManifestNumber();
					LinksynceparcelHelper::deleteManifest();
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
	
	public static function massDeleteConsignment()
	{
		$ids = $_REQUEST['order'];
		try 
		{
			if(is_array($ids))
			{
				$success = 0;
                foreach ($ids as $id) 
				{
					$values = explode('_',$id);
					$orderId = (int)($values[0]);
					$consignmentNumber = $values[1];
					$incrementId = $orderId;
					if($consignmentNumber == '0')
					{
						$error = sprintf('Order #%s: does not have consignment', $incrementId);
						LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
					}
					else
					{
						try 
						{
							$status = LinksynceparcelApi::deleteConsignment($consignmentNumber);
							$status = trim(strtolower($status));
							if($status == 'ok')
							{
								$filename = $consignmentNumber.'.pdf';
								$filepath = linksynceparcel_DIR.'assets/label/consignment/'.$filename;
								if(file_exists($filepath))
								{
									unlink($filepath);
								}
								$filepath_1 = linksynceparcel_UPLOAD_DIR.'consignment/'.$filename;
								if(file_exists($filepath_1))
								{
									unlink($filepath_1);
								}
								
								$filepath2 = linksynceparcel_DIR.'assets/label/returnlabels/'.$filename;
								if(file_exists($filepath2))
								{
									unlink($filepath2);
								}
								$filepath2_1 = linksynceparcel_UPLOAD_DIR.'returnlabels/'.$filename;
								if(file_exists($filepath2_1))
								{
									unlink($filepath2_1);
								}
								
								LinksynceparcelHelper::deleteConsignment($consignmentNumber);
								$success++;
								$successmsg = sprintf('Consignment #%s: successfully deleted', $consignmentNumber);
								LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$successmsg);
							}
							else
							{
								$error = sprintf('Consignment #%s: failed to delete', $consignmentNumber);
								LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
							}
						}
						catch (Exception $e) 
						{
							$error = sprintf('Consignment #%s, Error: %s', $consignmentNumber, $e->getMessage());
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
						}
					}
                }
				
				if($orderId > 0 && $success > 0)
				{
					LinksynceparcelHelper::getManifestNumber();
					LinksynceparcelHelper::deleteManifest();
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
	
	public static function massGenerateLabels()
	{
		$ids = $_REQUEST['order'];
		try 
		{
			if(is_array($ids))
			{
				$sameGroup = true;
				$isExpressCode = false;
				$isStandardCode = false;
				$isInternational = false;
				
				foreach ($ids as $id) 
				{
					$values = explode('_',$id);
					$orderId = (int)($values[0]);
					$consignmentNumber = $values[1];
					
					$chargeCode = LinksynceparcelHelper::getOrderChargeCode($orderId,$consignmentNumber);
					$allowedChargeCodes = LinksynceparcelHelper::getEParcelChargeCodes();
					$chargeCodeData = $allowedChargeCodes[$chargeCode];
					if($chargeCodeData['serviceType'] == 'express')
						$isExpressCode = true;
					if($chargeCodeData['serviceType'] == 'standard')
						$isStandardCode = true;
					if($chargeCodeData['serviceType'] == 'international')
						$isInternational = true;
				}
				
				$valid = true;
				if($isExpressCode && $isStandardCode) {
					$valid = false;
				}
				if($isExpressCode && $isInternational) {
					$valid = false;
				}
				if($isStandardCode && $isInternational) {
					$valid = false;
				}
				
				if ($valid) {
					$consignmentNumbers = array();
					$chargeCodes = array();
					foreach ($ids as $id) 
					{
						$values = explode('_',$id);
						$orderId = (int)($values[0]);
						$consignmentNumber = $values[1];
						$incrementId = $orderId;
						if($consignmentNumber != '0')
						{
							$consignmentNumbers[] = $consignmentNumber;
							
							$chargeCode = LinksynceparcelHelper::getOrderChargeCode($orderId,$consignmentNumber);
							$chargeCodes[] = $chargeCode;
							$labelContent = LinksynceparcelApi::getLabelsByConsignments($consignmentNumber, $chargeCode);
							if($labelContent)
							{
								$filename = $consignmentNumber.'.pdf';
								$filepath = linksynceparcel_UPLOAD_DIR.'consignment/'.$filename;
								$handle = fopen($filepath,'wb');
								fwrite($handle, $labelContent);
								fclose($handle);
	
								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'label', $filename);
								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'is_label_created', 1);
								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'is_label_printed', 1);
							}
						}
					}
					
					if(count($consignmentNumbers) > 0)
					{
						$labelContent = LinksynceparcelApi::getLabelsByConsignments(implode(',',$consignmentNumbers), $chargeCodes[0]);
						if($labelContent)
						{
							$filename = 'bulk-consignments-label.pdf';
							$filepath = linksynceparcel_UPLOAD_DIR.'consignment/'.$filename;
							$handle = fopen($filepath,'wb');
							fwrite($handle, $labelContent);
							fclose($handle);
							$labelLink = admin_url() .'?f_type=consignment&f_key=bulk-consignments-label';
							$success = sprintf('Label is generated. <a href="%s" target="_blank" style="color:blue; font-weight:bold; font-size:14px; text-decoration:underline">Please click here to view it.</a>',$labelLink.'&'.time());
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$success);
						}
						else
						{
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error','Failed to generate label');
						}
					}
					else
					{
						LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error','None of the selected items have consignments');
					}
				}
				else
				{
					$error = 'You can only print multiple consignment labels for the same Delivery Type - they must be all Express Post or all eParcel Standard.';
					LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
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
		}
		wp_redirect(admin_url('admin.php?page=linksynceparcel'));
	}
	
	public static function massGenerateDocs() {
		$ids = $_REQUEST['order'];
		try 
		{
			if(is_array($ids))
			{
				$international = true;
				
				foreach ($ids as $id) 
				{
					$values = explode('_',$id);
					$orderId = (int)($values[0]);
					$consignmentNumber = $values[1];
					
					$chargeCode = LinksynceparcelHelper::getOrderChargeCode($orderId,$consignmentNumber);
					$allowedChargeCodes = LinksynceparcelHelper::getEParcelChargeCodes();
					$chargeCodeData = $allowedChargeCodes[$chargeCode];
					if($chargeCodeData['serviceType'] != 'international')
						$international = false;
				}
				
				if($international) {
					$consignmentNumbers = array();
					$chargeCodes = array();
					foreach ($ids as $id) 
					{
						$values = explode('_',$id);
						$orderId = (int)($values[0]);
						$consignmentNumber = $values[1];
						$incrementId = $orderId;
						if($consignmentNumber != '0')
						{
							$consignmentNumbers[] = $consignmentNumber;
							
							$chargeCode = LinksynceparcelHelper::getOrderChargeCode($orderId,$consignmentNumber);
							$chargeCodes[] = $chargeCode;
							$labelContent = LinksynceparcelApi::getLabelsByConsignments($consignmentNumber, $chargeCode);
							if($labelContent)
							{
								$filename = $consignmentNumber.'.pdf';
								$filepath = linksynceparcel_UPLOAD_DIR.'consignment/'.$filename;
								$handle = fopen($filepath,'wb');
								fwrite($handle, $labelContent);
								fclose($handle);

								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'label', $filename);
								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'is_label_created', 1);
								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'is_customdocs_printed', 1);
							}
						}
					}
					
					if(count($consignmentNumbers) > 0)
					{
						$labelContent = LinksynceparcelApi::getLabelsByConsignments(implode(',',$consignmentNumbers), $chargeCodes[0]);
						if($labelContent)
						{
							$filename = 'bulk-consignments-customdocs.pdf';
							$filepath = linksynceparcel_UPLOAD_DIR.'consignment/'.$filename;
							$handle = fopen($filepath,'wb');
							fwrite($handle, $labelContent);
							fclose($handle);
							$labelLink = linksynceparcel_UPLOAD_BASEURL.'consignment/';
							$success = sprintf('Custom Docs is generated. <a href="%s" target="_blank" style="color:blue; font-weight:bold; font-size:14px; text-decoration:underline">Please click here to view it.</a>',$labelLink.$filename.'?'.time());
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$success);
						}
						else
						{
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error','Failed to generate docs.');
						}
					} else {
						LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error','None of the selected items have consignments');
					}
				} else {
					$error = 'You can only generate multiple consignment custom docs for internation Service.';
					LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
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
		}
		wp_redirect(admin_url('admin.php?page=linksynceparcel'));
	}
	
	public static function massGenerateReturnLabels()
	{
		$ids = $_REQUEST['order'];
		try 
		{
			if(is_array($ids))
			{
				$sameGroup = true;
				$isExpressCode = false;
				$isStandardCode = false;
				
				foreach ($ids as $id) 
				{
					$values = explode('_',$id);
					$orderId = (int)($values[0]);
					$consignmentNumber = $values[1];
					
					$chargeCode = LinksynceparcelHelper::getOrderChargeCode($orderId,$consignmentNumber);
					if(!$isExpressCode && LinksynceparcelHelper::isExpressPostCode($chargeCode))
						$isExpressCode = true;
					if(!$isStandardCode && LinksynceparcelHelper::isLinksynceparcelStandardCode($chargeCode))
						$isStandardCode = true;
				}
				
				if (!($isExpressCode && $isStandardCode))
				{
					$consignmentNumbers = array();
					foreach ($ids as $id) 
					{
						$values = explode('_',$id);
						$orderId = (int)($values[0]);
						$consignmentNumber = $values[1];
						$incrementId = $orderId;
						if($consignmentNumber != '0')
						{
							$consignmentNumbers[] = $consignmentNumber;
							
							$labelContent = LinksynceparcelApi::getReturnLabelsByConsignments($consignmentNumber);
							if($labelContent)
							{
								$filename = $consignmentNumber.'.pdf';
								$filepath = linksynceparcel_UPLOAD_DIR.'returnlabels/'.$filename;
								$handle = fopen($filepath,'wb');
								fwrite($handle, $labelContent);
								fclose($handle);
								LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'is_return_label_printed', 1);
							}
						}
					}
					
					if(count($consignmentNumbers) > 0)
					{
						$labelContent = LinksynceparcelApi::getReturnLabelsByConsignments(implode(',',$consignmentNumbers));
						if($labelContent)
						{
							$filename = 'bulk-consignments-return-label.pdf';
							$filepath = linksynceparcel_UPLOAD_DIR.'returnlabels/'.$filename;
							$handle = fopen($filepath,'wb');
							fwrite($handle, $labelContent);
							fclose($handle);
							$labelLink = linksynceparcel_UPLOAD_BASEURL.'returnlabels/';
							$success = sprintf('Return Label is generated. <a href="%s" target="_blank" style="color:blue; font-weight:bold; font-size:14px; text-decoration:underline">Please click here to view it.</a>',$labelLink.$filename.'?'.time());
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$success);
						}
						else
						{
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error','Failed to generate label');
						}
					}
					else
					{
						LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error','None of the selected items have consignments');
					}
				}
				else
				{
					$error = 'You can only print multiple consignment labels for the same Delivery Type - they must be all Express Post or all eParcel Standard.';
					LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
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
		}
		wp_redirect(admin_url('admin.php?page=linksynceparcel'));
	}
	
	public static function massMarkDespatched()
	{
		$ids = $_REQUEST['order'];
		try 
		{
			if(is_array($ids))
			{
				$statuses = (array) get_terms('shop_order_status', array('hide_empty' => 0, 'orderby' => 'id'));
				$success = 0;
                foreach ($ids as $id) 
				{
					$values = explode('_',$id);
					$orderId = (int)($values[0]);
					$consignmentNumber = $values[1];
					$incrementId = $orderId;
					if($consignmentNumber == '0')
					{
						$error = sprintf('Order #%s: does not have consignment', $incrementId);
						LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
					}
					else
					{
						try 
						{
							LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'despatched', 1);
							$changeState = get_option('linksynceparcel_change_order_status');
							if(!empty($changeState))
							{
								$order = new WC_Order($order_id);

								$current_status = '';
								foreach($statuses as $status)
								{
									if($status->slug == $order->status)
									{
										$current_status = $status->term_id;
									}
								}
									
								if ($changeState && ($changeState !== $current_status))
								{
									foreach($statuses as $status)
									{
										if($status->term_id == $changeState)
										{
											$order->update_status($status->slug);
										}
									}
								}
							}
							$successmsg = sprintf('Consignment #%s: successfully marked as despatched', $consignmentNumber);
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$successmsg);
						}
						catch (Exception $e) 
						{
							$error = sprintf('Consignment #%s, Error: %s', $consignmentNumber, $e->getMessage());
							LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$error);
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
		}
		wp_redirect(admin_url('admin.php?page=linksynceparcel'));
	}
	
	public static function despatchManifestData()
	{
		if(!session_id()) {
			session_start();
		}
		
		global $is_greater_than_21;
		try 
		{
			$arr_content = json_encode(array('percentage' => 1, 'message' => 'processed'));
			LinksynceparcelHelper::session_logs(session_id(), $arr_content);
			
			$arr_content = '';
			$isManifest = false;
			$manifestNumber = false;
			$manifests = LinksynceparcelApi::getManifest();
			$xml = simplexml_load_string($manifests);
			$currentManifest = '';
			
			$arr_content = json_encode(array('percentage' => 5, 'message' => 'processed'));
			LinksynceparcelHelper::session_logs(session_id(), $arr_content);
			
			if($xml) {
				$manifest = $xml->manifest;
				foreach($xml->manifest as $manifest)
				{
					$manifestNumber = $manifest->manifestNumber;
					if(empty($currentManifest))
					{
						$currentManifest = $manifestNumber;
					}
					$numberOfArticles = (int)$manifest->numberOfArticles;
					$numberOfConsignments = (int)$manifest->numberOfConsignments;
					if($numberOfConsignments > 0)
					{
						LinksynceparcelHelper::updateManifest($manifestNumber,$numberOfArticles,$numberOfConsignments);
						$isManifest = true;
					}
				}
				
				$arr_content = json_encode(array('percentage' => 15, 'message' => 'processed'));
				LinksynceparcelHelper::session_logs(session_id(), $arr_content);
			}
			
			if(!$isManifest)
			{
				$arr_content = json_encode(array('percentage' => null, 'message' => 'cancelled'));
				LinksynceparcelHelper::session_logs(session_id(), $arr_content);
				
				return array("error" => 1, "msg" => "No consignments are available in the current manifest");
			}
			
			$arr_content = json_encode(array('percentage' => 24, 'message' => 'processed'));
			LinksynceparcelHelper::session_logs(session_id(), $arr_content);
			
			$notDespatchedConsignmentNumbers = LinksynceparcelHelper::getNotDespatchedAssignedConsignmentNumbers();
			if(count($notDespatchedConsignmentNumbers) == 0)
			{
				$arr_content = json_encode(array('percentage' => null, 'message' => 'cancelled'));
				LinksynceparcelHelper::session_logs(session_id(), $arr_content);
				
				$error = 'No consignments are available in the current manifest';
				return array("error" => 1, "msg" => $error);
			} else {
				try {
					$arr_content = json_encode(array('percentage' => 45, 'message' => 'processed'));
					LinksynceparcelHelper::session_logs(session_id(), $arr_content);
					
					$statuses = LinksynceparcelHelper::getListOrderStatuses();
					
					$despatch = true;
					$notdespatched_msg = '';
					foreach ($notDespatchedConsignmentNumbers as $consignmentNumber) 
					{
						$consignmentNumber = trim($consignmentNumber);
						$consignment = LinksynceparcelHelper::getConsignment($consignmentNumber);
						if(!$consignment)
						{
							LinksynceparcelApi::deleteConsignment($consignmentNumber);
							$despatch = false;
							$notdespatched_msg .= 'Consignment #'. $consignmentNumber .': not in the current DB. Please try again.<br>';
						}
						else if(!$consignment->is_label_printed)
						{
							$despatch = false;
							$notdespatched_msg .= 'Consignment #'. $consignmentNumber .': you have not printed labels for this consignment.<br>';
						}
						else if($consignment->print_return_labels && !$consignment->is_return_label_printed)
						{
							$despatch = false;
							$notdespatched_msg .= 'Consignment #'. $consignmentNumber .': you have not printed return labels for this consignment.<br>';
						}
					}
					$arr_content = json_encode(array('percentage' => 63, 'message' => 'processed'));
					LinksynceparcelHelper::session_logs(session_id(), $arr_content);
					
					if($despatch) {
						try {
							$status = LinksynceparcelApi::despatchManifest();
							$status = trim(strtolower($status));
							if($status == 'ok')
							{
								$arr_content = json_encode(array('percentage' => 74, 'message' => 'processed'));
								LinksynceparcelHelper::session_logs(session_id(), $arr_content);
					
								$timestamp = time();
								$date = date('Y-m-d H:i:s', $timestamp);
								LinksynceparcelHelper::updateManifestTable($currentManifest,'despatch_date',$date);
								LinksynceparcelHelper::updateConsignmentTableByManifest($currentManifest,'despatched',1);
								LinksynceparcelHelper::updateConsignmentTableByManifest($currentManifest,'is_next_manifest',0);
					
								$changeState = get_option('linksynceparcel_change_order_status');
								if(!empty($changeState))
								{
									$arr_content = json_encode(array('percentage' => 85, 'message' => 'processed'));
									LinksynceparcelHelper::session_logs(session_id(), $arr_content);
									
									$ordersList = LinksynceparcelHelper::getOrdersByManifest($currentManifest);
									if($ordersList)
									{
										$orderids = array();
										foreach($ordersList as $orderObj)
										{
											$orderids[] = $orderObj->order_id;
										}
										$manifestdata = json_encode(array(
											'manifestnumber' => $currentManifest,
											'orders' => $orderids
										));
										LinksynceparcelHelper::session_maifest(session_id(), $manifestdata);
									}
								}
								
								$arr_content = json_encode(array('percentage' => 91, 'message' => 'processed'));
								LinksynceparcelHelper::session_logs(session_id(), $arr_content);
								
								$multiple_msg = array();
								
								$success = 'Despatching manifest is successful';
								$multiple_msg[] = array(
									'error' => 0,
									'msg' => $success
								);
									
								$labelContent = LinksynceparcelApi::printManifest($currentManifest);
							
								if($labelContent)
								{
									$arr_content = json_encode(array('percentage' => 95, 'message' => 'processed'));
									LinksynceparcelHelper::session_logs(session_id(), $arr_content);
					
									$filename = $currentManifest.'.pdf';
									$filepath = linksynceparcel_UPLOAD_DIR.'manifest/'.$filename;
									$handle = fopen($filepath,'wb');
									fwrite($handle, $labelContent);
									fclose($handle);
					
									LinksynceparcelHelper::updateManifestTable($currentManifest,'label',$filename);
									
									$labelLink = admin_url() .'?f_key='. $currentManifest .'&f_type=manifest';
									$success = 'Your Manifest Summary has been generated. <a href="'. $labelLink .'" target="_blank" style="color:blue; font-weight:bold; font-size:14px; text-decoration:underline">Please click here to view it.</a>';
									
									$multiple_msg[] = array(
										'error' => 0,
										'msg' => $success
									);
								}
								else
								{
									$error = 'Manifest label content is empty';
									$multiple_msg[] = array(
										'error' => 1,
										'msg' => $error
									);
								}
								$arr_content = json_encode(array('percentage' => 97, 'message' => 'processed'));
								LinksynceparcelHelper::session_logs(session_id(), $arr_content);
					
								LinksynceparcelHelper::updateManifestTable($currentManifest,'despatch_complete',1);
					
								$arr_content = json_encode(array('percentage' => 100, 'message' => 'completed'));
								LinksynceparcelHelper::session_logs(session_id(), $arr_content);
								return array("error" => 2, "msg" => $multiple_msg);
							}
							else
							{
								$arr_content = json_encode(array('percentage' => null, 'message' => 'cancelled'));
								LinksynceparcelHelper::session_logs(session_id(), $arr_content);
								
								$error = 'Despatching manifest is failed';
								return array("error" => 1, "msg" => $error);
							}
						}
						catch (Exception $e) 
						{
							$arr_content = json_encode(array('percentage' => null, 'message' => 'cancelled'));
							LinksynceparcelHelper::session_logs(session_id(), $arr_content);
							
							return array("error" => 1, "msg" => $e->getMessage());
						}
					} else {
						$arr_content = json_encode(array('percentage' => null, 'message' => 'cancelled'));
						LinksynceparcelHelper::session_logs(session_id(), $arr_content);
				
						$error = substr($notdespatched_msg, 0, -4);
						return array("error" => 1, "msg" => $error);
					}
				}
				catch (Exception $e) 
				{
						
					$arr_content = json_encode(array('percentage' => null, 'message' => 'cancelled'));
					LinksynceparcelHelper::session_logs(session_id(), $arr_content);
					
					return array("error" => 1, "msg" => $e->getMessage());
				}
			}
		}
		catch (Exception $e) 
		{
			$arr_content = json_encode(array('percentage' => null, 'message' => 'cancelled'));
			LinksynceparcelHelper::session_logs(session_id(), $arr_content);
			
			return array("error" => 1, "msg" => $e->getMessage());
		}
	}
}
?>