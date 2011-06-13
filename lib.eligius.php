<?php
/*Copyright (C) 2011 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Artefact2\EligiusStats;

const VERSION = '2.0';

const T_BALANCE_CURRENT_BLOCK = 'balance_current_block';
const T_BALANCE_UNPAID_REWARD = 'balance_unpaid';
const T_BALANCE_ALREADY_PAID = 'already_paid';
const T_HASHRATE_INDIVIDUAL = 'hashrate';
const T_HASHRATE_POOL = 'hashrate_total';

const HASHRATE_AVERAGE = 10800; // Show a 3-hour average for individual stats
const HASHRATE_AVERAGE_HR = '3 hour'; // Show a 3-hour average for individual stats
const HASHRATE_AVERAGE_SHORT = 900;
const HASHRATE_AVERAGE_SHORT_HR = '15 minute';
const HASHRATE_PERIOD = 900; // Use a 15-minute average to compute the hashrate
const HASHRATE_PERIOD_LONG = 3600;
const HASHRATE_LAG = 180; // Use a 3-minute delay, to cope with MySQL replication lag

const TIMESPAN_SHORT = 604800; // Store at most 7 days of data for short-lived graphs
const TIMESPAN_LONG = 2678400; // Store at most 31 days of data for long-lived graphs

const S_UNKNOWN = -1;
const S_WORKING = 0;
const S_INVALID_WORK = 1;
const S_NETWORK_PROBLEM = 2;

const STATUS_TIMEOUT = 20;
const STATUS_FILE_NAME = 'pool_status.json';

const NUMBER_OF_RECENT_BLOCKS = 7;
const NUMBER_OF_TOP_CONTRIBUTORS = 10;

const API_HASHRATE_DELAY = 600; /* The hashrate.txt files seem to be updated every ten minutes. */

const RECENT_BLOCKS = 10;
const OLD_BLOCKS = 250;

require __DIR__.'/lib.util.php';
require __DIR__.'/lib.cache.php';
if(!defined('NO_SQL') || !NO_SQL) require __DIR__.'/inc.sql.php';

/**
 * Update the Pool's hashrate.
 * @param string $serverName the name of the server (should coincide with the "server" column in MySQL)
 * @return bool true if the operation succeeded.
 */
