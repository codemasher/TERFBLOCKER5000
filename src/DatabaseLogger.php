<?php
/**
 * Class DatabaseLogger
 *
 * @created      14.11.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

namespace codemasher\TERFBLOCKER5000;

use chillerlan\Database\Database;
use chillerlan\Settings\SettingsContainerAbstract;
use chillerlan\Settings\SettingsContainerInterface;
use Exception;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use function array_key_exists;
use function is_string;
use function json_encode;
use function strtolower;
use function time;
use function var_dump;

/**
 *
 */
class DatabaseLogger extends AbstractLogger{

	protected const E_NONE      = 0x00;
	protected const E_DEBUG     = 0x01;
	protected const E_INFO      = 0x02;
	protected const E_NOTICE    = 0x04;
	protected const E_WARNING   = 0x08;
	protected const E_ERROR     = 0x10;
	protected const E_CRITICAL  = 0x20;
	protected const E_ALERT     = 0x40;
	protected const E_EMERGENCY = 0x80;

	protected const LEVELS = [
		'none'              => self::E_NONE,
		LogLevel::DEBUG     => self::E_DEBUG,
		LogLevel::INFO      => self::E_INFO,
		LogLevel::NOTICE    => self::E_NOTICE,
		LogLevel::WARNING   => self::E_WARNING,
		LogLevel::ERROR     => self::E_ERROR,
		LogLevel::CRITICAL  => self::E_CRITICAL,
		LogLevel::ALERT     => self::E_ALERT,
		LogLevel::EMERGENCY => self::E_EMERGENCY,
	];

	protected SettingsContainerInterface $options;
	protected Database $db;
	protected int $loglevel = self::E_NONE;

	/**
	 *
	 */
	public function __construct(SettingsContainerInterface $options, Database $db){
		$this->options = $options;
		$this->db      = $db;

		$this->setLoglevel($this->options->loglevel);
		$this->db->connect();
	}

	/**
	 * @throws \Exception
	 */
	public function setLoglevel(string $level):DatabaseLogger{
		$level = strtolower($level);

		if(!isset($this::LEVELS[$level])){
			throw new Exception('invalid loglevel');
		}

		$this->loglevel = $this::LEVELS[$level];

		return $this;
	}

	/**
	 * @inheritDoc
	 * @throws \Exception
	 */
	public function log($level, $message, array $context = []):void{

		if(!is_string($level) || !isset($this::LEVELS[$level])){
			throw new Exception('invalid loglevel');
		}

		if(!$this->isHandling($level)){
			return;
		}

		// TODO: $context, tests
		$this->db->insert
			->into($this->options->table_log)
			->values([
				'level'   => $level,
				'message' => $message,
				'context' => json_encode($context),
				'time'    => time(),
			])
			->query()
		;
	}

	/**
	 *
	 */
	protected function isHandling(string $level):bool{
		return $this->loglevel !== self::E_NONE && $this::LEVELS[$level] >= $this->loglevel;
	}
}
