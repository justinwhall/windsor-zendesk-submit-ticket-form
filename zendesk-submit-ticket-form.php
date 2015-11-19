<?php
/*
	Plugin Name: Zendesk Submit Ticket Form
	Plugin URI: http://justinwhall.com
	Description: A simple plugin that generates a form for your users to submit a ticket to your Zendesk Account.
	Tags: contact, form, contact form, email, mail, captcha, zendesk
	Author: Justin W. Hall
	Author URI: http://justinwhall.com
	Donate link:
	Contributors: Jeff Starr. Forked from https://wordpress.org/plugins/simple-basic-contact-form/
	Requires at least: 4.1
	Tested up to: 4.4
	Stable tag: trunk
	Version: 20151111
	Text Domain: zdf
	Domain Path: /languages/
	License: GPL v2 or later
*/

if (!function_exists('add_action')) die();

$zdf_wp_vers = '1.1';
$zdf_version = '20151111';
$zdf_plugin  = __('Zendesk Submit Ticket Form', 'zdf');
$zdf_options = get_option('zdf_options');
$zdf_path    = plugin_basename(__FILE__); // 'simple-basic-contact-form/simple-basic-contact-form.php';
$zdf_homeurl = 'https://perishablepress.com/simple-basic-contact-form/';

// date_default_timezone_set('UTC');

// i18n
function zdf_i18n_init() {
	load_plugin_textdomain('zdf', false, dirname(plugin_basename(__FILE__)).'/languages/');
}
add_action('plugins_loaded', 'zdf_i18n_init');

// require minimum version of WordPress
function zdf_require_wp_version() {
	global $wp_version, $zdf_path, $zdf_plugin, $zdf_wp_vers;
	if (version_compare($wp_version, $zdf_wp_vers, '<')) {
		if (is_plugin_active($zdf_path)) {
			deactivate_plugins($zdf_path);
			$msg  = '<strong>'. $zdf_plugin .'</strong> '. __('requires WordPress ', 'zdf') . $zdf_wp_vers . __(' or higher, and has been deactivated!', 'zdf') .'<br />';
			$msg .= __('Please return to the', 'zdf') .' <a href="'. admin_url() .'">'. __('WordPress Admin area', 'zdf') .'</a> '. __('to upgrade WordPress and try again.', 'zdf');
			wp_die($msg);
		}
	}
}
if (isset($_GET['activate']) && $_GET['activate'] == 'true') {
	add_action('admin_init', 'zdf_require_wp_version');
}

// set some strings
$value_name = ''; $value_email = ''; $value_subject = ''; $value_response = ''; $value_message  = '';

if (isset($_POST['zdf_name']))     $value_name     = sanitize_text_field($_POST['zdf_name']);
if (isset($_POST['zdf_email']))    $value_email    = sanitize_email($_POST['zdf_email']);
if (isset($_POST['zdf_subject']))  $value_subject  = sanitize_text_field($_POST['zdf_subject']);
if (isset($_POST['zdf_response'])) $value_response = sanitize_text_field($_POST['zdf_response']);
if (isset($_POST['zdf_message']))  $value_message  = sanitize_text_field($_POST['zdf_message']);

$zdf_strings = array(
	'name' 	 => '<input name="zdf_name" id="zdf_name" type="text" size="33" maxlength="99" value="'. $value_name .'" placeholder="' . $zdf_options['zdf_input_name'] . '" />',
	'email'    => '<input name="zdf_email" id="zdf_email" type="text" size="33" maxlength="99" value="'. $value_email .'" placeholder="' . $zdf_options['zdf_input_email'] . '" />',
	'subject'  => '<input name="zdf_subject" id="zdf_subject" type="text" size="33" maxlength="99" value="'. $value_subject .'" placeholder="' . $zdf_options['zdf_input_subject'] . '" />',
	'response' => '<input name="zdf_response" id="zdf_response" type="text" size="33" maxlength="99" value="'. $value_response .'" placeholder="' . $zdf_options['zdf_input_captcha'] . '" />',
	'message'  => '<textarea name="zdf_message" id="zdf_message" cols="33" rows="7" placeholder="' . $zdf_options['zdf_input_message'] . '">'. $value_message .'</textarea>',
	'error'    => ''
);

// check for bad stuff
function zdf_malicious_input($input) {
	$maliciousness = false;
	$denied_inputs = array("\r", "\n", "mime-version", "content-type", "cc:", "to:");
	foreach($denied_inputs as $denied_input) {
		if(strpos(strtolower($input), strtolower($denied_input)) !== false) {
			$maliciousness = true;
			break;
		}
	}
	return $maliciousness;
}

// challenge question
function zdf_spam_question($input) {
	global $zdf_options;
	$casing   = $zdf_options['zdf_casing'];
	$response = $zdf_options['zdf_response'];
	$response = sanitize_text_field($response);
	if ($casing == false) return (strtoupper($input) == strtoupper($response));
	else return ($input == $response);
}

// collect ip address
function zdf_get_ip_address() {
	if (isset($_SERVER)) {
		if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
		} elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
			$ip_address = $_SERVER["HTTP_CLIENT_IP"];
		} else {
			$ip_address = $_SERVER["REMOTE_ADDR"];
		}
	} else {
		if (getenv('HTTP_X_FORWARDED_FOR')) {
			$ip_address = getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('HTTP_CLIENT_IP')) {
			$ip_address = getenv('HTTP_CLIENT_IP');
		} else {
			$ip_address = getenv('REMOTE_ADDR');
		}
	}
	return $ip_address;
}

