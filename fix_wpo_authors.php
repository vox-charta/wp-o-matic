<?php
	require_once(dirname(__FILE__) . '/../../../wp-config.php');
	global $wpdb;

	$posts = $wpdb->get_results("SELECT ID, post_content FROM wp_posts WHERE post_author = '35'");
	$link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD)
		OR die(mysql_error());
	foreach($posts as $post) {
		$parts = explode('ArXiv #:', $post->post_content);
		$authors = mysql_real_escape_string($parts[0]);
		$content_parts = explode('</a>)<br><br><br><br>', $parts[1]);
		if (count($content_parts) == 1) {
			$content_parts = explode('</a>)<br><br>', $parts[1]);
		}
		$content = mysql_real_escape_string($content_parts[1]);
		$wpdb->query("UPDATE wp_posts SET post_content='{$content}' WHERE ID='{$post->ID}'");
		$wpdb->query("UPDATE wp_postmeta SET meta_value = '{$authors}' WHERE meta_key = 'wpo_authors' AND post_id = '{$post->ID}'");
		echo $post->ID . '<br>';
	}
?>
