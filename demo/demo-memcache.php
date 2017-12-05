<?php
/**
 * demo-memcache.php for cisession.
 * @author SamWu
 * @date 2017/12/4 17:58
 * @copyright istimer.com
 */

include '../vendor/autoload.php';

$config = [
	'sessionDriver' => 'memcached',
	'sessionCookieName' => 'memcache_session',
	'sessionExpiration' => 7200,
	//'sessionSavePath' => null,
	//'sessionSavePath' => 'localhost:11211:5,192.0.2.1:11211:1',
	'sessionSavePath' => 'localhost:11211',
	'sessionMatchIP' => false,
	'sessionTimeToUpdate' => 300,
	'sessionRegenerateDestroy' => false,
	'cookiePrefix' => '',
	'cookieDomain' => '',
	'cookiePath' => '/',
	'cookieSecure' => false,
	'cookieHTTPOnly' => false,
];

$session = \Opdss\Cisession\Session::getInstance($config);

$session->set('test', 'session_memcache');
$session->setFlashdata('flash', 'session_memcache_flash');
$session->setTempdata('temp', 'session_memcache_temp', 300);
var_dump($session->get('test'));
var_dump($session->get('flash'));
var_dump($session->get('temp'));