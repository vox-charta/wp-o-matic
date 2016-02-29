<?php

	require_once(dirname(__FILE__) . '/../../../wp-config.php');
	global $wpdb;
	$posts = $wpdb->get_results("SELECT ID FROM wp_posts WHERE post_author = '35' ORDER BY ID DESC");
	$link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD)
		OR die(mysql_error());
	$c = 0;
	foreach ($posts as $post) {
		$authors = get_post_meta($post->ID, 'wpo_authors', true);
		if ($authors == '') continue;
		preg_match_all('/ \((.*?)\)/iu', $authors, $affiliations);
		if (count($affiliations[1]) > 0) {
			echo $authors . '<br>';
			print_r($affiliations[1]);
			echo '<br><br>';
			$c++;
		}
		if ($c == 50) return;
	}
?>
