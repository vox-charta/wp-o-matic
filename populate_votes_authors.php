<?php

	require_once(dirname(__FILE__) . '/../../../wp-config.php');
	global $wpdb;
	$posts = $wpdb->get_results("SELECT ID FROM wp_posts WHERE post_author = '35' AND ID > '110900' ORDER BY ID DESC");
	$link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD)
		OR die(mysql_error());
	$c = 0;
	foreach ($posts as $post) {
		$authors = wp_get_post_terms($post->ID, 'post_author');
		foreach ($authors as $author) {
			if (count($wpdb->get_results("SELECT ID FROM wp_votes_authors WHERE term = '{$author->term_id}'")) < 1) {
				$name_str = strtolower(trim($author->name));
				while (strpos('  ', $name_str) !== false) $name_str = str_replace('  ', ' ', $name_str);
				$c++;
				$names = explode(' ', $name_str);
				if (count($names) >= 1) {
					$last_name = array_pop($names);
					$abbrevs = array();
					foreach ($names as $i => $name) {
						if (substr($name, -1) == '.') {
							$abbrevs[] = $name;
							$names[$i] = $abbrevs[$i];
						} else {
							$abbrevs[] = substr($name, 0, 1) . '.';
						}
					}
					$perm = array_transpose(array($names, $abbrevs));
					$perm = array_unique(array_cartesian($perm));
					foreach ($perm as $i => $p) {
						$perm[$i] .= ' ' . $last_name;
					}

					$aliases = $perm;
					if (count($names) > 1) {
						$aliases[] = $names[0] . ' ' . $last_name;
						$aliases[] = $abbrevs[0] . ' ' . $last_name;
					}
					foreach ($aliases as $i => $alias) {
						while (strpos('  ', $aliases[$i]) !== false) $aliases[$i] = str_replace('  ', ' ', $aliases[$i]);
						$aliases[$i] = trim($aliases[$i]);
					}
					$aliases = array_unique($aliases);
					$aliases_str = implode(",", $aliases);
				} else {
					$aliases_str = $name_str;
				}
				$wpdb->query("INSERT INTO wp_votes_authors (term, aliases) VALUES('{$author->term_id}', '{$aliases_str}');");
			}
		}
	}
?>
