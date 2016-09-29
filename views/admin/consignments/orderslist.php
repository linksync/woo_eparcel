<?php global $my_plugin_hook,$linksynceparcel_consignment_menu; ?>
<link href="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/themes/ui-darkness/jquery-ui.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="<?php echo linksynceparcel_URL?>assets/css/linksynctooltip.css" />
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
<script type="text/javascript" src="<?php echo linksynceparcel_URL?>assets/js/linksynctooltip.js"></script>
<div class="wrap woocommerce">
	<div id="loading" style="display:none;">
		<div id="img-loader">
			<img src="<?php echo linksynceparcel_URL?>assets/images/load.gif" alt="Loading" />
		</div>
	</div>
	<?php
	$error = get_option('linksynceparcel_consignment_error');
	if($error)
	{
		LinksynceparcelHelper::addError($error);
		delete_option('linksynceparcel_consignment_error');
	}
	$success = get_option('linksynceparcel_consignment_success');
	if($success)
	{
		LinksynceparcelHelper::addSuccess($success);
		delete_option('linksynceparcel_consignment_success');
	}
	$config_checker = LinksynceparcelHelper::checkAssignConfigurationSettings();
	?>
<form method="get" id="mainform" action="<?php echo admin_url('admin.php'); ?>" enctype="multipart/form-data" onsubmit="return submitConsignmentForm()">
    	<input type="hidden" name="page" value="linksynceparcel" />
        <?php 
        if ( ! empty( $_REQUEST['orderby'] ) )
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		else
			echo '<input type="hidden" name="orderby" value="order_id" />';
		if ( ! empty( $_REQUEST['order'] ) )
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		else
			echo '<input type="hidden" name="order" value="desc" />';
		?>
        <div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"><br /></div>
        <h2>
        	Consignments
        </h2>
        <?php
		if($config_checker) {
		?>
		<div class="error">
			<p>Account settings for linksync eParcel configuration are not complete. <a href="<?php echo admin_url('admin.php?page=linksynceparcel&subpage=configuration'); ?>">Click here for linksync eParcel configuration settings.</a></p>
		</div>
		<?php
		} else {
			$linksynceparcel_Version = LinksynceparcelHelper::checkLinksynceParcelVersion();
			if($linksynceparcel_Version > '1.1.2') {
				if( LinksynceparcelHelper::checkNewChargeCodeConfig() ) {
					$checker = $myListTable->checkNonLinksync();
					if (isset($checker) && $checker > 0)
					{ 
						$myListTable->prepare_items(); 
						$myListTable->display();
					} else { ?>
						<div class="update-nag notice">
							<p>Linksync eParcel requires at least one shipping type. <a href="<?php echo admin_url('admin.php?page=linksynceparcel&subpage=assign-shipping-types&action=add')?>">Click here</a> to assing shipping types.</p>
						</div>
					<?php }
				} else {
				?>
					<div class="update-nag notice">
						<p>Linksync eParcel requires to update your charge code configuration.  After you configure your charge code settings please update also your shipping types for eParcel.</p>
						<br/>
						<p><a href="https://help.linksync.com/hc/en-us/articles/206771050" target="_blank">Follow this link that contains a step by step instruction on how to configure your new charge code settings.</a></p>
					</div>
				<?php
				}
			} else {
				$checker = $myListTable->checkNonLinksync();
				if (isset($checker) && $checker > 0)
				{ 
					$myListTable->prepare_items(); 
					$myListTable->display();
				} else { ?>
					<div class="update-nag notice">
						<p>Linksync eParcel requires at least one shipping type. <a href="<?php echo admin_url('admin.php?page=linksynceparcel&subpage=assign-shipping-types&action=add')?>">Click here</a> to assing shipping types.</p>
					</div>
				<?php }
			}
					
		}
		?>
    </form>
