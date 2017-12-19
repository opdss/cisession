<?php
/**
 * demo.php for cisession.
 * @author SamWu
 * @date 2017/12/4 16:12
 * @copyright istimer.com
 */

include '../vendor/autoload.php';

$config = [
	'sessionDriver' => 'file',
	'sessionCookieName' => 'sesscookname',
	'sessionExpiration' => 7200,
	//'sessionSavePath' => null,
	//'sessionSavePath' => dirname(__FILE__).'/session',
	'sessionSavePath' => './session', //尽量填写绝对路径
	//'sessionMatchIP' => false,
	'sessionMatchIP' => true,
	'sessionTimeToUpdate' => 3,
	//'sessionRegenerateDestroy' => false,
	'sessionRegenerateDestroy' => true,
	//'cookiePrefix' => '',
	'cookiePrefix' => 'fsess',
	'cookieDomain' => '',
	'cookiePath' => '/',
	'cookieSecure' => false,
	'cookieHTTPOnly' => false,
];

$session = \Opdss\Cisession\Session::getInstance($config, 'file');
$session->start();
//$session->set('test', 'test_data_session_file');
//$session->set('test11111', '2222222222222');
var_dump($session->get());