// filter input
function zdf_input_filter() {
	global $zdf_options, $zdf_strings;
	$pass  = true;
	if (!isset($_POST['zdf_key'])) return false;

	$zdf_name = ''; $zdf_email = ''; $zdf_subject = ''; $zdf_message = ''; $sfc_response = '';

	if (isset($_POST['zdf_name']))     $zdf_name     = sanitize_text_field($_POST['zdf_name']);
	if (isset($_POST['zdf_email']))    $zdf_email    = sanitize_email($_POST['zdf_email']);
	if (isset($_POST['zdf_subject']))  $zdf_subject  = sanitize_text_field($_POST['zdf_subject']);
	if (isset($_POST['zdf_message']))  $zdf_message  = sanitize_text_field($_POST['zdf_message']);
	if (isset($_POST['zdf_response'])) $sfc_response = sanitize_text_field($_POST['zdf_response']);

	$sfc_style         = $zdf_options['zdf_style'];
	$sfc_input_name    = $zdf_options['zdf_input_name'];
	$sfc_input_mail    = $zdf_options['zdf_input_email'];
	$sfc_input_subject = $zdf_options['zdf_input_subject'];
	$sfc_input_captcha = $zdf_options['zdf_input_captcha'];
	$sfc_input_message = $zdf_options['zdf_input_message'];
	$sfc_hide_subject  = $zdf_options['zdf_subject'];

	if (!isset($_POST['zdf-nonce']) || !wp_verify_nonce($_POST['zdf-nonce'], 'zdf-nonce')) {
		$pass = false;
		$fail = 'nonce';
	}
	if (empty($zdf_name)) {
		$pass = false;
		$fail = 'empty';
		$zdf_strings['name'] = '<input class="zdf_error" name="zdf_name" id="zdf_name" type="text" size="33" maxlength="99" value="'. $zdf_name .'" '. $sfc_style .' placeholder="'. $sfc_input_name .'" />';
	}
	if (!is_email($zdf_email)) {
		$pass = false;
		$fail = 'empty';
		$zdf_strings['email'] = '<input class="zdf_error" name="zdf_email" id="zdf_email" type="text" size="33" maxlength="99" value="'. $zdf_email .'" '. $sfc_style .' placeholder="'. $sfc_input_mail .'" />';
	}
	if (empty($sfc_hide_subject) && empty($zdf_subject)) {
		$pass = false;
		$fail = 'empty';
		$zdf_strings['subject'] = '<input class="zdf_error" name="zdf_subject" id="zdf_subject" type="text" size="33" maxlength="99" value="'. $zdf_subject .'" '. $sfc_style .' placeholder="'. $sfc_input_subject .'" />';
	}
	if ($zdf_options['zdf_captcha'] == 1) {
		if (empty($sfc_response)) {
			$pass = false;
			$fail = 'empty';
			$zdf_strings['response'] = '<input class="zdf_error" name="zdf_response" id="zdf_response" type="text" size="33" maxlength="99" value="'. $sfc_response .'" '. $sfc_style .' placeholder="'. $sfc_input_captcha .'" />';
		}
		if (!zdf_spam_question($sfc_response)) {
			$pass = false;
			$fail = 'wrong';
			$zdf_strings['response'] = '<input class="zdf_error" name="zdf_response" id="zdf_response" type="text" size="33" maxlength="99" value="'. $sfc_response .'" '. $sfc_style .' placeholder="'. $sfc_input_captcha .'" />';
		}
	}
	if (empty($zdf_message)) {
		$pass = false;
		$fail = 'empty';
		$zdf_strings['message'] = '<textarea class="zdf_error" name="zdf_message" id="zdf_message" cols="33" rows="7" '. $sfc_style .' placeholder="' . $sfc_input_message .'">'. $zdf_message .'</textarea>';
	}
	if (zdf_malicious_input($zdf_name) || zdf_malicious_input($zdf_email) || zdf_malicious_input($zdf_subject)) {
		$pass = false;
		$fail = 'malicious';
	}
	if ($pass == true) {
		return true;
	} else {
		if ($fail == 'malicious') {
			$zdf_strings['error'] = '<p class="zdf_error">'. __('Please do not include any of the following in the Name, Email, or Subject fields: linebreaks, or the phrases "mime-version", "content-type", "cc:" or "to:".', 'zdf') .'</p>';

		} elseif ($fail == 'nonce') {
			$zdf_strings['error'] = '<p class="zdf_error">'. __('Invalid nonce value! Please try again or contact the administrator for help.', 'zdf') .'</p>';

		} elseif ($fail == 'empty') {
			$zdf_strings['error'] = $zdf_options['zdf_error'];

		} elseif ($fail == 'wrong') {
			$zdf_strings['error'] = $zdf_options['zdf_spam'];
		}
		return false;
	}
}


// shortcode to display contact form
add_shortcode('zendesk_ticket_form','zdf_shortcode');
function zdf_shortcode() {
	if (zdf_input_filter()) {
		return zdf_process_contact_form();
	} else {
		return zdf_display_contact_form();
	}
}

// template tag to display contact form
function zendesk_ticket_form() {
	if (zdf_input_filter()) {
		echo zdf_process_contact_form();
	} else {
		echo zdf_display_contact_form();
	}
}

// simple function to sanitize text
function zdf_sanitize_text($string) {
	return stripslashes(strip_tags(trim($string)));
}

// simple function to sanitize message content
function zdf_sanitize_message($string) {
	return stripslashes(trim($string));
}