</div>
<style>
th#order_item {
    width: 115px;
}
table.order_items td {
	border: 0;
    margin: 0;
    padding: 0 0 3px
}
.bg-orange {
	background-color: #f66a1e;
	color: #fff !important;
    text-align: center;
    border-radius: 10px;
    font-weight: 600;
}
.bg-yellow {
	background-color: #ffa10c;
	color: #fff !important;
    text-align: center;
    border-radius: 10px;
    font-weight: 600;
}
.bg-blue {
	background-color: #4487f5;
    color: #fff !important;
    text-align: center;
    border-radius: 10px;
    font-weight: 600;
}
span.notes_head.tips.tooltip.linksynctooltiped {
    margin: 5px auto;
    text-align: center;
}
td.customers_note.column-customers_note {
    text-align: center;
}
div#loading {
    width: 100%;
    text-align: center;
    background-color: #ddd;
}
div#img-loader {
    position: absolute;
    margin: auto;
    left: 0;
    right: 0;
    display: block;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.8);
    z-index: 1;
}
div#img-loader img {
    margin-top: 180px;
    width: 250px;
}
small.meta.email {
	width: 100%;
}
</style>
<script>
jQuery(document).ready(function(){
	jQuery('.tooltip').linksynctooltip({
		theme: 'linksynctooltip-shadow',
		contentAsHTML: true
	});
	jQuery('#the-list').delegate('.handler', 'click', function() {
		var key = jQuery(this).data('key');
		if(jQuery(this).hasClass('inactive') || !jQuery(this).hasClass('outactive')) {
			jQuery(this).removeClass('inactive');
			jQuery(this).addClass('outactive');
			jQuery('#key-'+ key).slideUp();
		} else {
			jQuery(this).addClass('inactive');
			jQuery(this).removeClass('outactive');
			jQuery('#key-'+ key).slideDown();
		}
	});
	jQuery("#dialog").dialog({
		autoOpen: false,
		width:'400px'
	});
	
	jQuery("#dialog2").dialog({
		autoOpen: false,
		width:'400px'
	});
	
	jQuery('input#_wpnonce').next().remove();
	jQuery('.datepicker').datepicker({
		dateFormat: "yy-mm-dd"
	});
	
	jQuery("#dialog_submit").click(function(e) {
		if(!jQuery("#dialog_checkbox").prop('checked'))
		{
			alert('Please acknowledge to submit test manifest');
			e.preventDefault();
		}
		else
		{
			jQuery('#loading').show();
			var mode = '<?php echo trim(get_option('linksynceparcel_operation_mode'))?>';		
			if(mode == 1)
			{
				jQuery("#dialog2").dialog("close");
			}
			else
			{
				jQuery("#dialog").dialog("close");
			}
			
			jQuery.get('<?php echo admin_url('admin.php?page=linksynceparcel&ajax=1&subpage=despatched-Manifest')?>', function(res) {
				if(res == 2) {
					window.location.reload(true);
				}
			});
			
			// Check with in 10mins if server not response
			setTimeout(checkDespatchProcess, 600000);
			e.preventDefault();
		}
	});
	
	jQuery('#dialog_submit2').click(function(e) {
		jQuery('#loading').show();
		var mode = '<?php echo trim(get_option('linksynceparcel_operation_mode'))?>';		
		if(mode == 1)
		{
			jQuery("#dialog2").dialog("close");
		}
		else
		{
			jQuery("#dialog").dialog("close");
		}
		
		jQuery.get('<?php echo admin_url('admin.php?page=linksynceparcel&ajax=1&subpage=despatched-Manifest')?>', function(res) {
			if(res == 2) {
				window.location.reload(true);
			}
		});
		
		// Check with in 10mins if server not response
		setTimeout(checkDespatchProcess, 600000);
		e.preventDefault();
	});
});

function checkDespatchProcess()
{
	jQuery.get('<?php echo plugin_dir_url('linksync-eparcel')?>/linksync-eparcel/includes/ajax/ajax.progress.php?sesid=<?php echo session_id(); ?>', function(res) {
		jQuery('#loading').hide();
		if(res.percentage != 100) {
			alert('Sorry, we encounter request problem from the server. Please try it again.');
			window.location.reload(true);
		} else {
			alert('Despatch successfully!');
			window.location.reload(true);
		}
	});
}

function setLocationConfirmDialog()
{
	if(!jQuery('#despatchManifest').hasClass('disabled'))
	{
		var mode = '<?php echo trim(get_option('linksynceparcel_operation_mode'))?>';		
		if(mode == 1)
		{
			jQuery("#dialog2").dialog("open");
		}
		else
		{
			jQuery("#dialog").dialog("open");
		}
	}
}

function setLocation(url)
{
	window.location.href = url;
}

function submitConsignmentForm()
{
	var action = jQuery('select[name="action"]').val();
	if(action == -1)
	{
		action = jQuery('select[name="action2"]').val();
	}
	if(action == 'massUnassignConsignment' || action == 'massDeleteConsignment' || action == 'massMarkDespatched')
	{
		return confirm('Are you sure?');
	}
	return true;
}

$jEparcel = jQuery.noConflict();
$jEparcel(document).ready(function(){
	jQuery('#doaction').click(function(e) {
		if(jQuery('#bulk-action-selector-top').val() == 'massCreateConsignment') {
			var c = checkAllOrders();
			if(c.length > 0) {
				var conf = confirm('Consignments already exist for order '+ c.join(', ') +'. Do you want to continue with creating consignments?');
				if(conf != true) {
					window.location.href = "<?php echo admin_url('admin.php?page=linksynceparcel'); ?>";
					return false;
				}
			}
		}
	});
});
function checkAllOrders()
{
	var allvalues = [];
	jQuery('.massaction-checkbox:checked').each(function() {
		var v = jQuery(this).val();
		var r = v.split("_");
		if(r[1] != 0) {
			allvalues.push(r[0]);
		}
	});
	return allvalues;
}
</script>
<style>
input.new-input{
	font-size:11px;
	width:90px;
}
#post-query-submit{
	float: right;
}
.column-number_of_articles,.column-is_return_label_printed,.column-is_address_valid,.column-is_next_manifest,.column-is_label_printed{
	text-align:center;
}
th.manage-column {
	vertical-align:top;
}
</style>

<div id="dialog" title="Submit Test Manifest" style="display:none">
<form method="post">
<p>You are in test mode. Test mode enables you to use and test all features of the linksync eParcel without actually submitting a manifest to Australia Post on despatch of a manifest.</p>
<label> <input id="dialog_checkbox" name="dialog_checkbox" type="checkbox"> I acknowledge this is only a test. </label>
<br /><br /><br/>
<input id="dialog_submit" type="submit" value="Submit" style="float:right" class="button">
</form>
</div>

<div id="dialog2" title="Submit Manifest" style="display:none">
<form method="post">
<p>You are about to submit your manifest to Australia Post. Once your manifest is despatched, you won't be able to modify it, or the associated consignments..</p>
<br /><br/>
<input id="dialog_submit2" type="submit" value="Submit" style="float:right" class="button">
</form>
</div>
