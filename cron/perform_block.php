<?php
/**
 * @created      10.11.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

/**
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000 $terfblocker
 * @var \chillerlan\Database\Database $db
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000Options $options
 * @var \Psr\Log\LoggerInterface $logger
 */

preg_match('/^(?<screen_name>[a-z_\d]{1,20})$/i', $argv[1] ?? '', $matches);

if(empty($matches)){
	exit(1);
}

require_once __DIR__.'/common.php';

$terfblocker->setTokenFromScreenName($matches['screen_name']);
$terfblocker->block();
