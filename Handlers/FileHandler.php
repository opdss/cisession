<?php
/**
 * FileHandler.php for cisession.
 * @author SamWu
 * @date 2017/12/4 17:58
 * @copyright istimer.com
 */


namespace Opdss\Cisession\Handlers;

/**
 * Session handler using file system for storage
 */
class FileHandler extends BaseHandler implements \SessionHandlerInterface
{

	/**
	 * Where to save the session files to.
	 *
	 * @var string
	 */
	protected $savePath;

	/**
	 * The file handle
	 *
	 * @var resource
	 */
	protected $fileHandle;

	/**
	 * File Name
	 *
	 * @var resource
	 */
	protected $filePath;

	/**
	 * Whether this is a new file.
	 *
	 * @var bool
	 */
	protected $fileNew;

	//--------------------------------------------------------------------

	/**
	 * Constructor
	 * @param BaseConfig $config
	 */
	public function __construct($config)
	{
		parent::__construct($config);

		if (!empty($config['sessionSavePath'])) {
			$this->savePath = rtrim($config['sessionSavePath'], '/\\');
			ini_set('session.save_path', $config['sessionSavePath']);
		} else {
			$this->savePath = rtrim(ini_get('session.save_path'), '/\\');
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Open
	 *
	 * Sanitizes the save_path directory.
	 *
	 * @param    string $savePath Path to session files' directory
	 * @param    string $name Session cookie name
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function open($savePath, $name)
	{
		if (!is_dir($savePath)) {
			if (!mkdir($savePath, 0700, true)) {
				throw new \Exception("Session: Configured save path '" . $this->savePath .
					"' is not a directory, doesn't exist or cannot be created.");
			}
		} elseif (!is_writable($savePath)) {
			throw new \Exception("Session: Configured save path '" . $this->savePath .
				"' is not writable by the PHP process.");
		}

		$this->savePath = $savePath;
		$this->filePath = $this->savePath . '/'
			. $name // we'll use the session cookie name as a prefix to avoid collisions
			. ($this->matchIP ? md5($_SERVER['REMOTE_ADDR']) : '');

		return true;
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
		// This might seem weird, but PHP 5.6 introduced session_reset(),
		// which re-reads session data
		if ($this->fileHandle === null) {
			$this->fileNew = !file_exists($this->filePath . $sessionID);
			if (($this->fileHandle = fopen($this->filePath . $sessionID, 'c+b')) === false) {
				$this->log("error", "Session: Unable to open file '" . $this->filePath . $sessionID . "'.");

				return false;
			}

			if (flock($this->fileHandle, LOCK_EX) === false) {
				$this->log("error", "Session: Unable to obtain lock for file '" . $this->filePath . $sessionID . "'.");
				fclose($this->fileHandle);
				$this->fileHandle = null;

				return false;
			}

			// Needed by write() to detect session_regenerate_id() calls
			$this->sessionID = $sessionID;

			if ($this->fileNew) {
				chmod($this->filePath . $sessionID, 0600);
				$this->fingerprint = md5('');

				return '';
			}
		} else {
			rewind($this->fileHandle);
		}

		$session_data = '';
		for ($read = 0, $length = filesize($this->filePath . $sessionID); $read < $length; $read += strlen($buffer)) {
			if (($buffer = fread($this->fileHandle, $length - $read)) === false) {
				break;
			}

			$session_data .= $buffer;
		}

		$this->fingerprint = md5($session_data);

		return $session_data;
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
		// If the two IDs don't match, we have a session_regenerate_id() call
		// and we need to close the old handle and open a new one
		if ($sessionID !== $this->sessionID && (!$this->close() || $this->read($sessionID) === false)) {
			return false;
		}

		if (!is_resource($this->fileHandle)) {
			return false;
		} elseif ($this->fingerprint === md5($sessionData)) {
			return ($this->fileNew) ? true : touch($this->filePath . $sessionID);
		}

		if (!$this->fileNew) {
			ftruncate($this->fileHandle, 0);
			rewind($this->fileHandle);
		}

		if (($length = strlen($sessionData)) > 0) {
			for ($written = 0; $written < $length; $written += $result) {
				if (($result = fwrite($this->fileHandle, substr($sessionData, $written))) === false) {
					break;
				}
			}

			if (!is_int($result)) {
				$this->fingerprint = md5(substr($sessionData, 0, $written));
				$this->log("error", 'Session: Unable to write data.');

				return false;
			}
		}

		$this->fingerprint = md5($sessionData);

		return true;
	}

	//--------------------------------------------------------------------

	/**
	 * Close
	 *
	 * Releases locks and closes file descriptor.
	 *
	 * @return    bool
	 */
	public function close()
	{
		if (is_resource($this->fileHandle)) {
			flock($this->fileHandle, LOCK_UN);
			fclose($this->fileHandle);

			$this->fileHandle = $this->fileNew = $this->sessionID = null;

			return true;
		}

		return true;
	}

	//--------------------------------------------------------------------

	/**
	 * Destroy
	 *
	 * Destroys the current session.
	 *
	 * @param    string $session_id Session ID
	 *
	 * @return    bool
	 */
	public function destroy($session_id)
	{
		if ($this->close()) {
			return file_exists($this->filePath . $session_id) ? (unlink($this->filePath . $session_id) && $this->destroyCookie()) : true;
		} elseif ($this->filePath !== null) {
			clearstatcache();

			return file_exists($this->filePath . $session_id) ? (unlink($this->filePath . $session_id) && $this->destroyCookie()) : true;
		}

		return false;
	}

	//--------------------------------------------------------------------

	/**
	 * Garbage Collector
	 *
	 * Deletes expired sessions
	 *
	 * @param    int $maxlifetime Maximum lifetime of sessions
	 *
	 * @return    bool
	 */
	public function gc($maxlifetime)
	{
		if (!is_dir($this->savePath) || ($directory = opendir($this->savePath)) === false) {
			$this->log("debug", "Session: Garbage collector couldn't list files under directory '" . $this->savePath . "'.");

			return false;
		}

		$ts = time() - $maxlifetime;

		$pattern = sprintf(
			'/^%s[0-9a-f]{%d}$/', preg_quote($this->cookieName, '/'), ($this->matchIP === true ? 72 : 40)
		);

		while (($file = readdir($directory)) !== false) {
			// If the filename doesn't match this pattern, it's either not a session file or is not ours
			if (!preg_match($pattern, $file) || !is_file($this->savePath . '/' . $file) || ($mtime = filemtime($this->savePath . '/' . $file)) === false || $mtime > $ts
			) {
				continue;
			}

			unlink($this->savePath . '/' . $file);
		}

		closedir($directory);

		return true;
	}

	//--------------------------------------------------------------------
}
