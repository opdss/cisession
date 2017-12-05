<?php
/**
 * demo-memcache.php for cisession.
 * @author SamWu
 * @date 2017/12/4 17:58
 * @copyright istimer.com
 */

include '../vendor/autoload.php';

$config = [
	'sessionDriver' => 'redis',
	'sessionCookieName' => 'redis_session',
	'sessionExpiration' => 7200,
	//'sessionSavePath' => null,
	'sessionSavePath' => 'tcp://127.0.0.1:6379',
	'sessionMatchIP' => false,
	'sessionTimeToUpdate' => 300,
	'sessionRegenerateDestroy' => false,
	//'cookiePrefix' => '',
	'cookiePrefix' => 'redis',
	'cookieDomain' => '',
	'cookiePath' => '/',
	'cookieSecure' => false,
	'cookieHTTPOnly' => false,
];

$session = \Opdss\Cisession\Session::getInstance($config, 'redis');

$session->set('test', 'session_redis');
$session->setFlashdata('flash', 'session_redis_flash');
$session->setTempdata('temp', 'session_redis_temp', 300);
var_dump($session->get('test'));
var_dump($session->get('flash'));
var_dump($session->get('temp'));