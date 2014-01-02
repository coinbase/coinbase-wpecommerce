<?php

$nzshpcrt_gateways[$num]['name'] = 'Coinbase';
$nzshpcrt_gateways[$num]['internalname'] = 'coinbase_wpe';
$nzshpcrt_gateways[$num]['function'] = 'gateway_coinbase_wpe';
$nzshpcrt_gateways[$num]['form'] = 'form_coinbase_wpe';
$nzshpcrt_gateways[$num]['submit_function'] = "submit_coinbase_wpe";
$nzshpcrt_gateways[$num]['display_name'] = "Bitcoin";

// Called on gateway execution (payment logic)
function gateway_coinbase_wpe($separator, $sessionid) {

	require_once(dirname(__FILE__) . "/coinbase-php/Coinbase.php");
	
	$clientId = get_option("coinbase_wpe_clientid");
	$clientSecret = get_option("coinbase_wpe_clientsecret");
	$redirectUrl = get_option("coinbase_wpe_oauthredirect");
	$oauth = new Coinbase_OAuth($clientId, $clientSecret, $redirectUrl);
	
	$tokens = unserialize(get_option("coinbase_wpe_tokens"));
	$coinbase = new Coinbase($oauth, $tokens);
	
	$callbackSecret = get_option("coinbase_wpe_callbacksecret");
	if($callbackSecret == false) {
		$callbackSecret = sha1(mt_rand());
		update_option("coinbase_wpe_callbacksecret", $callbackSecret);
	}
	
        global $wpdb, $wpsc_cart;
        
        $currencyId = get_option('currency_type');
        $currency = $wpdb->get_var($wpdb->prepare("SELECT `code` FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `id` = %d LIMIT 1", $currencyId));
        $amount = $wpsc_cart->total_price;
        $name = "Your Order";
        $custom = $sessionid;
        
        $params = array (
        	'description' => $name,
        	'callback_url' => get_option('siteurl') . "/?coinbase_callback=$callbackSecret",
        	'success_url' => add_query_arg('sessionid', $sessionid, get_option('transact_url')) . "&coinbase_order",
        	'info_url' => get_option('siteurl'),
        	'cancel_url' => get_option('siteurl') . "?coinbase_order",
        );
        
        try {
		try {
			$code = $coinbase->createButton($name, $amount, $currency, $custom, $params)->button->code;
		} catch (Coinbase_TokensExpiredException $e) {
		
			// Try refreshing tokens
			$tokens = $oauth->refreshTokens($tokens);
			
			// Save new tokens
			update_option("coinbase_wpe_tokens", serialize($tokens));
			$coinbase = new Coinbase($oauth, $tokens);
			
			// Try request again
			$code = $coinbase->createButton($name, $amount, $currency, $custom, $params)->button->code;
		}
        } catch (Exception $e) {

        	// Cancel order
        	$sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `processed`= '5' WHERE `sessionid`=".$sessionid;
                $wpdb->query($sql);

                // Show failure message
		$msg = $e->getMessage();
		$_SESSION['WpscGatewayErrorMessage'] = "There was an error while processing your transaction. Try another payment method. $msg";
        	header("Location: " . get_option('checkout_url'));
        	exit();
        }

        $wpsc_cart->empty_cart();
        unset($_SESSION['WpscGatewayErrorMessage']);
        wp_redirect("https://coinbase.com/checkouts/$code");
        exit();
}