function updatePoolHashrate($serverName) {
	$end = time() - HASHRATE_LAG;
	$start = $end - HASHRATE_PERIOD_LONG;
	$hashrate = mysql_query("
		SELECT ((COUNT(*) * POW(2, 32)) / ".HASHRATE_PERIOD_LONG.")
		FROM shares
		WHERE our_result <> 'N'
			AND server = '$serverName'
			AND time BETWEEN $start AND $end
	");
	$hashrate = mysql_result($hashrate, 0, 0);

	return updateData(T_HASHRATE_POOL, $serverName, null, $hashrate, TIMESPAN_LONG);
}

/**
 * Update the hashrate data for an address on one server.
 * @param string $serverName the name of the server (should coincide with the "server" column in MySQL)
 * @param string $address the address to process
 * @param array $hashrates the array returned by getIndividualHashrates()
 * @return bool true if the operation succeeded.
 */
function updateIndividualHashrate($serverName, $address, $hashrates) {
	$H = isset($hashrates[$address]) ? $hashrates[$address] : 0.0;
	return updateData(T_HASHRATE_INDIVIDUAL, $serverName.'_'.$address, null, $H, TIMESPAN_SHORT);
}

/**
 * Update all the balances of an address.
 * @param string $serverName the name of the server.
 * @param string $address the address to update.
 * @param string|float $current_block an estimation, in BTC, of the reward for this address with the current block.
 * @param string|float $unpaid the amount, in BTC, that is not yet paid to this address
 * @param string|float $paid the amount, in BTC, already paid to this address (ever)
 * @return bool true if the operations succeeded, false otherwise
 */
function updateBalance($serverName, $address, $current_block, $unpaid, $paid) {
	$identifier = $serverName.'_'.$address;
	$ret = true;
	$ret = $ret && updateData(T_BALANCE_CURRENT_BLOCK, $identifier, null, $current_block, TIMESPAN_SHORT);
	$ret = $ret && updateData(T_BALANCE_UNPAID_REWARD, $identifier, null, $unpaid, TIMESPAN_SHORT);
	$ret = $ret && updateData(T_BALANCE_ALREADY_PAID, $identifier, null, $paid, TIMESPAN_SHORT);
	return $ret;
}

/**
 * Update all the hashrates for one server.
 * @param string $serverName the name of the server (should coincide with the "server" column in MySQL)
 * @param string $apiRoot the API root of the server.
 * @param callback $tickCallback an optional callback that will be called after every address processed.
 * @return array an array containing the number of correctly processed addresses, and failed attempts.
 */
function updateAllIndividualHashratesOnServer($serverName, $apiRoot, $tickCallback = null) {
	$ok = 0;
	$failed = 0;

	$hashrates = getIndividualHashrates($serverName);
	$addresses = getActiveAddresses($apiRoot);
	foreach($addresses as $address) {
		if(updateIndividualHashrate($serverName, $address, $hashrates)) $ok++;
		else $failed++;

		if($tickCallback !== null) call_user_func($tickCallback, $address);
	}

	if($failed !== 0) {
		trigger_error('Could not update '.$failed.' hashrates of individual addresses.', E_USER_NOTICE);
	}

	return array($ok, $failed);
}

/**
 * Update the balances of all addresses of one server.
 * @param string $serverName the name of the server.
 * @param string $apiRoot the API root of the server.
 * @param callback $tickCallback a function to call after every address processed.
 * @return array|bool false if an error happened, or an array containing the number of correctly processed addresses
 * and failed attempts.
 */
function updateAllBalancesOnServer($serverName, $apiRoot, $tickCallback = null) {
	$ok = 0;
	$failed = 0;

	$balances = getBalanceData($apiRoot);
	$latest = json_decode_safe($apiRoot.'/blocks/latest.json');

	foreach($balances as $address => $data) {
		$paid = isset($latest[$address]['everpaid']) ? satoshiToBTC($latest[$address]['everpaid']) : 0.0;
		$unpaid = isset($latest[$address]['balance']) ? satoshiToBTC($latest[$address]['balance']) : 0.0;
		$current = satoshiToBTC(bcsub($balances[$address]['balance'], isset($latest[$address]['balance']) ? $latest[$address]['balance'] : 0, 0));
		if(updateBalance($serverName, $address, $current, $unpaid, $paid)) $ok++;
		else $failed++;

		if($tickCallback !== null) call_user_func($tickCallback, $address);
	}

	if($failed !== 0) {
		trigger_error('Could not update '.$failed.' balances.', E_USER_NOTICE);
	}

	return array($ok, $failed);
}

/**
 * Checks the status of a server, and writes the results in a JSON file.
 * @param string $serverName the name of the server
 * @param string $address the address of the server
 * @param int|string $port the port to connect to
 * @return bool true if the operation succeeded (ie, the status was updated successfully, regardless of its status)
 */
function updateServerStatus($serverName, $address, $port) {
	$f = __DIR__.'/'.DATA_RELATIVE_ROOT.'/'.STATUS_FILE_NAME;
	if(!file_exists($f)) {
		$status = array();
	} else {
		$status = json_decode_safe($f);
	}

	$s = S_UNKNOWN;
	$lag = -1.0;
	getServerStatus($address, $port, STATUS_TIMEOUT, $s, $lag);

	$now = time();
	$status[$serverName]['latency'] = $lag;
	$status[$serverName]['last-updated'] = $now;
	if(!isset($status[$serverName]['status']) || $status[$serverName]['status'] !== $s) {
		$status[$serverName]['status'] = $s;
		$status[$serverName]['since'] = $now;
	}

	return json_encode_safe($status, $f);
}

/**
 * Cache the contributors with the highest average hashrate, in average.
 * @param int $numContributors how many top contributors to fetch
 * @return bool true if the operation was successful.
 */
function updateTopContributors($numContributors = NUMBER_OF_TOP_CONTRIBUTORS) {
	$end = time() - HASHRATE_LAG;
	$start = $end - HASHRATE_AVERAGE;

	$q = mysql_query("
		SELECT server, username AS address, ((COUNT(*) * POW(2, 32)) / ".HASHRATE_AVERAGE.") AS hashrate
		FROM shares
		WHERE time BETWEEN $start AND $end
			AND our_result <> 'N'
		GROUP BY username, server
		ORDER BY hashrate DESC LIMIT ".$numContributors
	);

	$top = array();
	while($t = mysql_fetch_assoc($q)) {
		$top[] = $t;
	}

	return cacheStore('top_contributors', $top);
}

/**
 * Cache a random address currently contributing on a server.
 * @param string $serverName the name of the server
 * @param string $apiRoot the API root for this server
 * @return bool true if the operation was successful.
 */
function updateRandomAddress($serverName, $apiRoot) {
	$addresses = getActiveAddresses($apiRoot);
	if($addresses === false) return false;
	if(count($addresses) == 0) return false;

	shuffle($addresses);
	$address = array_pop($addresses);
	return cacheStore('random_address_'.$serverName, $address);
}

/**
 * Cache the average hashrates of all the users.
 * @return bool true if the operation was successful.
 */
function updateAverageHashrates() {
	$end = time() - HASHRATE_LAG;
	$start = $end - HASHRATE_AVERAGE;
	
	$wStart = time() - HASHRATE_LAG - 60;
	$isWorking = mysql_query("
		SELECT COUNT(*) AS shares
		FROM shares
		WHERE our_result <> 'N'
			AND time >= $wStart
		LIMIT 1
	");
	$isWorking = mysql_result($isWorking, 0, 0);
	if($isWorking == 0) {
		$averages3h = array();
		$averages15min = array();
	} else {
		$q = mysql_query("
			SELECT username AS address, server, COUNT(*) AS shares
			FROM shares
			WHERE our_result <> 'N'
				AND time BETWEEN $start AND $end
			GROUP BY server, username
			ORDER BY time DESC
		");

		$averages3h = array();
		while($r = mysql_fetch_assoc($q)) {
			$rate = floatval(bcdiv(bcmul($r['shares'], bcpow(2, 32)), HASHRATE_AVERAGE));
			$averages3h[$r['server']][$r['address']] = array($r['shares'], $rate);
		}

		$start = $end - HASHRATE_AVERAGE_SHORT;
		$q = mysql_query("
			SELECT username AS address, server, COUNT(*) AS shares
			FROM shares
			WHERE our_result <> 'N'
				AND time BETWEEN $start AND $end
			GROUP BY server, username
			ORDER BY time DESC
		");

		$averages15min = array();
		while($r = mysql_fetch_assoc($q)) {
			$rate = floatval(bcdiv(bcmul($r['shares'], bcpow(2, 32)), HASHRATE_AVERAGE_SHORT));
			$averages15min[$r['server']][$r['address']] = array($r['shares'], $rate);
		}
	}

	$a = cacheStore('average_hashrates_long', $averages3h);
	$b = cacheStore('average_hashrates_short', $averages15min);

	return $a && $b;
}

function updateBlocks($server, $apiRoot) {
	$recent = cacheFetch('blocks_recent_'.$server, $success0);
	$old = cacheFetch('blocks_old_'.$server, $success1);
	if(!$success0) {
		$recent = array();
	}
	if(!$success1) {
		$old = array();
	}

	$gBlocks = glob($apiRoot.'/blocks/0000*.json');
	$blocks = array();
	foreach($gBlocks as $block) {
		$blocks[$block] = filemtime($block);
	}
	arsort($blocks);

	$foundAt = array_values($blocks);
	$blocks = array_keys($blocks);

	$c = count($blocks);
	$newBlocks = array();

	for($i = 0; $i < ($c - 1); ++$i) {
		$blk = pathinfo($blocks[$i], PATHINFO_FILENAME);
		if(count($recent) > 0 && $recent[0]['when'] >= $foundAt[$i]) break;

		$bData = array();

		$bData['hash'] = $blk;
		$bData['when'] = $foundAt[$i];
		$bData['duration'] = $foundAt[$i] - $foundAt[$i + 1];

		$start = $foundAt[$i + 1];
		$end = $foundAt[$i];
		$q = mysql_query("
			SELECT username, COUNT(*) AS fshares
			FROM shares
			WHERE our_result <> 'N'
				AND server = '$server'
				AND time BETWEEN $start AND $end
			GROUP BY username
		");

		$bData['shares_total'] = 0;
		while($r = mysql_fetch_assoc($q)) {
			$bData['shares_total'] += $r['fshares'];
			$bData['shares'][$r['username']] = $r['fshares'];
		}

		$json = json_decode(file_get_contents($blocks[$i]), true);
		foreach($json as $address => $row) {
			if(isset($row['earned'])) {
				$bData['rewards'][$address] = satoshiToBTC($row['earned']);
			}
		}

		$newBlocks[] = $bData;
	}

	$recent = array_merge($newBlocks, $recent);

	// Transfer overflowing blocks from $recent to $old
	$c = count($recent);
	for($i = RECENT_BLOCKS; $i < $c; ++$i) {
		array_unshift($old, array_pop($recent));
	}

	// Throw away very old blocks
	$c = count($old);
	for($i = OLD_BLOCKS; $i < $c; ++$i) {
		array_pop($old);
	}

	return cacheStore('blocks_recent_'.$server, $recent) && cacheStore('blocks_old_'.$server, $old);
}

/**
 * Get an associative array of "instant" hashrates for the addresses that submitted shares recently.
 * This is a costly operation !
 * @param string $serverName the name of the server (should coincide with the "server" column in MySQL)
 * @return array an array (address => instant hashrate)
 */
function getIndividualHashrates($serverName) {
	$end = time() - HASHRATE_LAG;
	$start = $end - HASHRATE_PERIOD;
	$q = mysql_query("
		SELECT username AS address, ((COUNT(*) * POW(2, 32)) / ".HASHRATE_PERIOD.") AS hashrate
		FROM shares
		WHERE our_result <> 'N'
			AND server = '$serverName'
			AND time BETWEEN $start AND $end
		GROUP BY username
	");

	$result = array();
	while($r = mysql_fetch_assoc($q)) {
		$result[$r['address']] = $r['hashrate'];
	}

	return $result;
}

/**
 * Get the balances of addresses on a given server. This call is cached.
 * @param string $apiRoot the API root of the server.
 * @return bool|array false if the operation failed, an array (address => balance data) otherwise.
 */
function getBalanceData($apiRoot) {
	static $cache = null;
	if($cache === null) $cache = array();
	if(isset($cache[$apiRoot])) return $cache[$apiRoot];

	$balances = json_decode_safe($apiRoot.'/balances.json');

	return $cache[$apiRoot] = $balances;
}

/**
 * Get an array of addresses contributing on a given server.
 * @param string $apiRoot the API root of the server.
 * @return bool|array an array of active addresses.
 */
function getActiveAddresses($apiRoot) {
	$b = getBalanceData($apiRoot);
	if(!is_array($b)) return false;
	return array_keys($b);
}

/**
 * Issue a getwork to a server to check if it is working as expected.
 * @param string $server the server address.
 * @param string|int $port the port to connect to
 * @param int $timeout for how long should we try to connect to the server before failing ?
 * @param int $status the status of the server, is one of the S_ constants.
 * @param float $latency the latency, in seconds, to issue a getwork. Only valid if true is returned.
 * @return bool true if the server is working correctly
 */
function getServerStatus($server, $port, $timeout, &$status, &$latency) {
	$body = json_encode(array(
		"method" => "getwork",
		"params" => array(),
		"id" => 42
	));

	$c = curl_init();
	curl_setopt($c, CURLOPT_POST, true);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($c, CURLOPT_HEADER, true);
	curl_setopt($c, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($c, CURLOPT_POSTFIELDS, $body);
	curl_setopt($c, CURLOPT_URL, 'http://artefact2:test@'.$server.':'.$port.'/');
	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($c, CURLOPT_TIMEOUT, $timeout);

	$lag = microtime(true);
	$resp = curl_exec($c);
	$latency = microtime(true) - $lag;

	if(curl_error($c))  {
		$status = S_NETWORK_PROBLEM;
		curl_close($c);
		return false;
	}

	curl_close($c);

	if(strpos($resp, 'Content-Type: application/json') === false) {
		$status = S_INVALID_WORK;
		return false;
	}

	$work = json_decode_safe(substr($resp, strpos($resp, '{') - 1), false);
	
	if(!isset($work['result']['data']) || strlen($work['result']['data']) !== 256 || $work['error'] !== null) {
		$status = S_INVALID_WORK;
		return false;
	}

	$status = S_WORKING;
	return true;
}

/**
 * Get the balance of an address on one server.
 * @param string $apiRoot the API root of the server.
 * @param string $address return the balance of this address
 * @return array|bool false if an error happened, or array($paid, $unpaid, $current).
 */
function getBalance($apiRoot, $address) {
	$balances = getBalanceData($apiRoot);
	$latest = json_decode_safe($apiRoot.'/blocks/latest.json');

	if(!isset($balances[$address]['balance'])) return false;

	$paid = isset($latest[$address]['everpaid']) ? satoshiToBTC($latest[$address]['everpaid']) : 0.0;
	$unpaid = isset($latest[$address]['balance']) ? satoshiToBTC($latest[$address]['balance']) : 0.0;
	$current = satoshiToBTC(bcsub($balances[$address]['balance'], isset($latest[$address]['balance']) ? $latest[$address]['balance'] : 0, 0));

	return array($paid, $unpaid, $current);
}
