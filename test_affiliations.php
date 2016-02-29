<?php

	require_once(dirname(__FILE__) . '/../../../wp-config.php');
	global $wpdb;

	$has_aff = 0;
	$max_counts = 0;
	$votes_authors = $wpdb->get_results("SELECT * FROM wp_votes_authors ORDER BY aliases");
	foreach ($votes_authors as $votes_author) {
		$term = get_term($votes_author->term, 'post_author');
		if ($votes_author->affiliations !== '') {
			$has_aff++;
		} else continue;
		$affils = array_filter(explode("|", $votes_author->affiliations));
		$counts = array_filter(explode(",", $votes_author->affilcounts));
		$aliases = array_filter(array_unique(explode(",", $votes_author->aliases)));
		array_multisort($counts, $affils);
		if ($counts[0] > $max_counts) $max_counts = $counts[0];
		echo $term->name;
		if (count($aliases) > 0) echo ' [' .implode(", ", $aliases). '] ';
		if (count($counts) > 0) echo ', ' . stripslashes($affils[0]) . ' (' . $counts[0] . ' counts)';
		echo '<br>';
	}
	echo round((double) $has_aff/count($votes_authors),4)*100 . '% of authors have affiliation data<br>';
	echo 'Most counts: ' . $max_counts;
?>

