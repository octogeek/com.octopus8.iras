<?php
/**
 * Plugin Name: IRAS plugin
 * Plugin URI: http://www.octopus8.com
 * Description: IRAS WordPress plugin for Octopus8.
 * Version: 1.0
 * Author: Asliddin Oripov
 * Author Email: asliddin@octopus8.com
 */

require_once dirname(__FILE__) . '/vendor/autoload.php';
include_once("wp-config.php");
include_once("wp-includes/wp-db.php");

use Firebase\JWT\JWT;

function report_online()
{
	echo('report');
	global $wpdb;
    $result =  $wpdb->get_results( "SELECT * FROM civicrm_contribution WHERE 1", OBJECT );
	var_dump($wpdb->prefix);
	var_dump($result);
}

function report_offline($params)
{
	global $wpdb;
    $result =  $wpdb->get_results( "SELECT cd.description FROM civicrm_domain cd WHERE cd.contact_id=1 LIMIT 1", OBJECT );
	// var_dump($wpdb->prefix);
	// var_dump($result[0]->description);
	// echo('offline'.PHP_EOL);
	$csvData = [];
	$dataHead = [0, 7, date("Y"), 7, 0, $result[0]->description,null,null,null,null,null,null,null,null];
	array_push($csvData, $dataHead);
	
	// $result =  $wpdb->get_results( "SELECT cc.id, cc.external_identifier, cc.sort_name FROM civicrm_contact cc WHERE cc.external_identifier is not null", OBJECT );
	$result =  $wpdb->get_results( "SELECT * FROM civicrm_contribution contrib INNER JOIN civicrm_contact cont on cont.id = contrib.contact_id WHERE contrib.id NOT IN (SELECT ci.contribution_id FROM civicrm_iras ci)", OBJECT );
	$total = 0;
	$incer = 0;
	foreach($result as $val){
		$idType = paseUENNumber($val->external_identifier);
		// echo $idType.PHP_EOL;
		if($idType>0){
			// $contactDonation = $wpdb->get_results( "SELECT cc.id, cc.external_identifier, cc.sort_name FROM civicrm_contact cc WHERE cc.external_identifier is not null", OBJECT );
			$dataBody = [1, $idType, $val->external_identifier, str_replace(',', '', $val->sort_name), null, null, null, null, null, $val->total_amount, date("Ymd", $val->receive_date), null, 'O', 'Z'];
			
			array_push($csvData, $dataBody);

			$total+=$val->total_amount;
			$incer++;
			//$result =  $wpdb->get_results( "SELECT cc.id, cc.external_identifier FROM civicrm_contact cc WHERE cc.external_identifier is not null", OBJECT );
		}
	}
	var_dump($dataBody);
	$dataBottom = [2, $incer, $total,null,null,null,null,null,null,null,null,null,null,null];
	array_push($csvData, $dataBottom);

	// echo($csvData);
	// echo paseUENNumber('S1111111C').PHP_EOL;
	// echo paseUENNumber('F1111111C').PHP_EOL;
	// echo paseUENNumber('11111111C').PHP_EOL;
	// echo paseUENNumber('180011111C').PHP_EOL;
	// echo paseUENNumber('A1111111C').PHP_EOL;
	// echo paseUENNumber('111111111C').PHP_EOL;
	// echo paseUENNumber('T00SS1111C').PHP_EOL;

	//$filename = dirname(__FILE__).'/stock.csv';

	// open csv file for writing
	//$f = fopen($filename, 'w');
	app_iras_output_buffer();
	$f = fopen('php://memory', 'w'); 
	// if ($f === false) {
	// 	die('Error opening the file ' . $filename);
	// }
	
	// write each row at a time to a file
	foreach ($csvData as $row) {
		fputcsv($f, $row, "," , '\'', "\\" );
	}
	fseek($f, 0);
	header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="report.csv";');
    fpassthru($f);

	$genDate = date('Y-m-d H:i:s');
	foreach($result as $val){
		$result =  $wpdb->get_results( "INSERT INTO civicrm_iras VALUES ($val->id,'$genDate')", OBJECT );
	}
	// close the file
	// fclose($f);
	// wp_redirect();
}

