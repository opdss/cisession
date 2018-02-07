<?php
/**
 * BaseHandler.php for cisession.
 * @author SamWu
 * @date 2017/12/4 17:58
 * @copyright istimer.com
 */

namespace Opdss\Cisession\Handlers;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Base class for session handling
 */
abstract class BaseHandler implements \SessionHandlerInterface
{

	use LoggerAwareTrait;

	/**
	 * The Data fingerprint.
	 *
	 * @var string
	 */
	protected $fingerprint;

	/**
	 * Lock placeholder.
	 *
	 * @var bool
	 */
	protected $lock = false;


	/**
	 * Cookie domain
	 *
	 * @var string
	 */
	protected $cookieDomain = '';

	/**
	 * Cookie path
	 * @var string
	 */
	protected $cookiePath = '/';

	/**
	 * Cookie secure?
	 *
	 * @var bool
	 */
	protected $cookieSecure = false;

	/**
	 * @var null
	 */
	protected $cookieHTTPOnly = null;

	/**
	 * Cookie name to use
	 * @var string
	 */
	protected $sessionCookieName;

	/**
	 * Match IP addresses for cookies?
	 *
	 * @var bool
	 */
	protected $sessionMatchIP = false;

	/**
	 * Current session ID
	 * @var string
	 */
	protected $sessionID;

	/**
	 * The 'save path' for the session
	 * varies between
	 * @var string|array
	 */
	protected $sessionSavePath;

	/**
	 * Number of seconds until the session ends.
	 *
	 * @var int
	 */
	protected $sessionExpiration = 7200;
	//--------------------------------------------------------------------

	/**
	 * Constructor
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		foreach (array(
					 'cookieDomain',
			         'cookiePath',
			         'cookieSecure',
			         'cookieHTTPOnly',
			         'sessionCookieName',
			         'sessionMatchIP',
			         'sessionSavePath',
		         ) as $key) {
			if (isset($config[$key])) {
				$this->$key = $config[$key];
			}
		}

		isset($config['sessionExpiration']) AND $config['sessionExpiration']>0 AND $this->sessionExpiration =  $config['sessionExpiration'];
	}

	//--------------------------------------------------------------------

	/**
	 * Internal method to force removal of a cookie by the client
	 * when session_destroy() is called.
	 *
	 * @return bool
	 */
	protected function destroyCookie()
	{
		return setcookie(
			$this->sessionCookieName, null, 1, $this->cookiePath, $this->cookieDomain, $this->cookieSecure, $this->cookieHTTPOnly
		);
	}

	//--------------------------------------------------------------------

	/**
	 * A dummy method allowing drivers with no locking functionality
	 * (databases other than PostgreSQL and MySQL) to act as if they
	 * do acquire a lock.
	 *
	 * @param string $sessionID
	 *
	 * @return bool
	 */
	protected function lockSession($sessionID)
	{
		$this->lock = true;
		return true;
	}

	//--------------------------------------------------------------------

	/**
	 * Releases the lock, if any.
	 *
	 * @return bool
	 */
	protected function releaseLock()
	{
		$this->lock = false;
		return true;
	}

	//--------------------------------------------------------------------

	/**
	 * Fail
	 *
	 * Drivers other than the 'files' one don't (need to) use the
	 * session.save_path INI setting, but that leads to confusing
	 * error messages emitted by PHP when open() or write() fail,
	 * as the message contains session.save_path ...
	 * To work around the problem, the drivers will call this method
	 * so that the INI is set just in time for the error message to
	 * be properly generated.
	 *
	 * @return    mixed
	 */
	protected function fail()
	{
		ini_set('session.save_path', $this->sessionSavePath);
		return false;
	}

	/**
	 * 日志记录
	 * @param $type
	 * @param $message
	 * @param array $context
	 * @return mixed
	 */
	protected function log($type, $message, $context = array())
	{
		if ($this->logger && ($this->logger instanceof LoggerInterface)) {
			$this->logger->$type($message, $context);
		}
	}
}
