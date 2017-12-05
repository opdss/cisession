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
	 * @var bool
	 */
	protected $fingerprint;

	/**
	 * Lock placeholder.
	 *
	 * @var mixed
	 */
	protected $lock = false;

	/**
	 * Cookie prefix
	 *
	 * @var type
	 */
	protected $cookiePrefix = '';

	/**
	 * Cookie domain
	 *
	 * @var type
	 */
	protected $cookieDomain = '';

	/**
	 * Cookie path
	 * @var type
	 */
	protected $cookiePath = '/';

	/**
	 * Cookie secure?
	 *
	 * @var type
	 */
	protected $cookieSecure = false;

	/**
	 * Cookie name to use
	 * @var type
	 */
	protected $cookieName;

	/**
	 * Match IP addresses for cookies?
	 *
	 * @var type
	 */
	protected $matchIP = false;

	/**
	 * Current session ID
	 * @var type
	 */
	protected $sessionID;

	/**
	 * The 'save path' for the session
	 * varies between
	 * @var mixed
	 */
	protected $savePath;

	//--------------------------------------------------------------------

	/**
	 * Constructor
	 * @param BaseConfig $config
	 */
	public function __construct($config)
	{
		$this->cookiePrefix = $config['cookiePrefix'];
		$this->cookieDomain = $config['cookieDomain'];
		$this->cookiePath = $config['cookiePath'];
		$this->cookieSecure = $config['cookieSecure'];
		$this->cookieName = $config['sessionCookieName'];
		$this->matchIP = $config['sessionMatchIP'];
		$this->savePath = $config['sessionSavePath'];
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
			$this->cookieName, null, 1, $this->cookiePath, $this->cookieDomain, $this->cookieSecure, true
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
		ini_set('session.save_path', $this->savePath);
		return false;
	}

	protected function log($type, $message, $context = array())
	{
		echo $message.PHP_EOL;
		if ($this->logger && ($this->logger instanceof LoggerInterface)) {
			$this->logger->$type($message, $context);
		}
	}
}
