<?php
require_once dirname ( __FILE__ ) . '/KLogger.php';
class LoggerConfiguration {
	const LOG_FILE = '/Library/WebServer/Documents/AppStoreWrapper/src/Application/ICCrawler/logs/';
	const LOG_PRIORITY = KLogger::DEBUG;
	const LOG_CONSOLE = true;
	private static $loggerInstance = null;
	private static $loggerErrorInstance = null;
	static function init($log_file, $log_console = self::LOG_CONSOLE) {
		if (self::$loggerInstance === null) {
			$log_file = self::LOG_FILE . "/{$log_file}";
			$dir = dirname ( $log_file );
			if (! file_exists ( $dir )) {
				if (! mkdir ( $dir, 0777, TRUE )) {
					return FALSE;
				}
			}
			self::$loggerInstance = new KLogger ( $log_file, LoggerConfiguration::LOG_PRIORITY, empty ( $log_console ) ? self::LOG_CONSOLE : $log_console );
			self::$loggerErrorInstance = new KLogger ( self::LOG_FILE . 'error.log', LoggerConfiguration::LOG_PRIORITY, empty ( $log_console ) ? self::LOG_CONSOLE : $log_console );
		}
		return self::$loggerInstance;
	}
	public static function logInfo($msg) {
		if (self::$loggerInstance) {
			self::$loggerInstance->LogInfo ( $msg );
		}
	}
	public static function logError($msg) {
		self::logInfo ( $msg );
		if (self::$loggerErrorInstance)
			self::$loggerErrorInstance->LogError ($msg);
	}
}
date_default_timezone_set ( 'Asia/Bangkok' );
$current_time = date ( 'YmdH' );
$current_month = date ( 'Ym' );
LoggerConfiguration::init ( "{$current_month}/{$current_time}.log" );