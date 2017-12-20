<?php
/**
 * RedisHandler.php for cisession.
 * @author SamWu
 * @date 2017/12/4 17:58
 * @copyright istimer.com
 */


namespace Opdss\Cisession\Handlers;

/**
 * Session handler using Redis for persistence
 */
class RedisHandler extends BaseHandler implements \SessionHandlerInterface
{

	/**
	 * phpRedis instance
	 *
	 * @var    resource
	 */
	protected $redis;

	/**
	 * Key prefix
	 *
	 * @var    string
	 */
	protected $keyPrefix = 'cisession:';

	/**
	 * Lock key
	 *
	 * @var    string
	 */
	protected $lockKey;

	/**
	 * Key exists flag
	 *
	 * @var bool
	 */
	protected $keyExists = FALSE;


	//--------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @param BaseConfig $config
	 * @throws \Exception
	 */
	public function __construct($config)
	{
		parent::__construct($config);

		if (empty($this->sessionSavePath)) {
			throw new \Exception('Session: No Redis save path configured.');
		} elseif (preg_match('#(?:tcp://)?([^:?]+)(?:\:(\d+))?(\?.+)?#', $this->sessionSavePath, $matches)) {
			isset($matches[3]) OR $matches[3] = ''; // Just to avoid undefined index notices below

			$this->sessionSavePath = [
				'host' => $matches[1],
				'port' => empty($matches[2]) ? null : $matches[2],
				'password' => preg_match('#auth=([^\s&]+)#', $matches[3], $match) ? $match[1] : null,
				'database' => preg_match('#database=(\d+)#', $matches[3], $match) ? (int)$match[1] : null,
				'timeout' => preg_match('#timeout=(\d+\.\d+)#', $matches[3], $match) ? (float)$match[1] : null,
			];

			preg_match('#prefix=([^\s&]+)#', $matches[3], $match) && $this->keyPrefix = $match[1];
		} else {
			throw new \Exception('Session: Invalid Redis save path format: ' . $this->sessionSavePath);
		}

		if ($this->sessionMatchIP === true) {
			$this->keyPrefix .= $_SERVER['REMOTE_ADDR'] . ':';
		}

	}

	//--------------------------------------------------------------------

	/**
	 * Open
	 *
	 * Sanitizes save_path and initializes connection.
	 *
	 * @param    string $save_path Server path
	 * @param    string $name Session cookie name, unused
	 * @return    bool
	 */
	public function open($save_path, $name)
	{
		if (empty($this->sessionSavePath)) {
			return FALSE;
		}

		$redis = new \Redis();

		if (!$redis->connect($this->sessionSavePath['host'], $this->sessionSavePath['port'], $this->sessionSavePath['timeout'])) {
			$this->log("error", 'Session: Unable to connect to Redis with the configured settings.');
		} elseif (isset($this->sessionSavePath['password']) && !$redis->auth($this->sessionSavePath['password'])) {
			$this->log("error", 'Session: Unable to authenticate to Redis instance.');
		} elseif (isset($this->sessionSavePath['database']) && !$redis->select($this->sessionSavePath['database'])) {
			$this->log("error", 'Session: Unable to select Redis database with index ' . $this->sessionSavePath['database']);
		} else {
			$this->redis = $redis;
			return TRUE;
		}

		return FALSE;
	}

	//--------------------------------------------------------------------

	/**
	 * Read
	 *
	 * Reads session data and acquires a lock
	 *
	 * @param    string $sessionID Session ID
	 *
	 * @return    string    Serialized session data
	 */
	public function read($sessionID)
	{
		if (isset($this->redis) && $this->lockSession($sessionID)) {
			// Needed by write() to detect session_regenerate_id() calls
			$this->sessionID = $sessionID;

			$session_data = $this->redis->get($this->keyPrefix . $sessionID);
			is_string($session_data) ? $this->keyExists = TRUE : $session_data = '';

			$this->fingerprint = md5($session_data);
			return $session_data;
		}

		return FALSE;
	}

	//--------------------------------------------------------------------

