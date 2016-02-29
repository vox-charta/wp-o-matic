<?php
	require_once(dirname(__FILE__) . '/../../../wp-config.php');
	global $wpdb;

	$posts = $wpdb->get_results("SELECT ID, post_content FROM wp_posts WHERE post_author = '35' AND post_content != ''");
	$meta_ids = $wpdb->get_col("SELECT post_id FROM wp_postmeta WHERE meta_key = 'wpo_comments' OR meta_key = 'wpo_journal'");
	$link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD)
		OR die(mysql_error());
	foreach($posts as $i => $post) {
		$pc = str_replace('Comments: Comments: ', 'Comments: ', $post->post_content);
		$comments_pos = strpos($pc, 'Comments: ');
		if ($comments_pos === false) {
			$comments = '';
			$journal_pos = strpos($pc, 'Journal: ');
			if ($journal_pos === false) {
				$journal = '';
				$content = mysql_real_escape_string($pc);
			} else {
				$parts2 = explode('<br><br>', preg_replace('/\s{2,}/', ' ', substr($pc, $journal_pos + 9)));
				if (count($parts2) == 1) {
					echo 'Error, journal!';
					return;
				} else {
					$journal = mysql_real_escape_string(preg_replace('/\s{2,}/', ' ', $parts2[0]));
					$content = mysql_real_escape_string($parts2[1]);
				}
			}
		} else {
			$parts2 = explode('<br><br>', preg_replace('/\s{2,}/', ' ', substr($pc, $comments_pos + 10)));
			if (count($parts2) == 1) {
				echo 'Error!';
				return;
			} elseif (count($parts2) == 2) {
				$journal = '';
				$comments = mysql_real_escape_string($parts2[0]);
				$content = mysql_real_escape_string($parts2[1]);
			} else {
				$journal = mysql_real_escape_string(preg_replace('/\s{2,}/', ' ', substr($parts2[1], 9)));
				$comments = mysql_real_escape_string($parts2[0]);
				$content = mysql_real_escape_string($parts2[2]);
			}
		}

		if (array_search($post->ID, $meta_ids) === false) {
			$wpdb->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ('{$post->ID}', 'wpo_comments', '{$comments}')");
			$wpdb->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ('{$post->ID}', 'wpo_journal', '{$journal}')");
		} else {
			echo $post->ID . ' already has comments/journal' . "\n";
		}
		if ($comments == '' && $journal == '') continue;
		$wpdb->query("UPDATE wp_posts SET post_content='{$content}' WHERE ID='{$post->ID}'");
		echo 'Post: ' . $post->ID . "\n";
		echo 'Comments: ' . $comments . "\n";
		echo 'Journal: ' . $journal . "\n\n";
		//echo 'Content: ' . $content . "\n";
		//echo 'Old content: ' . $pc . "\n\n";
	}
?>
