<?php
	ini_set("memory_limit", "2000M");
	require_once(dirname(__FILE__) . '/../../../wp-config.php');
	global $wpdb;

	//Use this to reprocess older posts

	//$start_days = 630;
	//$end_days = 1200;
	//$day_step = 10;

	//for ($i = $start_days; $i <= $end_days - $day_step; $i += $day_step) {
	//	$item_count = 0;
	//	$trys = 0;
	//	while ($item_count < $day_step*10 && $trys <= 5) {
	//		echo 'Current start day: ' . $i . "<br>\n";
	//		$date_max = strtotime('00:00:00 10/22/2010') - $i*86400 - 60;
	//		$date_min = $date_max - $day_step*86400;
	//		$wp_post = new WPOMatic();
	//		$item_count = $wp_post->reprocessItems($date_min, $date_max);
	//		unset($wp_post);
	//		$trys++;
	//	}
	//}

	//Use this to reprocess specific posts
	$wp_post = new WPOMatic();
	$wp_post->processArXivItem('1205.6995');
	unset($wp_post);
?>