	/**
	 * Write
	 *
	 * Writes (create / update) session data
	 *
	 * @param    string $sessionID Session ID
	 * @param    string $sessionData Serialized session data
	 *
	 * @return    bool
	 */
	public function write($sessionID, $sessionData)
	{
		if (!isset($this->redis)) {
			return FALSE;
		} // Was the ID regenerated?
		elseif ($sessionID !== $this->sessionID) {
			if (!$this->releaseLock() || !$this->lockSession($sessionID)) {
				return FALSE;
			}

			$this->keyExists = FALSE;
			$this->sessionID = $sessionID;
		}

		if (isset($this->lockKey)) {
			$this->redis->setTimeout($this->lockKey, 300);

			if ($this->fingerprint !== ($fingerprint = md5($sessionData)) || $this->keyExists === FALSE) {
				if ($this->redis->set($this->keyPrefix . $sessionID, $sessionData, $this->sessionExpiration)) {
					$this->fingerprint = $fingerprint;
					$this->keyExists = TRUE;
					return TRUE;
				}

				return FALSE;
			}

			return $this->redis->setTimeout($this->keyPrefix . $sessionID, $this->sessionExpiration);
		}

		return FALSE;
	}

	//--------------------------------------------------------------------

	/**
	 * Close
	 *
	 * Releases locks and closes connection.
	 *
	 * @return    bool
	 */
	public function close()
	{
		if (isset($this->redis)) {
			try {
				if ($this->redis->ping() === '+PONG') {
					isset($this->lockKey) && $this->redis->delete($this->lockKey);

					if (!$this->redis->close()) {
						return FALSE;
					}
				}
			} catch (\RedisException $e) {
				$this->log("error", 'Session: Got RedisException on close(): ' . $e->getMessage());
			}

			$this->redis = NULL;

			return TRUE;
		}

		return TRUE;
	}

	//--------------------------------------------------------------------

	/**
	 * Destroy
	 *
	 * Destroys the current session.
	 *
	 * @param    string $session_id Session ID
	 * @return    bool
	 */
	public function destroy($sessionID)
	{
		if (isset($this->redis, $this->lockKey)) {
			if (($result = $this->redis->delete($this->keyPrefix . $sessionID)) !== 1) {
				$this->log("debug", 'Session: Redis::delete() expected to return 1, got ' . var_export($result, TRUE) . ' instead.');
			}

			return $this->destroyCookie();
		}

		return FALSE;
	}

	//--------------------------------------------------------------------

	/**
	 * Garbage Collector
	 *
	 * Deletes expired sessions
	 *
	 * @param    int $maxlifetime Maximum lifetime of sessions
	 * @return    bool
	 */
	public function gc($maxlifetime)
	{
		// Not necessary, Redis takes care of that.
		return TRUE;
	}

	//--------------------------------------------------------------------

	/**
	 * Get lock
	 *
	 * Acquires an (emulated) lock.
	 *
	 * @param    string $sessionID Session ID
	 *
	 * @return    bool
	 */
	protected function lockSession($sessionID)
	{
		// PHP 7 reuses the SessionHandler object on regeneration,
		// so we need to check here if the lock key is for the
		// correct session ID.
		if ($this->lockKey === $this->keyPrefix . $sessionID . ':lock') {
			return $this->redis->setTimeout($this->lockKey, 300);
		}

		// 30 attempts to obtain a lock, in case another request already has it
		$lock_key = $this->keyPrefix . $sessionID . ':lock';
		$attempt = 0;

		do {
			if (($ttl = $this->redis->ttl($lock_key)) > 0) {
				sleep(1);
				continue;
			}

			if (!$this->redis->setex($lock_key, 300, time())) {
				$this->log("error", 'Session: Error while trying to obtain lock for ' . $this->keyPrefix . $sessionID);
				return FALSE;
			}

			$this->lockKey = $lock_key;
			break;
		} while (++$attempt < 30);

		if ($attempt === 30) {
			$this->log('error', 'Session: Unable to obtain lock for ' . $this->keyPrefix . $sessionID . ' after 30 attempts, aborting.');
			return FALSE;
		} elseif ($ttl === -1) {
			$this->log('debug', 'Session: Lock for ' . $this->keyPrefix . $sessionID . ' had no TTL, overriding.');
		}

		$this->lock = TRUE;
		return TRUE;
	}

	//--------------------------------------------------------------------

	/**
	 * Release lock
	 *
	 * Releases a previously acquired lock
	 *
	 * @return    bool
	 */
	protected function releaseLock()
	{
		if (isset($this->redis, $this->lockKey) && $this->lock) {
			if (!$this->redis->delete($this->lockKey)) {
				$this->log("error", 'Session: Error while trying to free lock for ' . $this->lockKey);
				return FALSE;
			}

			$this->lockKey = NULL;
			$this->lock = FALSE;
		}

		return TRUE;
	}

	//--------------------------------------------------------------------
}
