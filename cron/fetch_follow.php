<?php
/**
 * This cron job fetches the follower and friend (followed) IDs for the screen names
 * in the to-scan table and inserts them in the profile table.
 *
 * @created      03.11.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

/**
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000 $terfblocker
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000Options $options
 * @var \chillerlan\Database\Database $db
 * @var \Psr\Log\LoggerInterface $logger
 */
require_once __DIR__.'/common.php';

$terfblocker->setTokenFromScreenName($options->cronUser);

while(true){

	$count = $db->select
		->from([$options->table_scan_jobs])
		->where('finished', 0)
		->count();

	if($count === 0){
		$logger->info('0 rows found, going to sleep');

		sleep($options->sleepTimer);
		continue;
	}

	$logger->info($count.' rows left');

	$terfblocker->cronScanFollow();
}