// Returns a form for the admin section
function form_coinbase_wpe() {

	require_once(dirname(__FILE__) . "/coinbase-php/Coinbase.php");

	$pageURL = 'http';
	if ($_SERVER["HTTPS"] == "on") {
		$pageURL .= "s";
	}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
			$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}

	$redirectUrl = dirname($pageURL) . "/options-general.php";

	$tokens = unserialize(get_option("coinbase_wpe_tokens"));
	$clientId = get_option("coinbase_wpe_clientid", "");
	$clientSecret = get_option("coinbase_wpe_clientsecret", "");
	
	$redirectUrlWithParams = $redirectUrl . "?page=wpsc-settings&tab=gateway&payment_gateway_id=coinbase_wpe&coinbase_callback=oauth";
	update_option("coinbase_wpe_oauthredirect", $redirectUrlWithParams);
	$oauth = new Coinbase_Oauth($clientId, $clientSecret, $redirectUrlWithParams);
	$authorize = $oauth->createAuthorizeUrl('merchant');
	
	if ($tokens == false) {
		$account = "<b>No merchant account connected.</b><br>";
		if($clientId == "") {
			$account .= "To connect a merchant account, <a href='https://coinbase.com/oauth/applications/new'>click here</a> and enter the following values:<br>
			<ul class='coinbase-wpe-list'>
				<li>Name: a name for this WP e-Commerce installation.</li>
				<li>Redirect URL: <input type='text' value='$redirectUrl' readonly></li>
			</ul>
			Then, copy the generated Client ID and Client Secret below. <b>After saving, return to this page to finish connecting a merchant account!</b>";
		} else {
			$account .= "Valid Client ID and Client Secret entered. <a href='$authorize'>Click here to connect a merchant account.</a>";
		}
		$showClient = true;
	} else {
		$account = "<b>Merchant account connected!</b> <a href='javascript:coinbase_wpe_doDisconnect();'>Disconnect.</a>";
		$showClient = false;
	}

	$clientId = htmlentities($clientId, ENT_QUOTES);
	$clientSecret = htmlentities($clientSecret, ENT_QUOTES);
	$content = "
	<script type=\"text/javascript\">
		function coinbase_wpe_doDisconnect() {
			document.getElementById('coinbase_wpe_disconnect').value = 'true';
			HTMLFormElement.prototype.submit.call(document.getElementById('coinbase_wpe_disconnect').form);
		}
	</script>
	<style type=\"text/css\">
		.coinbase-wpe-list {
			list-style: disc outside none;
		}
		.coinbase-wpe-list > li {
			margin-left: 30px;
		}
	</style>
	<tr>
		<td>Merchant Account</td>
		<td>$account</td>
	</tr>";
	
	if ($showClient) {
		$content .= "<tr>
			<td>Client ID</td>
			<td><input type='text' name='coinbase_wpe_clientid' value='$clientId' /></td>
		</tr>
		<tr>
			<td>Client Secret</td>
			<td><input type='text' name='coinbase_wpe_clientsecret' value='$clientSecret' /></td>
		</tr>";
	}
	$content .= "<input type='hidden' name='coinbase_wpe_disconnect' id='coinbase_wpe_disconnect' value='false' />
	";
	return $content;
}

// Validate and submit form fields from coinbase_wpe_form
function submit_coinbase_wpe() {
	
	if (isset($_POST['coinbase_wpe_clientsecret'])) {
		update_option("coinbase_wpe_clientid", $_POST['coinbase_wpe_clientid']);
		update_option("coinbase_wpe_clientsecret", $_POST['coinbase_wpe_clientsecret']);
	}
	if ($_POST['coinbase_wpe_disconnect'] == "true") {
		update_option("coinbase_wpe_tokens", false);
		update_option("coinbase_wpe_clientid", "");
		update_option("coinbase_wpe_clientsecret", "");
	}
}

function coinbase_wpe_callback() {
	if ($_GET['coinbase_callback'] == "oauth") {
		// OAuth callback!
		require_once(dirname(__FILE__) . "/coinbase-php/Coinbase.php");
		$clientId = get_option("coinbase_wpe_clientid");
		$clientSecret = get_option("coinbase_wpe_clientsecret");
		$redirectUrl = get_option("coinbase_wpe_oauthredirect");
		$oauth = new Coinbase_OAuth($clientId, $clientSecret, $redirectUrl);
		
		$tokens = $oauth->getTokens($_GET['code']);
		
		update_option("coinbase_wpe_tokens", serialize($tokens));
	} else if (isset($_GET['coinbase_order'])) {
		unset($_GET['order']);
	} else if($_GET['coinbase_callback'] == get_option("coinbase_wpe_callbacksecret")) {
		
		
		require_once(dirname(__FILE__) . "/coinbase-php/Coinbase.php");
		
		$postBody = json_decode(file_get_contents("php://input"));
		$coinbaseOrderId = $postBody->order->id;
		$sessionid = $postBody->order->custom;
		
		// Save order information
		$data = array(
			'processed'  => 3,
			'date'       => time(),
			'transactid' => $coinbaseOrderId,
		);
		wpsc_update_purchase_log_details( $sessionid, $data, 'sessionid' );
		transaction_results($sessionid, false);
	}
}

add_action('init', 'coinbase_wpe_callback');