function paseUENNumber($uen){
	$idTypes =["nric"=>1, "fin"=>2, "uenb"=>5, "uenl"=>6, "asgd"=>8, "itr"=>10, "ueno"=>35 ];
	//Uen number types
	// $nric = ['SNNNNNNNC', 'TNNNNNNNC'];
	// $fin = ['FNNNNNNNC', 'GNNNNNNNC'];
	// $uenb = ['NNNNNNNNC'];
	// $uenl = ['YYYYNNNNNC'];
	// $asgd = ['ANNNNNNNC'];
	// $itr = ['NNNNNNNNNC'];
	// $ueno = ['TYYPQNNNNC', 'SYYPQNNNNC', 'RYYPQNNNNC'];

	switch($uen){
		case ($uen[0]=='S' || $uen[0]=='T') && is_numeric(substr($uen, 1, 7)):
			return $idTypes['nric'];
		case ($uen[0]=='F' || $uen[0]=='G') && is_numeric(substr($uen, 1, 7)):
			return $idTypes['fin'];
		case (strlen($uen)<10 && is_numeric(substr($uen, 0, 8))):
			return $idTypes['uenb'];
		case (((int)substr($uen, 0, 4))>=1800 && ((int)substr($uen, 0, 4)) <= date("Y")) && is_numeric(substr($uen, 4, 5)):
			return $idTypes['uenl'];
		case ($uen[0]=='A' && is_numeric(substr($uen, 1, 7))):
			return $idTypes['asgd'];
		case (is_numeric(substr($uen, 0, 9))):
			return $idTypes['itr'];
		case (($uen[0]=='T' || $uen[0]=='S' || $uen[0]=='R') && is_numeric(substr($uen, 1, 2)) && !is_numeric(substr($uen, 3, 2)) && is_numeric(substr($uen, 5, 4))):
			return $idTypes['ueno'];
		default:
			return 0;			
	}
	
	echo($idTypes['nric']);
}

function iras_curl_post($url, $header, $body)
{

	$c_type = '';
	if (!is_null($header)) {
		foreach ($header as $item) {
			$row = explode(':', $item);
			if (strcmp(strtolower(trim($row[0])), 'content-type') == 0) {
				$c_type = trim($row[1]);
			}
		}
		switch ($c_type) {
			case 'application/x-www-form-urlencoded':
				$content_body = http_build_query($body);
				break;
			case 'application/json':
				$content_body = json_encode($body);
				break;
		}
	} else {
		$header = array();
	}

	$curlOptions = array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_FOLLOWLOCATION => TRUE,
		CURLOPT_VERBOSE => TRUE,
		CURLOPT_STDERR => $verbose = fopen('php://temp', 'rw+'),
		CURLOPT_FILETIME => TRUE,
		CURLOPT_POST => TRUE,
		CURLOPT_HTTPHEADER => $header,
		CURLOPT_POSTFIELDS => $content_body
	);
	$curl = curl_init();
	curl_setopt_array($curl, $curlOptions);
	$response = curl_exec($curl);
	curl_close($curl);

	return json_decode($response);
}

function add_iras_admin_page()
{
	add_menu_page('IRAS plugin', 'IRAS', 'manage_options', 'iras-page.php', 'iras_admin_page', plugins_url('/assets/og_image_mini.png',  __FILE__));
}

function iras_singpass_admin_page()
{
	require_once plugin_dir_path(__FILE__) . '/iras-page.php';
}

function iras_settings_link($links)
{
	$settins_link = '<a href="admin.php?page=iras-page.php">Settings</a>';
	array_push($links, $settins_link);
	return $links;
}

function iras_create_settings()
{
	$plugin_name = explode('/', plugin_basename(__FILE__))[0];
	register_setting("$plugin_name._settings", "token_url");
	register_setting("$plugin_name._settings", "callback_url");
	register_setting("$plugin_name._settings", "token_parser_url");
	register_setting("$plugin_name._settings", "singpass_client");
	register_setting("$plugin_name._settings", "jwk_endpoint");
	register_setting("$plugin_name._settings", "public_jwks");
	register_setting("$plugin_name._settings", "private_jwks");
	register_setting("$plugin_name._settings", "private_sig_key");
	register_setting("$plugin_name._settings", "private_enc_key");
}

function app_iras_output_buffer()
{
	ob_clean();
	ob_start();
}

$plugin_name = plugin_basename(__FILE__);
add_action('init', 'app_iras_output_buffer');
add_action('admin_init', 'iras_create_settings');
add_filter("plugin_action_links_$plugin_name", 'iras_settings_link');
add_action('admin_menu', 'add_iras_admin_page');

add_action('rest_api_init', function () {
	register_rest_route('iras/v1', '/report_offline', array(
		'methods' => 'GET',
		'callback' => 'report_offline',
	));
});

add_action('rest_api_init', function () {
	register_rest_route('iras/v1', '/report', array(
		'methods' => 'GET',
		'callback' => 'report_online',
	));
});

wp_register_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js');
wp_enqueue_script('bootstrap-js');
wp_register_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css');
wp_enqueue_style('bootstrap-css');


// SELECT * FROM civicrm_contribution contrib
// INNER JOIN civicrm_contact cont on cont.id = contrib.contact_id
// WHERE contrib.id NOT IN (SELECT ci.contribution_id FROM civicrm_iras ci) AND cont.external_identifier IS NOT NULL

// //http://localhost/iras/wp-json/iras/v1/report_file
// //http://localhost/iras/wp-json/myplugin/v1/author/gfsdg

// //http://localhost/iras/wp-json/iras/v1/report_online
// //http://localhost/iras/wp-json/iras/v1/report_offline