// process contact form
function zdf_process_contact_form($content = '') {
	global $zdf_options, $zdf_strings;

	$topic     = $zdf_options['zdf_subject'];
	$recipient = $zdf_options['zdf_email'];
	$recipname = $zdf_options['zdf_name'];
	$recipsite = $zdf_options['zdf_website'];
	$success   = $zdf_options['zdf_success'];
	$carbon    = $zdf_options['zdf_carbon'];
	$offset    = $zdf_options['zdf_offset'];
	$prepend   = $zdf_options['zdf_prepend'];
	$append    = $zdf_options['zdf_append'];
	$styles    = $zdf_options['zdf_css'];

	$email     = isset($_POST['zdf_email'])   ? sanitize_email($_POST['zdf_email'])         : '';
	$name      = isset($_POST['zdf_name'])    ? zdf_sanitize_text($_POST['zdf_name'])       : '';
	$subject   = isset($_POST['zdf_subject']) ? zdf_sanitize_text($_POST['zdf_subject'])    : '';
	$message   = isset($_POST['zdf_message']) ? zdf_sanitize_message($_POST['zdf_message']) : '';

	$agent     = isset($_SERVER['HTTP_USER_AGENT']) ? zdf_sanitize_text($_SERVER['HTTP_USER_AGENT'])            : __('[ undefined ]', 'zdf');
	$form      = isset($_SERVER['HTTP_REFERER'])    ? zdf_sanitize_text($_SERVER['HTTP_REFERER'])               : __('[ undefined ]', 'zdf');
	$host      = isset($_SERVER['REMOTE_ADDR'])     ? zdf_sanitize_text(gethostbyaddr($_SERVER['REMOTE_ADDR'])) : __('[ undefined ]', 'zdf');

	$senderip  = zdf_sanitize_text(zdf_get_ip_address());

	$date = date_i18n(get_option('date_format'), current_time('timestamp')) .' @ '. date_i18n(get_option('time_format'), current_time('timestamp'));

	$zdf_custom = (!empty($styles)) ? '<style>' . $styles . '</style>' : '';

	$topic = (!empty($subject)) ? $subject : $topic;

	$headers  = 'X-Mailer: Simple Basic Contact Form'. "\n";
	$headers .= 'From: '. $name .' <'. $email .'>'. "\n";
	$headers .= 'Content-Type: text/plain; charset="'. get_option('blog_charset') .'"'. "\n";



	$fullmsg = __('Hello ', 'zdf') . $recipname . ', ' . "\n\n" .
__('You are being contacted via ', 'zdf') . $recipsite . ': ' . "\n\n" .

__('Name: ',    'zdf') . $name  . "\n" .
__('Email: ',   'zdf') . $email . "\n" .
__('Message: ', 'zdf') . "\n\n" . $message . "\n\n" .

__('-----------------------',  'zdf') . "\n\n" .
__('Additional Information: ', 'zdf') . "\n\n" .

__('Site: ',  'zdf') . $recipsite . "\n" .
__('URL: ',   'zdf') . $form      . "\n" .
__('Date: ',  'zdf') . $date      . "\n" .
__('IP: ',    'zdf') . $senderip  . "\n" .
__('Host: ',  'zdf') . $host      . "\n" .
__('Agent: ', 'zdf') . $agent     . "\n\n";

	$fullmsg = apply_filters('zdf_full_message', $fullmsg);

	// build ticket
	$ticket = array(
		'ticket' => array(
			'subject' => $_POST['zdf_subject'],
			'comment' => array(
				'value'=>$_POST['zdf_message']
				),
		    'requester' => array(
		    	'name' => $_POST['zdf_name'],
		    	'email' => $_POST['zdf_email']
		    	)
		    )
	);


	$ticket = json_encode($ticket);
	$return = zdf_curl_wrap("/tickets.json", $ticket);

	if (property_exists($return, 'ticket')) {
		$name    = htmlentities($name, ENT_QUOTES, get_option('blog_charset', 'UTF-8'));
		$topic   = htmlentities($topic, ENT_QUOTES, get_option('blog_charset', 'UTF-8'));
		$message = htmlentities($message, ENT_QUOTES, get_option('blog_charset', 'UTF-8'));

		$reset_link = '<p class="zdf_reset">'. __('[ ', 'zdf') .'<a href="'. $form .'">'. __('Click here to reset the form', 'zdf') .'</a>'. __(' ]', 'zdf') .'</p></div>'. $zdf_custom . $append;

		$short_results = $prepend .'<div id="zdf_success" class="zdf">'. $success .'<pre>'. __('Message: ', 'zdf') . "\n\n" . $message .'</pre>'. $reset_link;

		$full_results = $prepend .'<div id="zdf_success" class="zdf">'. $success .'
		<pre>'. __('Name: ', 'zdf') . $name  . "\n" .
		__('Email: ',   'zdf') . $email   . "\n" .
		__('Ticket Subject: ', 'zdf') . $topic   . "\n" .
		__('Date: ',    'zdf') . $date    . "\n" .
		__('Your Message: ', 'zdf') . "\n\n" . $message .'</pre>'. $reset_link;

		$short_results = apply_filters('zdf_short_results', $short_results);
		$full_results  = apply_filters('zdf_full_results', $full_results);


		if ($zdf_options['zdf_success_details']) echo $full_results;
		else echo $short_results;

	}else{
		echo '<div class="ticket-error" style="text-align:center;">Sorry, Looks as thought we had some trouble procceing your ticket.<br />Please submit your request at our <a href="">Zendesk Support Desk Page</a>.</div>';
	}
}

function zdf_curl_wrap($url, $json){
	global $zdf_options;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10 );
	curl_setopt($ch, CURLOPT_URL, $zdf_options['zdf_url'].$url);
	curl_setopt($ch, CURLOPT_USERPWD, $zdf_options['zdf_user']."/token:".$zdf_options['zdf_apikey']);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
	curl_setopt($ch, CURLOPT_USERAGENT, "MozillaXYZ/1.0");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	$output = curl_exec($ch);
	curl_close($ch);
	$decoded = json_decode($output);
	return $decoded;
}

