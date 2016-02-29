<?php
	ini_set("memory_limit", "2000M");
	require_once(dirname(__FILE__) . '/../../../wp-config.php');
	global $wpdb;

	$start_days = 0;
	$end_days = 7;
	$day_step = 7;

	for ($i = $start_days; $i <= $end_days - $day_step; $i += $day_step) {
		$item_count = 0;
		$trys = 0;
		while ($item_count < $day_step*10 && $trys <= 5) {
			echo 'Current start day: ' . $i . "<br>\n";
			$date_max = strtotime('00:17:30 02/17/2015') - $i*86400;
			$date_min = $date_max - $day_step*86400;
			$wp_post = new WPOMatic();
			$item_count = $wp_post->reprocessItems($date_min, $date_max, 0, 'hep-ph');
			unset($wp_post);
			$trys++;
		}
	}
?>

