<?php

	require_once(dirname(__FILE__) . '/../../../wp-config.php');
	global $wpdb;
	$posts = $wpdb->get_results("SELECT ID FROM wp_posts WHERE post_author = '35' AND ID = '97101'");
	$link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD)
		OR die(mysql_error());
	foreach ($posts as $post) {
		$authors = get_post_meta($post->ID, 'wpo_authors', true);
		echo $authors . "<br>";
		$new_authors = mysql_real_escape_string(utf8_decode($authors));
		echo $new_authors . "<br>";
		//$wpdb->query("UPDATE wp_postmeta SET meta_value = {$new_authors} WHERE meta_key = 'wpo_authors' AND post_id = '{$post->ID}'");
	}
?>