// /**
//  * Plugin Name: IRAS plugin
//  * Plugin URI: http://www.octopus8.com
//  * Description: Wordpress IRAS Donation rporting plugin
//  * Version: 1.0
//  * Author: Asliddin Oripov
//  * Author Email: asliddin@octopus8.com
//  */

// require_once dirname(__FILE__) . '/vendor/autoload.php';
// include_once("wp-config.php");
// include_once("wp-includes/wp-db.php");

// use Firebase\JWT\JWT;

// function report_online($params)
// {
// 	echo('report');
// 	// global $wpdb;
//     // $result =  $wpdb->get_results( "SELECT * FROM civicrm_contribution WHERE 1", OBJECT );
// 	// var_dump($wpdb->prefix);
// 	// var_dump($result);
// }

// function report_offline($params)
// {
// 	echo('report');
// 	// global $wpdb;
//     // $result =  $wpdb->get_results( "SELECT cd.description FROM civicrm_domain cd WHERE cd.contact_id = 1", OBJECT );
// 	// var_dump($wpdb->prefix);
// 	// var_dump($result);
// }

// function iras_curl_post($url, $header, $body)
// {
// 	$c_type = '';
// 	if (!is_null($header)) {
// 		foreach ($header as $item) {
// 			$row = explode(':', $item);
// 			if (strcmp(strtolower(trim($row[0])), 'content-type') == 0) {
// 				$c_type = trim($row[1]);
// 			}
// 		}
// 		switch ($c_type) {
// 			case 'application/x-www-form-urlencoded':
// 				$content_body = http_build_query($body);
// 				break;
// 			case 'application/json':
// 				$content_body = json_encode($body);
// 				break;
// 		}
// 	} else {
// 		$header = array();
// 	}

// 	$curlOptions = array(
// 		CURLOPT_URL => $url,
// 		CURLOPT_RETURNTRANSFER => TRUE,
// 		CURLOPT_FOLLOWLOCATION => TRUE,
// 		CURLOPT_VERBOSE => TRUE,
// 		CURLOPT_STDERR => $verbose = fopen('php://temp', 'rw+'),
// 		CURLOPT_FILETIME => TRUE,
// 		CURLOPT_POST => TRUE,
// 		CURLOPT_HTTPHEADER => $header,
// 		CURLOPT_POSTFIELDS => $content_body
// 	);
// 	$curl = curl_init();
// 	curl_setopt_array($curl, $curlOptions);
// 	$response = curl_exec($curl);
// 	curl_close($curl);

// 	return json_decode($response);
// }

// function iras_add_admin_page()
// {
// 	add_menu_page('IRAS plugin', 'IRAS', 'manage_options', 'rias-page.php', 'iras_admin_page', plugins_url('/assets/og_image_mini.png',  __FILE__));
// }

// function iras_admin_page()
// {
// 	require_once plugin_dir_path(__FILE__) . '/iras-page.php';
// }

// function iras_settings_link($links)
// {
// 	$settins_link = '<a href="admin.php?page=iras-page.php">Settings</a>';
// 	array_push($links, $settins_link);
// 	return $links;
// }

// function iras_create_settings()
// {
// 	$plugin_name = explode('/', plugin_basename(__FILE__))[0];
// 	register_setting("$plugin_name._settings", "token_url");
// 	register_setting("$plugin_name._settings", "callback_url");
// 	register_setting("$plugin_name._settings", "token_parser_url");
// 	register_setting("$plugin_name._settings", "iras_client");
// 	register_setting("$plugin_name._settings", "jwk_endpoint");
// 	register_setting("$plugin_name._settings", "public_jwks");
// 	register_setting("$plugin_name._settings", "private_jwks");
// 	register_setting("$plugin_name._settings", "private_sig_key");
// 	register_setting("$plugin_name._settings", "private_enc_key");
// }

// function iras_app_output_buffer()
// {
// 	ob_clean();
// 	ob_start();
// }

// $plugin_name = plugin_basename(__FILE__);
// add_action('init', 'iras_app_output_buffer');
// add_action('admin_init', 'iras_create_settings');
// add_filter("plugin_action_links_$plugin_name", 'settings_link');
// add_action('admin_menu', 'iras_add_admin_page');
// add_action('rest_api_init', function () {
// 	register_rest_route('iras/v1', '/report', array(
// 		'methods' => 'GET',
// 		'callback' => 'report_online',
// 	));
// });
// add_action('rest_api_init', function () {
// 	register_rest_route('iras/v1', '/report_file', array(
// 		'methods' => 'GET',
// 		'callback' => 'report_offline',
// 	));
// });

// wp_register_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js');
// wp_enqueue_script('bootstrap-js');
// wp_register_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css');
// wp_enqueue_style('bootstrap-css');

// //http://asliddin.socialservicesconnect.com/wp-json/iras/v1/report
// //http://localhost/singpass/wp-json/iras/v1/report
// //http://localhost/iras/wp-json/iras/v1/report_file
// //http://localhost/singpass/wp-json/myplugin/v1/author/gfsdg

// //http://localhost/iras/wp-json/iras/v1/report_offline