// display contact form
function zdf_display_contact_form() {
	global $zdf_options, $zdf_strings;

	$question = $zdf_options['zdf_question'];
	$nametext = $zdf_options['zdf_nametext'];
	$subjtext = $zdf_options['zdf_subjtext'];
	$mailtext = $zdf_options['zdf_mailtext'];
	$messtext = $zdf_options['zdf_messtext'];
	$captcha  = $zdf_options['zdf_captcha'];
	$offset   = $zdf_options['zdf_offset'];

	if ($zdf_options['zdf_preform'] !== '') {
		$zdf_preform = $zdf_options['zdf_preform'];
	} else { $zdf_preform = ''; }

	if ($zdf_options['zdf_appform'] !== '') {
		$zdf_appform = $zdf_options['zdf_appform'];
	} else { $zdf_appform = ''; }

	if ($zdf_options['zdf_css'] !== '') {
		$zdf_custom = '<style>' . $zdf_options['zdf_css'] . '</style>';
	} else { $zdf_custom = ''; }

	if (empty($zdf_options['zdf_subject'])) {
		$zdf_subject = '
				<fieldset class="zdf-subject">
					<label for="zdf_subject">'. $subjtext .'</label>
					'. $zdf_strings['subject'] .'
				</fieldset>';
	} else { $zdf_subject = ''; }

	if ($captcha == 1) {
		$captcha_box = '
				<fieldset class="zdf-response">
					<label for="zdf_response">'. $question .'</label>
					'. $zdf_strings['response'] .'
				</fieldset>';
	} else { $captcha_box = ''; }

	$zdf_form = ($zdf_preform . $zdf_strings['error'] . '
		<div id="simple-contact-form" class="zdf">
			<form action="" method="post">
				<fieldset class="zdf-name">
					<label for="zdf_name">'. $nametext .'</label>
					'. $zdf_strings['name'] .'
				</fieldset>
				<fieldset class="zdf-email">
					<label for="zdf_email">'. $mailtext .'</label>
					'. $zdf_strings['email'] .'
				</fieldset>'.
					$zdf_subject . $captcha_box .'
				<fieldset class="zdf-message">
					<label for="zdf_message">'. $messtext .'</label>
					'. $zdf_strings['message'] .'
				</fieldset>
				<div class="zdf-submit">
					<input type="submit" name="Submit" id="zdf_contact" value="' . __('Send email', 'zdf') . '">
					<input type="hidden" name="zdf_key" value="process">
					'. wp_nonce_field('zdf-nonce', 'zdf-nonce', false, false) .'
				</div>
			</form>
		</div>
		' . $zdf_custom . $zdf_appform);

	return apply_filters('zdf_filter_contact_form', $zdf_form);
}

// display settings link on plugin page
add_filter ('plugin_action_links', 'zdf_plugin_action_links', 10, 2);
function zdf_plugin_action_links($links, $file) {
	global $zdf_path;
	if ($file == $zdf_path) {
		$zdf_links = '<a href="'. get_admin_url() .'options-general.php?page='. $zdf_path .'">'. __('Settings', 'zdf') .'</a>';
		array_unshift($links, $zdf_links);
	}
	return $links;
}

// rate plugin link
function add_zdf_links($links, $file) {
	if ($file == plugin_basename(__FILE__)) {
		$rate_url = 'http://wordpress.org/support/view/plugin-reviews/' . basename(dirname(__FILE__)) . '?rate=5#postform';
		$links[] = '<a target="_blank" href="'. $rate_url .'" title="Click here to rate and review this plugin on WordPress.org">Rate this plugin</a>';
	}
	return $links;
}
add_filter('plugin_row_meta', 'add_zdf_links', 10, 2);

// delete plugin settings
function zdf_delete_plugin_options() {
	delete_option('zdf_options');
}
if ($zdf_options['default_options'] == 1) {
	register_uninstall_hook (__FILE__, 'zdf_delete_plugin_options');
}

// define default settings
register_activation_hook (__FILE__, 'zdf_add_defaults');
function zdf_add_defaults() {
	$user_info = get_userdata(1);
	if ($user_info == true) {
		$admin_name = $user_info->user_login;
	} else {
		$admin_name = 'Neo Smith';
	}
	$site_title = get_bloginfo('name');
	$admin_mail = get_bloginfo('admin_email');
	$tmp = get_option('zdf_options');
	if(($tmp['default_options'] == '1') || (!is_array($tmp))) {
		$arr = array(
			'default_options'     => 0,
			'zdf_name'            => $admin_name,
			'zdf_website'         => $site_title,
			'zdf_email'           => $admin_mail,
			'zdf_offset'          => '0',
			'zdf_subject'         => __('Message sent from your contact form.', 'zdf'),
			'zdf_question'        => __('1 + 1 =', 'zdf'),
			'zdf_response'        => __('2', 'zdf'),
			'zdf_casing'          => 0,
			'zdf_nametext'        => __('Name (Required)', 'zdf'),
			'zdf_mailtext'        => __('Email (Required)', 'zdf'),
			'zdf_subjtext'        => __('Subject (Required)', 'zdf'),
			'zdf_messtext'        => __('Message (Required)', 'zdf'),
			'zdf_success'         => '<p class=\'zdf_success\'><strong>' . __('Success!', 'zdf') . '</strong> ' . __('Your message has been sent.', 'zdf') . '</p>',
			'zdf_error'           => '<p class=\'zdf_error\'>' . __('Please complete the required fields.', 'zdf') . '</p>',
			'zdf_spam'            => '<p class=\'zdf_spam\'>' . __('Incorrect response for challenge question. Please try again.', 'zdf') . '</p>',
			'zdf_style'           => 'style=\'border: 1px solid #CC0000;\'',
			'zdf_prepend'         => '',
			'zdf_apikey'        => '',
			'zdf_user'          => '',
			'zdf_url'           => '',
			'zdf_url'           => '',
			'zdf_append'          => '',
			'zdf_css'             => '#simple-contact-form fieldset { width: 100%; overflow: hidden; margin: 5px 0; border: 0; } #simple-contact-form fieldset input { float: left; width: 60%; } #simple-contact-form textarea { float: left; clear: both; width: 95%; } #simple-contact-form label { float: left; clear: both; width: 30%; margin-top: 3px; line-height: 1.8; font-size: 90%; }',
			'zdf_preform'         => '',
			'zdf_appform'         => '<div style=\'clear:both;\'>&nbsp;</div>',
			'zdf_captcha'         => 1,
			'zdf_carbon'          => 1,
			'zdf_input_name'      => __('Your Name', 'zdf'),
			'zdf_input_email'     => __('Your Email', 'zdf'),
			'zdf_input_subject'   => __('Email Subject', 'zdf'),
			'zdf_input_captcha'   => __('Correct Response', 'zdf'),
			'zdf_input_message'   => __('Your Message', 'zdf'),
			'zdf_mail_function'   => 1,
			'zdf_success_details' => 1,
		);
		update_option('zdf_options', $arr);
	}
}

// whitelist settings
add_action ('admin_init', 'zdf_init');
function zdf_init() {
	register_setting('zdf_plugin_options', 'zdf_options', 'zdf_validate_options');
}

// sanitize and validate input
function zdf_validate_options($input) {

	if (!isset($input['default_options'])) $input['default_options'] = null;
	$input['default_options'] = ($input['default_options'] == 1 ? 1 : 0);

	$input['zdf_name']     = wp_filter_nohtml_kses($input['zdf_name']);
	$input['zdf_website']  = wp_filter_nohtml_kses($input['zdf_website']);
	$input['zdf_email']    = wp_filter_nohtml_kses($input['zdf_email']);
	$input['zdf_offset']   = wp_filter_nohtml_kses($input['zdf_offset']);
	$input['zdf_subject']  = wp_filter_nohtml_kses($input['zdf_subject']);
	$input['zdf_question'] = wp_filter_nohtml_kses($input['zdf_question']);
	$input['zdf_response'] = wp_filter_nohtml_kses($input['zdf_response']);

	if (!isset($input['zdf_casing'])) $input['zdf_casing'] = null;
	$input['zdf_casing'] = ($input['zdf_casing'] == 1 ? 1 : 0);

	$input['zdf_nametext'] = wp_filter_nohtml_kses($input['zdf_nametext']);
	$input['zdf_mailtext'] = wp_filter_nohtml_kses($input['zdf_mailtext']);
	$input['zdf_subjtext'] = wp_filter_nohtml_kses($input['zdf_subjtext']);
	$input['zdf_messtext'] = wp_filter_nohtml_kses($input['zdf_messtext']);

	// dealing with kses
	global $allowedposttags;
	$allowed_atts = array('align'=>array(), 'class'=>array(), 'id'=>array(), 'dir'=>array(), 'lang'=>array(), 'style'=>array(), 'xml:lang'=>array(), 'src'=>array(), 'alt'=>array(), 'href'=>array(), 'title'=>array());

	$allowedposttags['strong'] = $allowed_atts;
	$allowedposttags['small'] = $allowed_atts;
	$allowedposttags['span'] = $allowed_atts;
	$allowedposttags['abbr'] = $allowed_atts;
	$allowedposttags['code'] = $allowed_atts;
	$allowedposttags['div'] = $allowed_atts;
	$allowedposttags['img'] = $allowed_atts;
	$allowedposttags['h1'] = $allowed_atts;
	$allowedposttags['h2'] = $allowed_atts;
	$allowedposttags['h3'] = $allowed_atts;
	$allowedposttags['h4'] = $allowed_atts;
	$allowedposttags['h5'] = $allowed_atts;
	$allowedposttags['ol'] = $allowed_atts;
	$allowedposttags['ul'] = $allowed_atts;
	$allowedposttags['li'] = $allowed_atts;
	$allowedposttags['em'] = $allowed_atts;
	$allowedposttags['p'] = $allowed_atts;
	$allowedposttags['a'] = $allowed_atts;

	$input['zdf_success'] = wp_kses_post($input['zdf_success'], $allowedposttags);
	$input['zdf_error']   = wp_kses_post($input['zdf_error'], $allowedposttags);
	$input['zdf_spam']    = wp_kses_post($input['zdf_spam'], $allowedposttags);
	$input['zdf_style']   = wp_kses_post($input['zdf_style'], $allowedposttags);
	$input['zdf_prepend'] = wp_kses_post($input['zdf_prepend'], $allowedposttags);
	$input['zdf_append']  = wp_kses_post($input['zdf_append'], $allowedposttags);
	$input['zdf_preform'] = wp_kses_post($input['zdf_preform'], $allowedposttags);
	$input['zdf_appform'] = wp_kses_post($input['zdf_appform'], $allowedposttags);

	$input['zdf_css'] = wp_filter_nohtml_kses($input['zdf_css']);

	if (!isset($input['zdf_captcha'])) $input['zdf_captcha'] = null;
	$input['zdf_captcha'] = ($input['zdf_captcha'] == 1 ? 1 : 0);

	if (!isset($input['zdf_carbon'])) $input['zdf_carbon'] = null;
	$input['zdf_carbon'] = ($input['zdf_carbon'] == 1 ? 1 : 0);

	$input['zdf_input_name'] = wp_filter_nohtml_kses($input['zdf_input_name']);
	$input['zdf_input_email'] = wp_filter_nohtml_kses($input['zdf_input_email']);
	$input['zdf_input_subject'] = wp_filter_nohtml_kses($input['zdf_input_subject']);
	$input['zdf_input_captcha'] = wp_filter_nohtml_kses($input['zdf_input_captcha']);
	$input['zdf_input_message'] = wp_filter_nohtml_kses($input['zdf_input_message']);

	if (!isset($input['zdf_mail_function'])) $input['zdf_mail_function'] = null;
	$input['zdf_mail_function'] = ($input['zdf_mail_function'] == 1 ? 1 : 0);

	if (!isset($input['zdf_success_details'])) $input['zdf_success_details'] = null;
	$input['zdf_success_details'] = ($input['zdf_success_details'] == 1 ? 1 : 0);

	return $input;
}

// add the options page
add_action ('admin_menu', 'zdf_add_options_page');
function zdf_add_options_page() {
	global $zdf_plugin;
	add_options_page($zdf_plugin, 'ZendDesk Form', 'manage_options', __FILE__, 'zdf_render_form');
}

// create the options page
function zdf_render_form() {
	global $zdf_plugin, $zdf_options, $zdf_path, $zdf_homeurl, $zdf_version; ?>

	<style type="text/css">

		#mm-plugin-options h1 small { font-size: 60%; }
		#mm-plugin-options h2 { margin: 0; padding: 12px 0 12px 15px; font-size: 16px; cursor: pointer; }
		#mm-plugin-options h3 { margin: 20px 15px; font-size: 14px; }

		#mm-plugin-options p { margin-left: 15px; }
		#mm-plugin-options ul { margin: 15px 15px 25px 40px; line-height: 16px; }
		#mm-plugin-options li { margin: 8px 0; list-style-type: disc; }
		#mm-plugin-options abbr { cursor: help; border-bottom: 1px dotted #dfdfdf; }

		.mm-table-wrap { margin: 15px; }
		.mm-table-wrap td,
		.mm-table-wrap th { padding: 15px; vertical-align: middle; }
		.mm-item-caption { margin: 3px 0 0 3px; font-size: 11px; color: #777; line-height: 17px; }
		.mm-code { background-color: #fafae0; color: #333; font-size: 14px; }

		#setting-error-settings_updated { margin: 10px 0; }
		#setting-error-settings_updated p { margin: 5px; }
		#mm-plugin-options .button-primary { margin: 0 0 15px 15px; }

		#mm-panel-toggle { margin: 5px 0; }
		#mm-credit-info { margin-top: -5px; }
		#mm-iframe-wrap { width: 100%; height: 250px; overflow: hidden; }
		#mm-iframe-wrap iframe { width: 100%; height: 100%; overflow: hidden; margin: 0; padding: 0; }
	</style>

	<div id="mm-plugin-options" class="wrap">
		<?php screen_icon(); ?>

		<h1><?php echo $zdf_plugin; ?> <small><?php echo 'v' . $zdf_version; ?></small></h1>
		<div id="mm-panel-toggle"><a href="<?php get_admin_url() . 'options-general.php?page=' . $zdf_path; ?>"><?php _e('Toggle all panels', 'zdf'); ?></a></div>

		<form method="post" action="options.php">
			<?php $zdf_options = get_option('zdf_options'); settings_fields('zdf_plugin_options'); ?>

			<div class="metabox-holder">
				<div class="meta-box-sortables ui-sortable">
					<div id="mm-panel-overview" class="postbox">
						<h2><?php _e('Overview', 'zdf'); ?></h2>
						<div class="toggle">
							<div class="mm-panel-overview">
								<p>
									<strong><?php echo $zdf_plugin; ?></strong> <?php _e('(SBCF) is a simple basic contact form for your WordPress-powered website. Automatically sends a carbon copy to the sender.', 'zdf'); ?>
									<?php _e('Simply choose your options, then add the shortcode to any post or page to display the contact form. For a contact form with more options try ', 'zdf'); ?>
									<a href="https://perishablepress.com/contact-coldform/">Contact Coldform</a>.
								</p>
								<ul>
									<li><?php _e('To configure the contact form, visit the', 'zdf'); ?> <a id="mm-panel-primary-link" href="#mm-panel-primary"><?php _e('Options panel', 'zdf'); ?></a>.</li>
									<li><?php _e('For the shortcode and template tag, visit', 'zdf'); ?> <a id="mm-panel-secondary-link" href="#mm-panel-secondary"><?php _e('Shortcodes &amp; Template Tags', 'zdf'); ?></a>.</li>
									<li><?php _e('To restore default settings, visit', 'zdf'); ?> <a id="mm-restore-settings-link" href="#mm-restore-settings"><?php _e('Restore Default Options', 'zdf'); ?></a>.</li>
									<li>
										<?php _e('For more information check the', 'zdf'); ?> <a target="_blank" href="<?php echo plugins_url('/simple-basic-contact-form/readme.txt', dirname(__FILE__)); ?>">readme.txt</a>
										<?php _e('and', 'zdf'); ?> <a target="_blank" href="<?php echo $zdf_homeurl; ?>"><?php _e('SBCF Homepage', 'zdf'); ?></a>.
									</li>
									<li><?php _e('If you like this plugin, please', 'zdf'); ?>
										<a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/<?php echo basename(dirname(__FILE__)); ?>?rate=5#postform" title="<?php _e('Click here to rate and review this plugin on WordPress.org', 'zdf'); ?>">
											<?php _e('give it a 5-star rating at the Plugin Directory', 'zdf'); ?>&nbsp;&raquo;
										</a>
									</li>
								</ul>
							</div>
						</div>
					</div>
					<div id="mm-panel-primary" class="postbox">
						<h2><?php _e('Options', 'zdf'); ?></h2>
						<div class="toggle<?php if (!isset($_GET["settings-updated"])) { echo ' default-hidden'; } ?>">
							<p><?php _e('Configure the contact form..', 'zdf'); ?></p>
							<h3><?php _e('General options', 'zdf'); ?></h3>
							<div class="mm-table-wrap">
								<table class="widefat mm-table">
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_name]"><?php _e('Your Name', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_name]" value="<?php echo $zdf_options['zdf_name']; ?>" />
										<div class="mm-item-caption"><?php _e('How would you like to be addressed in messages sent from the contact form?', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_email]"><?php _e('Your Email', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_email]" value="<?php echo $zdf_options['zdf_email']; ?>" />
										<div class="mm-item-caption"><?php _e('Where would you like to receive messages sent from the contact form?', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_website]"><?php _e('Your Site', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_website]" value="<?php echo $zdf_options['zdf_website']; ?>" />
										<div class="mm-item-caption"><?php _e('From where should the contact messages indicate they were sent?', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_subject]"><?php _e('Default Subject', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_subject]" value="<?php echo $zdf_options['zdf_subject']; ?>" />
										<div class="mm-item-caption"><?php _e('Specify any value here to hide the Subject field (or leave blank to display the Subject field).', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_captcha]"><?php _e('Enable Captcha', 'zdf'); ?></label></th>
										<td><input type="checkbox" name="zdf_options[zdf_captcha]" value="1" <?php if (isset($zdf_options['zdf_captcha'])) { checked('1', $zdf_options['zdf_captcha']); } ?> />
										<?php _e('Check this box if you want to enable the captcha (challenge question/answer).', 'zdf'); ?></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_question]"><?php _e('Challenge Question', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_question]" value="<?php echo $zdf_options['zdf_question']; ?>" />
										<div class="mm-item-caption"><?php _e('What question should be answered correctly before the message is sent?', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_response]"><?php _e('Challenge Response', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_response]" value="<?php echo $zdf_options['zdf_response']; ?>" />
										<div class="mm-item-caption"><?php _e('What is the <em>only</em> correct answer to the challenge question?', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_casing]"><?php _e('Case-sensitive?', 'zdf'); ?></label></th>
										<td><input type="checkbox" name="zdf_options[zdf_casing]" value="1" <?php if (isset($zdf_options['zdf_casing'])) { checked('1', $zdf_options['zdf_casing']); } ?> />
										<?php _e('Check this box if you want the challenge response to be case-sensitive.', 'zdf'); ?></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_offset]"><?php _e('Time Offset', 'zdf'); ?></label></th>
										<td>
											<input type="text" size="50" maxlength="200" name="zdf_options[zdf_offset]" value="<?php echo $zdf_options['zdf_offset']; ?>" />
											<div class="mm-item-caption">
												<?php _e('Please specify any time offset here. For example, "+7" or "-7". If no offset or unsure, enter "0" (zero, default).', 'zdf'); ?><br />
												<?php _e('Current time:', 'zdf'); ?> <?php echo date("l, F jS, Y @ g:i a", time() + $zdf_options['zdf_offset']*60*60); ?>
											</div>
										</td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_carbon]"><?php _e('Enable Carbon Copies?', 'zdf'); ?></label></th>
										<td><input type="checkbox" name="zdf_options[zdf_carbon]" value="1" <?php if (isset($zdf_options['zdf_carbon'])) { checked('1', $zdf_options['zdf_carbon']); } ?> />
										<?php _e('Check this box if you want to enable the automatic sending of carbon-copies to the sender.', 'zdf'); ?></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_mail_function]"><?php _e('Mail Function', 'zdf'); ?></label></th>
										<td><input type="checkbox" name="zdf_options[zdf_mail_function]" value="1" <?php if (isset($zdf_options['zdf_mail_function'])) { checked('1', $zdf_options['zdf_mail_function']); } ?> />
										<?php _e('Check this box if you want to use PHP&rsquo;s mail() function instead of WP&rsquo;s wp_mail() (default).', 'zdf'); ?></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_success_details]"><?php _e('Success Message', 'zdf'); ?></label></th>
										<td><input type="checkbox" name="zdf_options[zdf_success_details]" value="1" <?php if (isset($zdf_options['zdf_success_details'])) { checked('1', $zdf_options['zdf_success_details']); } ?> />
										<?php _e('Check this box to display verbose success message (default), or uncheck for brief success message.', 'zdf'); ?></td>
									</tr>
								</table>
							</div>
							<h3><?php _e('Appearance', 'zdf'); ?></h3>
							<div class="mm-table-wrap">
								<table class="widefat mm-table">
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_css]"><?php _e('Custom CSS styles', 'zdf'); ?></label></th>
										<td><textarea class="textarea" rows="7" cols="55" name="zdf_options[zdf_css]"><?php echo esc_textarea($zdf_options['zdf_css']); ?></textarea>
										<div class="mm-item-caption"><?php _e('Add some CSS to style the contact form. Note: do not include the <code>&lt;style&gt;</code> tags.<br />
											Note: visit <a href="http://m0n.co/i" target="_blank">m0n.co/i</a> for complete list of CSS hooks.', 'zdf'); ?></div></td>
									</tr>
								</table>
							</div>
							<h3><?php _e('Field Captions &amp; Placeholders', 'zdf'); ?></h3>
							<div class="mm-table-wrap">
								<table class="widefat mm-table">
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_nametext]"><?php _e('Caption for Name Field', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_nametext]" value="<?php echo $zdf_options['zdf_nametext']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the caption that corresponds with the Name field.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_mailtext]"><?php _e('Caption for Email Field', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_mailtext]" value="<?php echo $zdf_options['zdf_mailtext']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the caption that corresponds with the Email field.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_subjtext]"><?php _e('Caption for Subject Field', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_subjtext]" value="<?php echo $zdf_options['zdf_subjtext']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the caption that corresponds with the Subject field.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_messtext]"><?php _e('Caption for Message Field', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_messtext]" value="<?php echo $zdf_options['zdf_messtext']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the caption that corresponds with the Message field.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_input_name]"><?php _e('Placeholder for Name Field', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_input_name]" value="<?php echo $zdf_options['zdf_input_name']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the text appearing as the input placeholder for the Name field.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_input_email]"><?php _e('Placeholder for Email Field', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_input_email]" value="<?php echo $zdf_options['zdf_input_email']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the text appearing as the input placeholder for the Email field.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_input_subject]"><?php _e('Placeholder for Subject Field', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_input_subject]" value="<?php echo $zdf_options['zdf_input_subject']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the text appearing as the input placeholder for the Subject field.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_input_captcha]"><?php _e('Placeholder for Captcha Field', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_input_captcha]" value="<?php echo $zdf_options['zdf_input_captcha']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the text appearing as the input placeholder for the Captcha field.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_input_message]"><?php _e('Placeholder for Message Field', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_input_message]" value="<?php echo $zdf_options['zdf_input_message']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the text appearing as the input placeholder for the Message field.', 'zdf'); ?></div></td>
									</tr>
								</table>
							</div>
							<h3><?php _e('Success &amp; error messages', 'zdf'); ?></h3>
							<p><?php _e('Note: use single quotes for attributes. Example: <code>&lt;span class=\'error\'&gt;</code>', 'zdf'); ?></p>
							<div class="mm-table-wrap">
								<table class="widefat mm-table">
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_success]"><?php _e('Success Message', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_success]" value="<?php echo $zdf_options['zdf_success']; ?>" />
										<div class="mm-item-caption"><?php _e('When the form is sucessfully submitted, this message will be displayed to the sender.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_error]"><?php _e('Error Message', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_error]" value="<?php echo $zdf_options['zdf_error']; ?>" />
										<div class="mm-item-caption"><?php _e('If the user skips a required field, this message will be displayed.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_spam]"><?php _e('Incorrect Response', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_spam]" value="<?php echo $zdf_options['zdf_spam']; ?>" />
										<div class="mm-item-caption"><?php _e('When the challenge question is answered incorrectly, this message will be displayed.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_style]"><?php _e('Error Fields', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_style]" value="<?php echo $zdf_options['zdf_style']; ?>" />
										<div class="mm-item-caption"><?php _e('Here you may specify the default CSS for error fields, or add other attributes.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_preform]"><?php _e('Custom content before the form', 'zdf'); ?></label></th>
										<td><textarea class="textarea" rows="3" cols="55" name="zdf_options[zdf_preform]"><?php echo esc_textarea($zdf_options['zdf_preform']); ?></textarea>
										<div class="mm-item-caption"><?php _e('Add some text/markup to appear <em>before</em> the submitted contact form (optional).', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_appform]"><?php _e('Custom content after the form', 'zdf'); ?></label></th>
										<td><textarea class="textarea" rows="3" cols="55" name="zdf_options[zdf_appform]"><?php echo esc_textarea($zdf_options['zdf_appform']); ?></textarea>
										<div class="mm-item-caption"><?php _e('Add some text/markup to appear <em>after</em> the submitted contact form (optional).', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_prepend]"><?php _e('Custom content before results', 'zdf'); ?></label></th>
										<td><textarea class="textarea" rows="3" cols="55" name="zdf_options[zdf_prepend]"><?php echo esc_textarea($zdf_options['zdf_prepend']); ?></textarea>
										<div class="mm-item-caption"><?php _e('Add some text/markup to appear <em>before</em> the success message (optional).', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_append]"><?php _e('Custom content after results', 'zdf'); ?></label></th>
										<td><textarea class="textarea" rows="3" cols="55" name="zdf_options[zdf_append]"><?php echo esc_textarea($zdf_options['zdf_append']); ?></textarea>
										<div class="mm-item-caption"><?php _e('Add some text/markup to appear <em>after</em> the success message (optional).', 'zdf'); ?></div></td>
									</tr>
								</table>
							</div>
							<input type="submit" class="button-primary" value="<?php _e('Save Settings', 'zdf'); ?>" />
						</div>
					</div>
					<div id="mm-panel-secondary" class="postbox">
						<h2><?php _e('Shortcodes &amp; Template Tags', 'zdf'); ?></h2>
						<div class="toggle<?php if (!isset($_GET["settings-updated"])) { echo ' default-hidden'; } ?>">
							<h3><?php _e('Shortcode', 'zdf'); ?></h3>
							<p><?php _e('Use this shortcode to display the contact form on a post or page:', 'zdf'); ?></p>
							<p><code class="mm-code">[zendesk_ticket_form]</code></p>
							<h3><?php _e('Template tag', 'zdf'); ?></h3>
							<p><?php _e('Use this template tag to display the form anywhere in your theme template:', 'zdf'); ?></p>
							<p><code class="mm-code">&lt;?php if (function_exists('zendesk_ticket_form')) zendesk_ticket_form(); ?&gt;</code></p>
						</div>
					</div>

					<div id="mm-restore-settings" class="postbox">
						<h2><?php _e('Restore Default Options', 'zdf'); ?></h2>
						<div class="toggle<?php if (!isset($_GET["settings-updated"])) { echo ' default-hidden'; } ?>">
							<p>
								<input name="zdf_options[default_options]" type="checkbox" value="1" id="mm_restore_defaults" <?php if (isset($zdf_options['default_options'])) { checked('1', $zdf_options['default_options']); } ?> />
								<label class="description" for="zdf_options[default_options]"><?php _e('Restore default options upon plugin deactivation/reactivation.', 'zdf'); ?></label>
							</p>
							<p>
								<small>
									<?php _e('<strong>Tip:</strong> leave this option unchecked to remember your settings. Or, to go ahead and restore all default options, check the box, save your settings, and then deactivate/reactivate the plugin.', 'zdf'); ?>
								</small>
							</p>
							<input type="submit" class="button-primary" value="<?php _e('Save Settings', 'zdf'); ?>" />
						</div>
					</div>
					<div id="zd-creds" class="postbox">
						<h2><?php _e('ZendDesk Credentials', 'zdf'); ?></h2>
						<div class="toggle">
							<div class="mm-table-wrap">
								<table class="widefat mm-table">
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_apikey]"><?php _e('Zendesk API Key', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_apikey]" value="<?php echo $zdf_options['zdf_apikey']; ?>" />
										<div class="mm-item-caption"><?php _e('Your API Key can be found <a href="#">Here</a>.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_user]"><?php _e('Zendesk User', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_user]" value="<?php echo $zdf_options['zdf_user']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the email you used to sign up with.', 'zdf'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="zdf_options[zdf_url]"><?php _e('Zendesk URL', 'zdf'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="zdf_options[zdf_url]" value="<?php echo $zdf_options['zdf_url']; ?>" />
										<div class="mm-item-caption"><?php _e('The Zendesk URL where users can submit support tickets.', 'zdf'); ?></div></td>
									</tr>
								</table>
							</div>
							<input type="submit" class="button-primary" value="<?php _e('Save Settings', 'zdf'); ?>" />
						</div>
					</div>
					<div id="mm-panel-current" class="postbox">
						<h2><?php _e('Updates &amp; Info', 'zdf'); ?></h2>
						<div class="toggle">
							<div id="mm-iframe-wrap">
								<!-- <iframe src="https://perishablepress.com/current/index-zdf.html"></iframe> -->
							</div>
						</div>
					</div>
				</div>
			</div>
			<div id="mm-credit-info">
				<a target="_blank" href="<?php echo $zdf_homeurl; ?>" title="<?php echo $zdf_plugin; ?> Homepage"><?php echo $zdf_plugin; ?></a> <?php _e('by', 'zdf'); ?>
				<a target="_blank" href="https://twitter.com/perishable" title="Jeff Starr on Twitter">Jeff Starr</a> @
				<a target="_blank" href="http://monzilla.biz/" title="Obsessive Web Design &amp; Development">Monzilla Media</a>
			</div>
		</form>
	</div>
	<script type="text/javascript">
		jQuery(document).ready(function(){
			// toggle panels
			jQuery('.default-hidden').hide();
			jQuery('#mm-panel-toggle a').click(function(){
				jQuery('.toggle').slideToggle(300);
				return false;
			});
			jQuery('h2').click(function(){
				jQuery(this).next().slideToggle(300);
			});
			jQuery('#mm-panel-primary-link').click(function(){
				jQuery('.toggle').hide();
				jQuery('#mm-panel-primary .toggle').slideToggle(300);
				return true;
			});
			jQuery('#mm-panel-secondary-link').click(function(){
				jQuery('.toggle').hide();
				jQuery('#mm-panel-secondary .toggle').slideToggle(300);
				return true;
			});
			jQuery('#mm-restore-settings-link').click(function(){
				jQuery('.toggle').hide();
				jQuery('#mm-restore-settings .toggle').slideToggle(300);
				return true;
			});
			// prevent accidents
			if(!jQuery("#mm_restore_defaults").is(":checked")){
				jQuery('#mm_restore_defaults').click(function(event){
					var r = confirm("<?php _e('Are you sure you want to restore all default options? (this action cannot be undone)', 'zdf'); ?>");
					if (r == true){
						jQuery("#mm_restore_defaults").attr('checked', true);
					} else {
						jQuery("#mm_restore_defaults").attr('checked', false);
					}
				});
			}
		});
	</script>

<?php }
