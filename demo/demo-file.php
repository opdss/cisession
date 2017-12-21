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
	'sessionCookieName' => 'file_session',
	'sessionExpiration' => 7200,
	//'sessionSavePath' => null,
	'sessionSavePath' => dirname(__FILE__).'/session', //绝对路径
	'sessionMatchIP' => false,
	//'sessionMatchIP' => true,
	'sessionTimeToUpdate' => 300,
	//'sessionRegenerateDestroy' => false,
	'sessionRegenerateDestroy' => true,

	'cookieDomain' => '',
	'cookiePath' => '/',
	'cookieSecure' => true,
	'cookieHTTPOnly' => false,
];

$session = \Opdss\Cisession\Session::getInstance($config, 'file');
$session->start();
/*$session->set('test', 'test_data_session_file');
$session->set('test11111', '2222222222222');
$session->set(array('a'=>1, 'b'=>2));
$session->set('ab', array('a'=>1, 'b'=>2));
$session->setFlashdata('ff', 2323232);*/
var_dump($session->ab['b']);