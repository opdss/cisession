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
	'sessionSavePath' => './session',
	'sessionMatchIP' => false,
	'sessionTimeToUpdate' => 300,
	//'sessionRegenerateDestroy' => false,
	'sessionRegenerateDestroy' => true,
	//'cookiePrefix' => '',
	'cookiePrefix' => 'file',
	'cookieDomain' => '',
	'cookiePath' => '/',
	'cookieSecure' => false,
	'cookieHTTPOnly' => false,
];

$session = \Opdss\Cisession\Session::getInstance($config, 'file');

$session->set('test', 'session_file');
$session->setFlashdata('flash', 'session_file_flash');
$session->setTempdata('temp', 'session_file_temp', 300);
var_dump($session->get('test'));
var_dump($session->get('flash'));
var_dump(1111111111);
var_dump(\Opdss\Cisession\Session::getInstance($config, 'file')->get('test'));
var_dump(\Opdss\Cisession\Session::getInstance($config, 'file')->get('temp'));