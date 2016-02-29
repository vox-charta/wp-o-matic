<?php

/*
 * Plugin Name: WP-o-Matic-JFG
 * Description: Enables administrators to create posts automatically from RSS/Atom feeds.
 * Author: Guillermo Rauch and James Guillochon
 * Plugin URI: http://devthought.com/wp-o-matic-the-wordpress-rss-agreggator/
 * Version: 1.0RC4-6
 * =======================================================================
 
 Todo:
 
 - 'View campaign' view, with stats, thus getting rid of Tools tab
 - Bulk actions in campaign list
 - 'Time ago' for 'Last active' (on hover) in campaign list
 - Image thumbnailing option
 - More advanced post templates
 - Advanced filters
 - Import drag and drop to current campaigns.
 - Export extended OPML to save WP-o-Matic options
 - Proper commenting
 - Upgrading support
 - Plugins support
 
 Changelog:                         
 - 0.1beta
   WP-o-Matic released.
   
 - 0.2beta:           
   Fixed use of MagpieRSS legacy functions. 
   Updated cron code to check every twenty minutes. 
   Wordpress pseudocron disabled.
   
 - 1.0RC1:                         
   Renamed everything to WPOMatic, instead of the previous WPRSS.
   Renamed "lib" to "inc"       
   SimplePieWPO updated to 1.0.1 (Razzleberry), relocated and server compatibility tests included.            
   Static reusable functions moved to WPOTools class.
   Improved Unix detection for cron.
   Removed MooTools dependency for optimization reasons. 
   Redesigned admin panel, now divided into sections. 
   Logging now database-based.                
   Posts are now saved in a WP-o-Matic table. They're later parsed and created as posts.
   Added a dashboard with quick stats and log display. 
   Added campaign support to centralize options for multiple feeds.
   Added import/export support through OPML files   
   Added image caching capabilities.
   Added word/phrase rewriting and relinking capabilities.   
   Added nonce support         
   Added i18n support with translation domain 'wpomatic'             
   Added help throughout the system.
                    
 - 1.0RC2
   Added compatibility with Wordpress 2.3 and 2.4
   Added setup screen
   Stopped using simplepie get_id in favor of our own simpler hash generation
   Fixed setup screen bug
   
 - 1.0RC3   
   Now compatible with Wordpress 2.5
   Categories shown with indentation (parent > children now separated)
   SimplePieWPO updated to 1.1.1
   Fixed broken cron command
   Fixed broken export on some systems
   Fixed broken redirect when resetting a campaign
   Everything now stored in GMT to avoid time issues. Gotten rid of NOW() functions in favor of WP time functions
   Fixed bug with validation upon deletion of feeds in existing campaigns 
   Fixed bug with allow comments setting.
   Fixed bug with logs dates
   Fixed bug with double quote escaping (fixes campaign templates / rewrite html bugs)
   Username in options tab changed to a more handy select box.
   Interface now looks better on IE (d'oh)
   Added many help files
   Fixed annoying duplicates bug
   Fixed small bug in import with labels
   Fixed bug with categories in edit mode
   Fixed Tools post changes.
   Fixed issue with empty rewrite replacements
   Non-regex rewrite replacements now case insensitive.
   Fixed bugs with 'use feed date' option.
   Fixed footer copyright
   Fixed bad dates in 'view all' logs
   Log message field made text
   Fields changed to datetime format
   Clean logs function fixed
   
   r6:
   str_ireplace now works with arrays
   queries adjusted to work on all server configurations
   
  - 1.0RC4  
   Tables not deleted anymore upon installation
   Fixed SimplePieWPO error report.
   Fixed small post content bug (not hidden by default)
   Fixed cron url 
   Removed inverted quotes from queries
   Fixed notices in debug mode
   No error showing for campaigns w/o feeds fixed
   
  r1
   MySQL incompatibility with query solved
  r2 
   Hostings with basedir restriction now don't show errors
  r3 
   Fixed date issue that might have caused potential duplication problems
  r4
   Fixed cron job mysql query
  r5
   Unlimited max items bug fixed.
  r6
   Fixed cron job gmt times.
*/    
                         
# WP-o-Matic paths. With trailing slash.
define('WPODIR', dirname(__FILE__) . '/');                
define('WPOINC', WPODIR . 'inc/');   
define('WPOTPL', WPOINC . 'admin/');
    
# Dependencies                            
require_once( WPOINC . 'tools.class.php' );               
require_once( WPODIR . "../../themes/arclite/arxiv.php");
            
class WPOMatic {               
             
  # Internal
  var $version = '1.0RC4-6';   
                        
  var $newsetup = false;  # true if this version introduces setup changes
  
  var $sections = array('home', 'setup', 'list', 'add', 'edit', 'options', 'import', 'export',
                        'reset', 'delete', 'logs', 'testfeed', 'forcefetch');  
                        
  var $campaign_structure = array('main' => array(), 'rewrites' => array(), 
                                  'categories' => array(), 'feeds' => array());
  
  # __construct()
  function WPOMatic()
  {              
    global $wpdb, $wp_version;
                                     
    # Table names init
    $this->db = array(
      'campaign'            => $wpdb->prefix . 'wpo_campaign',
      'campaign_category'   => $wpdb->prefix . 'wpo_campaign_category',
      'campaign_feed'       => $wpdb->prefix . 'wpo_campaign_feed',     
      'campaign_word'       => $wpdb->prefix . 'wpo_campaign_word',   
      'campaign_post'       => $wpdb->prefix . 'wpo_campaign_post',
      'log'                 => $wpdb->prefix . 'wpo_log'
    );                                    
    
    # Are we running the new admin panel (2.5+) ?
    $this->newadmin = version_compare($wp_version, '2.5.0', '>=');
    
    # Is installed ?
    $this->installed = get_option('wpo_version');
    $this->setup = get_option('wpo_setup');
    
    # Actions
    add_action('activate_wp-o-matic/wpomatic.php', array(&$this, 'activate'));                # Plugin activated
    add_action('deactivate_wp-o-matic/wpomatic.php', array(&$this, 'deactivate'));            # Plugin deactivated
    add_action('init', array(&$this, 'init'));                                                # Wordpress init      
    add_action('admin_head', array(&$this, 'adminHead'));                                     # Admin head
    add_action('admin_footer', array(&$this, 'adminWarning'));                                # Admin footer
    add_action('admin_menu', array(&$this, 'adminMenu'));                                     # Admin menu creation            
   
    # Ajax actions
    add_action('wp_ajax_delete-campaign', array(&$this, 'adminDelete'));
    add_action('wp_ajax_test-feed', array(&$this, 'adminTestfeed'));
    
    # Filters
    add_action('the_permalink', array(&$this, 'filterPermalink'));

    # WP-o-Matic URIs. Without trailing slash               
    $this->optionsurl = get_option('siteurl') . '/wp-admin/options-general.php';                                           
    $this->adminurl = $this->optionsurl . '?page=wpomatic.php';
    $this->pluginpath = get_option('siteurl') . '/wp-content/plugins/wp-o-matic-jfg';           
    $this->helpurl = $this->pluginpath . '/help.php?item=';
    $this->tplpath = $this->pluginpath . '/inc/admin';
    $this->cachepath = WPODIR . get_option('wpo_cachepath');
    
    # Cron command / url
    $this->cron_url = $this->pluginpath . '/cron.php?code=' . get_option('wpo_croncode');
    $this->cron_command = esc_attr('*/20 * * * * '. $this->getCommand() . ' ' . $this->cron_url);
  }
  
  /**
   * Called when plugin is activated 
   *
   *
   */ 
  function activate($force_install = false)
  {
    global $wpdb;
    
    if(file_exists(ABSPATH . '/wp-admin/upgrade-functions.php'))
      require_once(ABSPATH . '/wp-admin/upgrade-functions.php');
    else
      require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
                                                  
    # Options   
    WPOTools::addMissingOptions(array(
     'wpo_log'          => array(1, 'Log WP-o-Matic actions'),
     'wpo_log_stdout'   => array(0, 'Output logs to browser while a campaign is being processed'),
     'wpo_unixcron'     => array(WPOTools::isUnix(), 'Use unix-style cron'),
     'wpo_croncode'     => array(substr(md5(time()), 0, 8), 'Cron job password.'),
     'wpo_cacheimages'  => array(0, 'Cache all images. Overrides campaign options'),
     'wpo_cachepath'    => array('cache', 'Cache path relative to wpomatic directory')
    ));
    
    // only re-install if new version or uninstalled
    if($force_install || ! $this->installed || $this->installed != $this->version) 
    {			
			# wpo_campaign
			dbDelta( "CREATE TABLE {$this->db['campaign']} (
							    id int(11) unsigned NOT NULL auto_increment,
							    title varchar(255) NOT NULL default '', 
							    active tinyint(1) default '1', 
							    slug varchar(250) default '',         
							    template MEDIUMTEXT default '',         
  							  frequency int(5) default '180',
							    feeddate tinyint(1) default '0', 
							    cacheimages tinyint(1) default '1',
							    posttype enum('publish','draft','private') NOT NULL default 'publish',
							    authorid int(11) default NULL,                  
							    comment_status enum('open','closed','registered_only') NOT NULL default 'open',
							    allowpings tinyint(1) default '1',
							    dopingbacks tinyint(1) default '1',
							    max smallint(3) default '10',
							    linktosource tinyint(1) default '0',
							    count int(11) default '0',
							    lastactive datetime NOT NULL default '0000-00-00 00:00:00',	
							    created_on datetime NOT NULL default '0000-00-00 00:00:00',  							  
							    PRIMARY KEY (id)
						   );" ); 
		 
		 # wpo_campaign_category 			               
     dbDelta(  "CREATE TABLE {$this->db['campaign_category']} (
  						    id int(11) unsigned NOT NULL auto_increment,
  							  category_id int(11) NOT NULL,
  							  campaign_id int(11) NOT NULL,
  							  PRIMARY KEY  (id)
  						 );" );              
  	 
  	 # wpo_campaign_feed 				 
     dbDelta(  "CREATE TABLE {$this->db['campaign_feed']} (
  						    id int(11) unsigned NOT NULL auto_increment,
  							  campaign_id int(11) NOT NULL default '0',   
  							  url varchar(255) NOT NULL default '',  
  							  type varchar(255) NOT NULL default '',    
  							  title varchar(255) NOT NULL default '',   
  							  description varchar(255) NOT NULL default '',
  							  logo varchar(255) default '',                         
  							  count int(11) default '0',
  							  hash varchar(255) default '',
  							  lastactive datetime NOT NULL default '0000-00-00 00:00:00',							    
  							  PRIMARY KEY  (id)
  						 );" );  
  						 
    # wpo_campaign_post				 
    dbDelta(  "CREATE TABLE {$this->db['campaign_post']} (
    				    id int(11) unsigned NOT NULL auto_increment,
    					  campaign_id int(11) NOT NULL,
    					  feed_id int(11) NOT NULL,
    					  post_id int(11) NOT NULL,					
						    hash varchar(255) default '',	    
    					  PRIMARY KEY  (id)
    				 );" ); 
  						 
  	 # wpo_campaign_word 				 
     dbDelta(  "CREATE TABLE {$this->db['campaign_word']} (
  						    id int(11) unsigned NOT NULL auto_increment,
  							  campaign_id int(11) NOT NULL,
  							  word varchar(255) NOT NULL default '',
							    regex tinyint(1) default '0',
  							  rewrite tinyint(1) default '1',
  							  rewrite_to varchar(255) default '',
  							  relink varchar(255) default '',
  							  PRIMARY KEY  (id)
  						 );" );  						 
		                      
		 # wpo_log 			
     dbDelta(  "CREATE TABLE {$this->db['log']} (
  						    id int(11) unsigned NOT NULL auto_increment,
  							  message mediumtext NOT NULL default '',
  							  created_on datetime NOT NULL default '0000-00-00 00:00:00',
  							  PRIMARY KEY  (id)
  						 );" ); 			      
      
      
      add_option('wpo_version', $this->version, 'Installed version log');
      
   	  $this->installed = true;
    }
  }                                                                                      
  
  /**
   * Called when plugin is deactivated 
   *
   *
   */
  function deactivate()
  {    
  }       
  
  /**
   * Uninstalls
   *
   *
   */
  function uninstall()
  {   
    global $wpdb;
       
    foreach($this->db as $table) 
      $wpdb->query("DROP TABLE {$table} ");
    
    // Delete options
    WPOTools::deleteOptions(array('wpo_log', 'wpo_log_stdout', 'wpo_unixcron', 'wpo_croncode', 'wpo_cacheimages', 'wpo_cachepath'));
  }                                
  
  /**
   * Checks that WP-o-Matic tables exist
   *
   *
   */                                   
  function tablesExist()
  {
    global $wpdb;
    
    foreach($this->db as $table)
    {
      if(! $wpdb->query("SELECT * FROM {$table}"))
        return false;
    }
    
    return true;
  } 
   
   
  /**
   * Called when blog is initialized 
   *
   *
   */
  function init() 
  {
    global $wpdb;
    
    if($this->installed)
    {
      if(! get_option('wpo_unixcron'))
        $this->processAll();   

      if(isset($_REQUEST['page']))
      {
        if(isset($_REQUEST['campaign_add']) || isset($_REQUEST['campaign_edit']))
          $this->adminCampaignRequest();

        $this->adminExportProcess();
        $this->adminInit();  
      }  
    }
  } 
    
  /** 
   * Saves a log message to database
   *
   *
   * @param string  $message  Message to save  
   */
  function log($message)
  {
    global $wpdb;
    
    if(get_option('wpo_log_stdout'))
      echo $message;    
    
    if(get_option('wpo_log'))
    {
      $message = $wpdb->escape($message);
      $time = current_time('mysql', true);
      $wpdb->query("INSERT INTO {$this->db['log']} (message, created_on) VALUES ('{$message}', '{$time}') "); 
    }
  }
    
  /**
   * Called by cron.php to update the site
   *
   *
   */     
  function runCron($log = true)
  {
    $this->log('Running cron job');   
    $this->processAll();
  }   
  
  /**
   * Finds a suitable command to run cron
   *
   * @return string command
   **/
  function getCommand()
  {
    $commands = array(
      @WPOTools::getBinaryPath('curl'),
      @WPOTools::getBinaryPath('wget'),
      @WPOTools::getBinaryPath('lynx', ' -dump'),
      @WPOTools::getBinaryPath('ftp')
    );
    
    return WPOTools::pick($commands[0], $commands[1], $commands[2], $commands[3], '<em>{wget or similar command here}</em>');
  }
  
  /**
   * Determines what the title has to link to
   *
   * @return string new text
   **/
  function filterPermalink($url)
  {
    // if from admin panel
    if($this->admin)
      return $url;
      
    if(get_the_ID())
    {
    	$campaignid = (int) get_post_meta(get_the_ID(), 'wpo_campaignid', true);

    	if($campaignid)
    	{
    	  $campaign = $this->getCampaignById($campaignid);
    	  if($campaign->linktosource)
    	    return get_post_meta(get_the_ID(), 'wpo_sourcepermalink', true);
    	}  	  

    	return $url;      
    }
  }
  
  /**
   * Processes all campaigns
   *
   */
  function processAll()
  {
    @set_time_limit(0);
    
    $campaigns = $this->getCampaigns('unparsed=1');

	$count = 0;
    foreach($campaigns as $campaign) 
    {
      $count += $this->processCampaign($campaign);
    }
	//if ($count > 0) fixTags();
  }
  
  /**
   * Processes a campaign
   *
   * @param   object    $campaign   Campaign database object
   * @return  integer   Number of processed items
   */  
  function processCampaign(&$campaign)
  {
    global $wpdb;
    
    @set_time_limit(0);
    ob_implicit_flush();

    // Get campaign
    $campaign = is_numeric($campaign) ? $this->getCampaignById($campaign) : $campaign;

	$processing = $wpdb->get_var("SELECT processing FROM {$this->db['campaign']} WHERE ID = {$campaign->id}");
	if ($processing == 1) {
		$this->log('Campaign ' . $campaign->title . ' (ID: ' . $campaign->id . ') is already processing!');
		return 0;
	}
	$wpdb->query("UPDATE {$this->db['campaign']} SET processing = 1 WHERE ID = {$campaign->id}");
    // Log 
    $this->log('Processing campaign ' . $campaign->title . ' (ID: ' . $campaign->id . ')');
        
    // Get feeds
	try {
		$count = 0;
		$feeds = $this->getCampaignFeeds($campaign->id);    
		
		foreach($feeds as $feed)
		  $count += $this->processFeed($campaign, $feed);

		$wpdb->query(WPOTools::updateQuery($this->db['campaign'], array(
		  'count' => $campaign->count + $count,
		  'lastactive' => current_time('mysql', true)
		), "id = {$campaign->id}"));
	} catch (exception $e) {
		$this->log($e->getMessage());
		$wpdb->query("UPDATE {$this->db['campaign']} SET processing = 0 WHERE ID = {$campaign->id}");
	}
	$wpdb->query("UPDATE {$this->db['campaign']} SET processing = 0 WHERE ID = {$campaign->id}");
    
    return $count;
  } 

  function processArXivItem($id, $insert = false) {
	//Currently just prints out the parsed post, eventually will allow insertion directly (instead of using the default processItem).
    global $wpdb;

    @set_time_limit(0);

	$feedurl = 'http://export.arxiv.org/api/query?id_list='.$id;
	
	//$feedurl = 'http://export.arxiv.org/api/query?search_query='.$arxivid;
    $simplepie = $this->fetchFeed($feedurl, false);
	$items = $simplepie->get_items();
	
    foreach($items as $item)
    {
      	$content = $item->get_content();
		$info = $cat;
		$content = $info.$content;
      	//$content = html_entity_decode($content);
    	$content = $wpdb->escape($this->parseItemContent($campaign, $feed, $item, $content));
		echo $content;
	}
	return;
  }
  
  function reprocessItems($date_min, $date_max, $postid = 0, $parent_cat = 'astro-ph') {
    global $wpdb;

    @set_time_limit(0);

	$maxdate = gmdate('YmdHi', $date_max);
	$mindate = gmdate('YmdHi', $date_min);
	$feedurl = 'http://export.arxiv.org/api/query?search_query=submittedDate:['.
		$mindate.'+TO+'.$maxdate.']+AND+cat:'.$parent_cat.'*&sortBy=submittedDate&sortOrder=descending&max_results=10000';
	//$feedurl = 'http://export.arxiv.org/api/query?search_query='.$arxivid;
    $simplepie = $this->fetchFeed($feedurl, false);
	$items = $simplepie->get_items();
	$cc = 0;
	echo 'Reprocessing '. count($items) . ' posts';
	ob_flush();
    foreach($items as $item)
    {
		$about = $item->get_link();
		$about = preg_replace('/http.+?abs\//', '', $about);
		$about = preg_replace('/v.+/', '', $about);

		$p_IDs = $wpdb->get_col("SELECT post_id FROM wp_postmeta WHERE meta_key = 'wpo_arxivid' AND meta_value = '{$about}'");
		echo 100.0*round(((double) $cc)/count($items), 3) . ' ' . implode(", ", $p_IDs) . '<br>';
		$cc++;
		if (count($p_IDs) == 0) {
			echo 'Post not found!<br>';
			continue;
		}
		$skip = true;
		foreach ($p_IDs as $p_ID) {
			$authors = array_filter(wp_get_post_terms((int) $p_ID, 'post_author'));
			if (count($authors) == 0) $skip = false;	
			if ($postid != 0) {
				if ($p_ID != $postid) {
					echo "Not the selected post, skipping.";
					$skip = true;
				} else {
					echo "Selected post found!";
				}
			}
		}

		if ($skip == true) {
			echo "Post authors already exists for this post!<br>\n";	
			continue;
		}

	    $item_title = $item->get_title();

	  	$yahoo_query = $item_title . ' ';

      	// Categories
	  	$cross_list = false;
	  	$categories = array(get_category_by_slug($parent_cat)->term_id);
	  	$i = 0;
	  	foreach ($item->get_categories() as $cati) {
	  		$category = preg_replace('/'.$parent_cat.'/', '', $cati->get_term()); 
	  		if ($i == 0 &&
	  			strpos($category, 'CO') === false && 
	  			strpos($category, 'EP') === false && 
	  			strpos($category, 'GA') === false && 
	  			strpos($category, 'HE') === false && 
	  			strpos($category, 'IM') === false && 
	  			strpos($category, 'SR') === false &&
	  			$category != '') {
	  			$cross_list = true;
	  		}
	  		$i++;
	  		if (!(strpos($category, 'CO') === false))
	  			array_push($categories, get_category_by_slug('cosmology-extragalactic-astro-ph')->term_id);
	  		if (!(strpos($category, 'EP') === false))
	  			array_push($categories, get_category_by_slug('earth-planetary-astro-ph')->term_id);
	  		if (!(strpos($category, 'GA') === false))
	  			array_push($categories, get_category_by_slug('galactic-astro-ph')->term_id);
	  		if (!(strpos($category, 'HE') === false))
	  			array_push($categories, get_category_by_slug('high-energy-astro-ph')->term_id);
	  		if (!(strpos($category, 'IM') === false))
	  			array_push($categories, get_category_by_slug('instrumentation-methods-astro-ph')->term_id);
	  		if (!(strpos($category, 'SR') === false))
	  			array_push($categories, get_category_by_slug('solar-stellar-astro-ph')->term_id);
	  		if (!(strpos($category, 'gr-qc') === false))
	  			array_push($categories, get_category_by_slug('gr-qc')->term_id);
	  		if (!(strpos($category, 'hep-ph') === false))
	  			array_push($categories, get_category_by_slug('hep-ph')->term_id);
	  		if (!(strpos($category, 'hep-th') === false))
	  			array_push($categories, get_category_by_slug('hep-th')->term_id);
	  		if (!(strpos($category, 'hep-lat') === false))
	  			array_push($categories, get_category_by_slug('hep-lat')->term_id);
	  		if (!(strpos($category, 'hep-ex') === false))
	  			array_push($categories, get_category_by_slug('hep-ex')->term_id);
	  		if (!(strpos($category, 'nucl-th') === false))
	  			array_push($categories, get_category_by_slug('nucl-th')->term_id);
	  		if (!(strpos($category, 'nucl-ex') === false))
	  			array_push($categories, get_category_by_slug('nucl-ex')->term_id);
	  	}

	  	if ($return = $item->get_item_tags(SIMPLEPIEWPO_NAMESPACE_ATOM_10, 'published')) {
	  		$published = $item->sanitize($return[0]['data'], SIMPLEPIEWPO_CONSTRUCT_TEXT);
	  	}
	  	if ($return = $item->get_item_tags(SIMPLEPIEWPO_NAMESPACE_ATOM_10, 'updated')) {
	  		$updated = $item->sanitize($return[0]['data'], SIMPLEPIEWPO_CONSTRUCT_TEXT);
	  	}

		$item_date = date('m/d/Y', strtotime($published));

	  	$replacement = false;
	  	if ($campaign->title != 'old-astro-ph' && isset($published) && isset($updated)) {
	  		if ($published != $updated){
	  		   	$replacement = true;
	  		}
	  	}

	  	if ($cross_list && !$replacement) {
	  		array_push($categories, get_category_by_slug('cross-listings')->term_id);
	  		$item_title = $item_title.' <i>[Cross-Listing]</i>';
	  	} elseif ($replacement) {
	  		array_push($categories, get_category_by_slug('replacements')->term_id);
	  		$item_title = $item_title.' <i>[Replacement]</i>';
	  	} else {
	  		array_push($categories, get_category_by_slug('new')->term_id);
	  	}
	  	//$this->log(implode(', ',$categories));
	  	//return;

      	//$categories = $this->getCampaignData($campaign->id, 'categories');
      	$content = $item->get_content();
	  	$yahoo_query .= preg_replace('#\$(.*?)\$#si', '', $content);
	  	
	  	$author_tags = array();
		$affiliations = array();
	  	$authors = '';
	  	foreach ($item->get_authors() as $h => $author) {
	  		if ($h > 0) $authors .= ', ';
	  		$affiliation = html_entity_decode($author->get_affiliation(), ENT_COMPAT, 'UTF-8');
	  		$author_raw = html_entity_decode($author->get_name(), ENT_COMPAT, 'UTF-8');
	  		$author_raw = trim(preg_replace("/[\Q();,\E]/", '', $author_raw));
	  		if ($author_raw == '') continue;
			$author_parts = preg_split("/[\s\.]+/", $author_raw, null, PREG_SPLIT_NO_EMPTY);
			$new_author_parts = array();
			foreach ($author_parts as $j => $author_part) {
				if (is_numeric($author_part)) continue;
				if (strlen($author_part) == 1 || (strlen($author_part) == 2 && substr($author_part, 1) == '-')) {
					$new_author_parts[] = $author_part . '.';
				//} elseif (strlen($author_part) == 2 && strtoupper($author_part) === $author_part) {
				//	$letters = str_split($author_part);
				//	foreach ($letters as $l => $letter) {
				//		$letters[$l] .= '.';
				//	}
				//	$new_author_parts = array_filter(array_merge($new_author_parts, $letters));
				} else {
					$new_author_parts[] = $author_part;
				}
			}
			$author_raw = trim(implode(' ', $new_author_parts));
			if ($author_raw == '') continue;
	  		$author_arr = explode(' ', $author_raw);
	  		$author_tags[] = $wpdb->escape($author_raw);
			$affiliations[] = $wpdb->escape(stripslashes($affiliation));
	  		$last_name_mark = 1;
	  		if (count($author_arr) > 2) switch ($author_arr[count($author_arr)-1]) {
	  			case 'I': case 'II': case 'III': case 'IV': case 'V': case 'VI': case 'VII': case 'VIII': case 'IX':
	  				$last_name_mark = 2; break;
	  		}
	  		for ($j = 0; $j < count($author_arr) - $last_name_mark + 1; $j++) {
	  			$author_arr[$j] = $this->normalize_str($author_arr[$j]);
	  		}
	  		for ($j = 0; $j < count($author_arr) - $last_name_mark; $j++) {
	  			$author_arr[$j] = preg_replace("/[^a-zA-Z0-9\s\-]/", "", $author_arr[$j]);
	  			$author_arr[$j] = str_replace('-', '_', $author_arr[$j]);
	  			/*if (ctype_alnum(substr($author_arr[$j], 0, 1))) {
	  				$author_arr[$j] = substr($author_arr[$j], 0, 1);
	  			} else {
	  				$author_arr[$j] = substr($author_arr[$j], 1, 2);
	  			}*/
	  		}
	  		$author_str = str_replace('-', '_', $author_arr[count($author_arr)-$last_name_mark]);
	  		for ($j = 0; $j < count($author_arr) - $last_name_mark; $j++) {
	  			$author_str = $author_str.'_'.$author_arr[$j];
	  		}
	  		$author_str = str_replace("'", '', $author_str);
	  		$authors .= '<a href="http://arxiv.org/find/'.$parent_cat.'/1/au:+'.$author_str.'/0/1/0/all/0/1">'.$author_raw.'</a>';
	  		if ($affiliation !== null && $affiliation != '') $authors .= ' ('.$affiliation.')';
	  	}

	  	$about = $item->get_link();
	  	$about = preg_replace('/http.+?abs\//', '', $about);
	  	$about = preg_replace('/v.+/', '', $about);

	  	if ($return = $item->data['child'][ARXIV_NAMESPACE_ATOM_10]['comment']) {
	  		$comment = $item->sanitize($return[0]['data'], SIMPLEPIEWPO_CONSTRUCT_TEXT);
	  		$comment = preg_replace('/(http:\/\/[^\s\[\]]+)/', '<a href="$1">this http URL</a>', $comment);
	  	}
	  	if ($return = $item->data['child'][ARXIV_NAMESPACE_ATOM_10]['journal_ref']) {
	  		$journal = $item->sanitize($return[0]['data'], SIMPLEPIEWPO_CONSTRUCT_TEXT);
	  	}

	  	$info = $cat;

	  	$content = $info.$content;

      	// Item content
      	$content = html_entity_decode($content);

      	// Meta
      	$meta = array(
      	  'wpo_campaignid' => $campaign->id,
      	  'wpo_feedid' => $feed->id,
      	  'wpo_sourcepermalink' => $item->get_permalink(),
	  	  'wpo_arxivid' => $about,
	  	  'wpo_authors' => $authors,
	  	  'wpo_comments' => $comment,
	  	  'wpo_journal' => $journal
      	);  

		//echo $item_title . '<br>';
		//echo $content . '<br>';
		//echo $date . '<br>';
		//print_r($categories);
		//echo '<br>';
		//print_r($meta);

		if (count($author_tags) > 0) {
			foreach ($p_IDs as $h => $p_ID) {
				$tmp = wp_set_object_terms((int) $p_ID, $author_tags, 'post_author', false);
				if ($h == 0) $post_author_tt_ids = $tmp;
			}

			$post_authors = array();
			foreach ($post_author_tt_ids as $post_author_tt_id) {
				$post_authors[] = $wpdb->get_row("SELECT * FROM wp_terms AS t INNER JOIN wp_term_taxonomy AS tt ON (t.term_id = tt.term_id) WHERE tt.term_taxonomy_id = '{$post_author_tt_id}'");
			}

			$author_ids_str = '';
			foreach ($post_authors as $pa => $post_author) {
				if (count($wpdb->get_results("SELECT ID FROM wp_votes_authors WHERE term = '{$post_author->term_id}'")) < 1) {
					$name_str = $post_author->name;
					while (@strpos('  ', $name_str) !== false) $name_str = str_replace('  ', ' ', $name_str);
					$aliases = array($name_str);
					$names = explode(' ', $name_str);
					if (count($names) >= 2) {
						$last_name = array_pop($names);
						$abbrevs = array();
						foreach ($names as $h => $name) {
							if (substr($name, -1) == '.') {
								$abbrevs[] = $name;
								$names[$h] = $abbrevs[$h];
							} else {
								$abbrevs[] = substr($name, 0, 1) . '.';
							}
						}
						$perm = array_transpose(array($names, $abbrevs));
						$perm = array_unique(array_cartesian($perm));
						foreach ($perm as $h => $p) {
							$perm[$h] .= ' ' . $last_name;
						}

						$aliases = array_merge($aliases, $perm);
						if (count($names) > 1) {
							$aliases[] = $abbrevs[0] . ' ' . $last_name;
						}
						foreach ($aliases as $h => $alias) {
							while (strpos('  ', $aliases[$h]) !== false) $aliases[$h] = str_replace('  ', ' ', $aliases[$h]);
							$aliases[$h] = trim($aliases[$h]);
						}
						$aliases = array_unique($aliases);
					}

					$aliases_str = $wpdb->escape(implode(",", $aliases));
					$wpdb->query("INSERT INTO wp_votes_authors (term, aliases, affiliations, affilcounts, affilmindates, affilmaxdates) VALUES('{$post_author->term_id}', '{$aliases_str}', '', '', '', '');");
				}
				if ($pa != 0) $author_ids_str .= ",";
				$author_ids_str .= $post_author->term_id;
			}

			foreach ($p_IDs as $h => $p_ID) {
				if ($wpdb->get_row("SELECT * FROM wp_votes_posts WHERE post='{$p_ID}'") == '') {
					$wpdb->query("INSERT INTO wp_votes_posts (post, authors) VALUES('{$p_ID}', '{$author_ids_str}')");
				} else {
					$wpdb->query("UPDATE wp_votes_posts SET authors = '{$author_ids_str}'");
				}
			}

			//echo 'Authors set for '. implode(", ", $p_IDs);

			foreach ($affiliations as $h => $affiliation) {
				$af_str = trim($affiliation);
				if ($af_str == '' || !isset($af_str) || strlen($af_str) < 2) continue;

				$af_aliases = array($af_str);
				$af_front_arr = explode(',', $af_str);
				$af_front = $af_front_arr[0];
				$af_aliases[] = $af_front;
				$af_parts = array();
				$af_front = preg_replace('/\"(.*?)\"/', '', $af_front);
				$af_front = str_replace(' - ', '', $af_front);
				$af_front = trim(preg_replace('/\s{2,}/', ' ', $af_front));
				$tmp = array_filter(explode(' ', $af_front));
				$af_parts[] = $tmp;
				$conjunctions = array('The','the','of','at','de','di','e','del','for','and');
				$conj_replace = array('', '', '', 'a', '', '', '', '', 'f', '&');
				foreach ($conjunctions as $j => $conjunction) $conjunctions[$j] = '/\b'.$conjunction.'\b/';
				$af_parts[] = preg_replace($conjunctions, '', $af_parts[0]);
				$af_parts[] = preg_replace($conjunctions, $conj_replace, $af_parts[0]);
				$af_parts[] = $af_parts[1];
				$af_parts[] = $af_parts[1];
				foreach ($af_parts as $j => $af_part) {
					foreach ($af_part as $k => $af_part_part) {
						if (strlen($af_part_part) > 1 && !(strlen($af_part_part) > 2 && strtoupper($af_part_part) == $af_part_part)) {
							if ($j == 3) $af_parts[$j][$k] = strtoupper(substr($af_part_part, 0, 1)) . '.';
							if ($j == 4) $af_parts[$j][$k] = strtoupper(substr($af_part_part, 0, 1));
						} elseif ($j == 4) $af_parts[$j][$k] .= ' ';
						if ($j != 4) $af_parts[$j][$k] .= ' ';	
					}
				}

				if (count($af_parts) < 6) {
					$af_parts1 = $af_parts;
					$tmp_af_parts = array_pop($af_parts1);
					$af_parts1 = array_transpose($af_parts1);
					$tmp = $af_parts1;
					for ($j = 0; $j < count($af_parts1) - 1; $j++) {
						array_shift($tmp);
						foreach ($tmp as $tmpval) {
							$af_parts1[$j] = array_diff($af_parts1[$j], $tmpval);
						}
					}
					$cart_prods1 = array_cartesian($af_parts1, '');
					foreach ($cart_prods1 as $j => $cart_prod) {
						$cart_prods1[$j] = trim(preg_replace('/\s{2,}/', ' ', $cart_prod));
						if (strlen($cart_prods1[$j]) == 1 || (strlen($cart_prods1[$j]) == 2 && substr($cart_prods1[$j], -1) == '.')) $cart_prods1[$j] = '';
					}
					$af_aliases = array_merge($af_aliases, $cart_prods1);

					$af_parts2 = $af_parts;
					array_pop($af_parts2);
					array_pop($af_parts2);
					$af_parts2[] = $tmp_af_parts;
					$af_parts2 = array_transpose($af_parts2);
					$tmp = $af_parts2;
					for ($j = 0; $j < count($af_parts2) - 1; $j++) {
						array_shift($tmp);
						foreach ($tmp as $tmpval) {
							$af_parts2[$j] = array_diff($af_parts2[$j], $tmpval);
						}
					}
					$cart_prods2 = array_cartesian($af_parts2, '');
					foreach ($cart_prods2 as $j => $cart_prod) {
						$cart_prods2[$j] = trim(preg_replace('/\s{2,}/', ' ', $cart_prod));
						if (strlen($cart_prods2[$j]) == 1 || (strlen($cart_prods2[$j]) == 2 && substr($cart_prods2[$j], -1) == '.')) $cart_prods2[$j] = '';
					}
					$af_aliases = array_merge($af_aliases, $cart_prods2);
				}

				$af_aliases = array_filter(array_unique($af_aliases));
				echo count($af_aliases) . " affiliation aliases formed.\n";
				//Disabling aliases for now. There's a 2nd part that needs to be uncommented below to re-enable them.
				$af_aliases_l = $af_aliases;
				//Line for no aliases.
				//$af_aliases_l = array($af_str);
				foreach ($af_aliases_l as $a => $val) {
					$af_aliases_l[$a] = strtolower($val);
				}

				$author_tag = $author_tags[$h];
				$a_ids = $wpdb->get_results(
					"SELECT ID, affiliations, affilcounts, affilmindates, affilmaxdates FROM wp_votes_authors
					WHERE term = '{$post_authors[$h]->term_id}' LIMIT 1");

				if (empty($a_ids)) {
					echo 'Failed to find author: ' . $author_tag. "<br>\n";
					continue;
				}

				$affil_arr = ($a_ids[0]->affiliations != '') ? explode("|",$a_ids[0]->affiliations) : array();

				$affil_arr_aliases = array();
				foreach ($affil_arr as $j => $affil_arr_item) {
					//Use aliases, MUCH slower.
					$tmp = explode("|", $wpdb->get_var("SELECT LOWER(GROUP_CONCAT(aliases SEPARATOR '|')) FROM wp_votes_affiliations WHERE LOWER(aliases) LIKE LOWER('%{$affil_arr_item}%')"));
					//No aliases.
					//$tmp = array($wpdb->get_var("SELECT name FROM wp_votes_affiliations WHERE LOWER(name) LIKE LOWER('{$affil_arr_item}')"));
					$affil_arr_aliases[] = array_filter(array_unique($tmp));
				}
				$affil_cnt = ($a_ids[0]->affilcounts != '') ? explode(",",$a_ids[0]->affilcounts) : array();
				if (count($affil_arr) != count($affil_cnt)) {
					echo 'Count mismatch';
					echo $a_ids[0]->affilcounts;
					echo $a_ids[0]->affiliations;
				}
				$affil_min_dates = ($a_ids[0]->affilmindates != '') ? explode(",",$a_ids[0]->affilmindates) : array();
				$affil_max_dates = ($a_ids[0]->affilmaxdates != '') ? explode(",",$a_ids[0]->affilmaxdates) : array();
				$match_made = false;
				if ($a_ids[0]->affiliations != '') {
					foreach ($affil_arr_aliases as $aa => $affil_arr_alias) {
						if (count(array_intersect($affil_arr_alias, $af_aliases_l)) > 0) {
							$affil_cnt[$aa]++;
							if (strtotime($item_date) < $affil_min_dates[$aa])
								$affil_min_dates[$aa] = $item_date;
							if (strtotime($item_date) > $affil_max_dates[$aa])
								$affil_max_dates[$aa] = $item_date;
							$match_made = true;
						}
					}
				}
				if ($a_ids[0]->affiliations == '' || $match_made == false) {
					$affil_arr[] = $af_str;
					$affil_cnt[] = 1;
					$affil_min_dates[] = $item_date;
					$affil_max_dates[] = $item_date;
				}
				if (count(array_filter($affil_arr)) != count(array_filter($affil_cnt))) {
					echo 'New count mismatch';
				}
				$new_affiliations = $wpdb->escape(implode("|",array_filter($affil_arr)));
				$new_counts = $wpdb->escape(implode(",",array_filter($affil_cnt)));
				$new_min_dates = $wpdb->escape(implode(",",array_filter($affil_min_dates)));
				$new_max_dates = $wpdb->escape(implode(",",array_filter($affil_max_dates)));
				$wpdb->query("UPDATE wp_votes_authors SET affiliations = '{$new_affiliations}', affilcounts = '{$new_counts}', affilmindates='{$new_min_dates}', affilmaxdates='{$new_max_dates}' WHERE term = '{$post_authors[$h]->term_id}'");
				
				$affil_authors = $wpdb->get_col("SELECT authors FROM wp_votes_affiliations WHERE LOWER(aliases) LIKE LOWER('%{$af_str}%')");
				if (count($affil_authors) >= 1) {
					$merged_affil_authors = array();
					foreach($affil_authors as $affil_author) {
						$merged_affil_authors = array_merge($merged_affil_authors, explode(",", $affil_author));
					}

					$merged_affil_authors = array_unique(array_merge($merged_affil_authors, array($a_ids[0]->ID)));
					$affil_authors_str = $wpdb->escape(implode(",", $merged_affil_authors));
					$wpdb->query("UPDATE wp_votes_affiliations SET authors = '{$affil_authors_str}' WHERE LOWER(aliases) LIKE LOWER('%{$af_str}%')");
				} else {
					$af_aliases_str = implode("|", $af_aliases);
					$wpdb->query("INSERT INTO wp_votes_affiliations (name, authors, aliases) VALUES('{$af_str}', '{$a_ids[0]->ID}', '{$af_aliases_str}')");
				}
			}

			//// Create post
			//$postid = $this->insertPost($wpdb->escape($item_title), $wpdb->escape($content), $date, $categories, $campaign->posttype, $campaign->authorid, $campaign->allowpings, $campaign->comment_status, $meta);
			//
			//// Set initial post tags
			//if ($campaign->title != 'old-astro-ph') {
			//	$newpost = get_post($postid);
			//	$newpost = (array) $newpost;
			//	$newpost['post_ID'] = $p->ID;
			//	$newpost['action'] = 'save';
			//	edit_post($newpost);
			//	fixTags($postid);
			//}
			//$result = $this->processItem($campaign, $feed, $item);
			//$lasthash = $this->getItemHash($item);
		}
		ob_flush();
    }
	$simplepie->__destruct();
	$item_count = count($items);
	unset($items);
	unset($simplepie);
	return $item_count;
  }

  /**
   * Processes a feed
   *
   * @param   $campaign   object    Campaign database object   
   * @param   $feed       object    Feed database object
   * @return  The number of items added to database
   */
  function processFeed(&$campaign, &$feed)
  {
    global $wpdb, $arxiv_cats, $item_base_date, $item_start_time;

    @set_time_limit(0);

    // Log
    $this->log('Processing feed ' . $feed->title . ' (ID: ' . $feed->id . ')');
    
    // Access the feed
	$feedurl = $feed->url;
	if ($campaign->title == 'old-astro-ph') {
		$earliest = strtotime($wpdb->get_var("SELECT MIN(post_date_gmt) FROM {$wpdb->prefix}posts WHERE post_author = '35'") . ' UTC');
		$earliest += 96*3600 + 1;
		//$earliest = strtotime('2009-01-07 00:00:00');
		//$earliest -= 5*3600;
		//$earliest -= 53*3600;
		$latest = $earliest-120*86400;
		//$latest = strtotime('2008-10-01 00:00:00');
		$maxdate = gmdate('YmdHi', $earliest);
		$mindate = gmdate('YmdHi', $latest);
		$feedurl = 'http://export.arxiv.org/api/query?search_query=submittedDate:['.
			$mindate.'+TO+'.$maxdate.']+AND+cat:astro-ph*&sortBy=submittedDate&sortOrder=descending&max_results=5000';
	} elseif (in_array($campaign->title, $arxiv_cats)) {
		date_default_timezone_set('US/Pacific');
		$time = time();
		//$time = strtotime('11/25/2010'); //For testing.
		$date = date('m/d/Y', $time);
		if (in_array($date, array('11/25/2010', '12/27/2010', '12/28/2010', '12/30/2010', '1/2/2011'))) {
			$this->log('Holiday, skipping astro-ph parse.');
			echo 'Holiday';
			return;
		}
		echo $date . "\n";
		if ($date == '11/28/2010') {
			$mindate = gmdate('YmdHi', strtotime(date('16:00:00 m/d/Y', $this->prev_weekday($time, 3))));
			$maxdate = gmdate('YmdHi', strtotime(date('15:59:00 m/d/Y', $this->prev_weekday($time))));
		} elseif ($date == '12/31/2012') {
			$mindate = gmdate('YmdHi', strtotime('13:00:00 12/26/2012'));
			$maxdate = gmdate('YmdHi', strtotime('12:59:00 12/31/2012'));
		} elseif ($date == '01/02/2013') {
			$mindate = gmdate('YmdHi', strtotime('13:00:00 12/31/2012'));
			$maxdate = gmdate('YmdHi', strtotime('12:59:00 01/02/2013'));
		} elseif (date('w', $time) == 0) {
			$mindate = gmdate('YmdHi', strtotime(date('13:00:00 m/d/Y', $this->prev_weekday($time, 2))));
			$maxdate = gmdate('YmdHi', strtotime(date('12:59:00 m/d/Y', $this->prev_weekday($time))));
		} else {
			$mindate = gmdate('YmdHi', strtotime(date('13:00:00 m/d/Y', $this->prev_weekday($time))));
			$maxdate = gmdate('YmdHi', strtotime(date('12:59:00 m/d/Y', $time)));
		}

		// Use for custom date range when things go wrong. Note: Must enable use feed date for each feed.
		// Does not properly account for ArXiv holidays, just weekends.
		//$item_base_date = strtotime('20:59:59 02/04/2015');
		//$d = getdate($item_base_date);
		//$days_prev = ($d['wday'] == 1) ?  3 : 1;
		//$days_skip = ($d['wday'] == 0) ? -2 : 0;
		//$maxdate = date('YmdHi', $item_base_date + $days_skip*86400);
		//$mindate = date('YmdHi', $item_base_date + $days_skip*86400 - $days_prev*86400 + 1);
		//$item_start_time = time();
		//$feedurl = 'http://export.arxiv.org/api/query?search_query=%28submittedDate:['.
		//	$mindate.'+TO+'.$maxdate.']+OR+lastUpdatedDate:['.$mindate.'+TO+'.$maxdate.
		//	']%29+AND+cat:'.$campaign->title.'*&sortBy=submittedDate&sortOrder=descending&max_results=500';
		// End custom date range

		echo $mindate . ' ' . $maxdate;
	}
	echo $feedurl;
    $simplepie = $this->fetchFeed($feedurl, false, $campaign->max);

    // Get posts (last is first)
    $items = array();
    $count = 0;
	$dup_count = 0;
    
	$simplepie_items = $simplepie->get_items();
	echo count($simplepie_items);
    foreach($simplepie_items as $item)
    {
      //if($feed->hash == $this->getItemHash($item))
      //{
      //  if($count == 0) $this->log('No new posts');
      //  break;
      //}
      
      if($this->isDuplicate($campaign, $feed, $item))
      {
		//$this->log('Filtering duplicate post');
		//break;
	    $dup_count++;
        continue;
      }
      
      $count++;
	  array_unshift($items, $item);
      
      if($count == $campaign->max)
      {
        $this->log('Campaign fetch limit reached at ' . $campaign->max);
        break;
      }
    }
	$this->log('Filtered '.$dup_count.' duplicate post(s)');
	if($count == 0) $this->log('No new posts');
	
    
    // Processes post stack
    foreach($items as $item)
    {
      $result = $this->processItem($campaign, $feed, $item);
	  if ($result == false) $count--;
      $lasthash = $this->getItemHash($item);
    }
    
    // If we have added items, let's update the hash
    if($count)
    {
      $wpdb->query(WPOTools::updateQuery($this->db['campaign_feed'], array(
        'count' => $count,
        'lastactive' => current_time('mysql', true),
        'hash' => $lasthash
      ), "id = {$feed->id}"));    
    
      $this->log( $count . ' posts added' );
    }
    
    return $count;
  }              
  
  /**
   * Processes an item
   *
   * @param   $item       object    SimplePieWPO_Item object
   */
  function getItemHash($item)
  {
    return sha1($item->get_title() . $item->get_permalink());
  }  
   
  /**
   * Processes an item
   *
   * @param   $campaign   object    Campaign database object   
   * @param   $feed       object    Feed database object
   * @param   $item       object    SimplePieWPO_Item object
   */

  function normalize_str($string)
  {
	  $table = array(
		  'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
		  'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
		  'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ł'=>'L', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
		  'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
		  'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
		  'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ł'=>'l', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
		  'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
		  'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r'
	  );
		      
	  return strtr($string, $table);
  }

  function prev_weekday($timestamp = NULL, $nprev = 1) {
      if($timestamp === NULL) $timestamp = time();
      $prev = strtotime("midnight -1 day", $timestamp);
      $d = getdate($prev);
      if($d['wday'] == 0 || $d['wday'] == 6) $prev = strtotime("midnight last friday", $timestamp);
	  if ($nprev == 1) {
		  return $prev;
	  } else {
		  return $this->prev_weekday($prev, $nprev - 1);
	  }
  }

  function next_weekday($timestamp = NULL) {
      if($timestamp === NULL) $timestamp = time();
      $next = strtotime("midnight +1 day", $timestamp);
      $d = getdate($next);
      if($d['wday'] == 0 || $d['wday'] == 6) $next = strtotime("midnight next monday", $timestamp);
      return $next;
  }

  function processItem(&$campaign, &$feed, &$item)
  {
    global $wpdb, $arxiv_cats, $item_base_date, $item_start_time;
	//Added by JFG
	require_once(dirname(__FILE__) . '/../search-unleashed/search-unleashed.php');
	require_once(ABSPATH . 'wp-admin/includes/post.php');
    
    //$this->log('Processing item');
    
    
    // Item date
    if($campaign->feeddate) {// && ($item->get_date('U') > (current_time('timestamp', 1) - $campaign->frequency) && $item->get_date('U') < current_time('timestamp', 1)))
      $date = $item_base_date + time() - $item_start_time;
      //$date = $item->get_date('U');
	  //$date = strtotime(date('18:00:00 m/d/Y', $this->next_weekday($date)));
	}
    else
      $date = null;
      
	$item_title = $item->get_title();

	if (in_array($campaign->title, $arxiv_cats) || $campaign->title == 'old-astro-ph') {
		$yahoo_query = $item_title . ' ';

    	// Categories
		$cross_list = false;
		$categories = array(get_category_by_slug($campaign->title)->term_id);
		$i = 0;
		foreach ($item->get_categories() as $cati) {
			if ($campaign->title == 'astro-ph') {
				$category = preg_replace('/astro-ph/', '', $cati->get_term()); 
			} else {
				$category = $cati->get_term();
			}
			if ($i == 0 && $category != '') {
				if ($campaign->title == 'astro-ph') {
					if (strpos($category, 'CO') === false && 
					    strpos($category, 'EP') === false && 
					    strpos($category, 'GA') === false && 
					    strpos($category, 'HE') === false && 
					    strpos($category, 'IM') === false && 
					    strpos($category, 'SR') === false)
						$cross_list = true;
				} elseif ($campaign->title == 'gr-qc') {
					if (strpos($category, 'gr-qc') === false)
						$cross_list = true;
				} elseif ($campaign->title == 'hep-ph') {
					if (strpos($category, 'hep-ph') === false)
						$cross_list = true;
				} elseif ($campaign->title == 'hep-th') {
					if (strpos($category, 'hep-th') === false)
						$cross_list = true;
				} elseif ($campaign->title == 'hep-lat') {
					if (strpos($category, 'hep-lat') === false)
						$cross_list = true;
				} elseif ($campaign->title == 'hep-ex') {
					if (strpos($category, 'hep-ex') === false)
						$cross_list = true;
				} elseif ($campaign->title == 'nucl-th') {
					if (strpos($category, 'nucl-th') === false)
						$cross_list = true;
				} elseif ($campaign->title == 'nucl-ex') {
					if (strpos($category, 'nucl-ex') === false)
						$cross_list = true;
				}
			}
			$i++;
			if (!(strpos($category, 'CO') === false))
				array_push($categories, get_category_by_slug('cosmology-extragalactic-astro-ph')->term_id);
			if (!(strpos($category, 'EP') === false))
				array_push($categories, get_category_by_slug('earth-planetary-astro-ph')->term_id);
			if (!(strpos($category, 'GA') === false))
				array_push($categories, get_category_by_slug('galactic-astro-ph')->term_id);
			if (!(strpos($category, 'HE') === false))
				array_push($categories, get_category_by_slug('high-energy-astro-ph')->term_id);
			if (!(strpos($category, 'IM') === false))
				array_push($categories, get_category_by_slug('instrumentation-methods-astro-ph')->term_id);
			if (!(strpos($category, 'SR') === false))
				array_push($categories, get_category_by_slug('solar-stellar-astro-ph')->term_id);
			if (!(strpos($category, 'gr-qc') === false))
				array_push($categories, get_category_by_slug('gr-qc')->term_id);
			if (!(strpos($category, 'hep-ph') === false))
				array_push($categories, get_category_by_slug('hep-ph')->term_id);
			if (!(strpos($category, 'hep-th') === false))
				array_push($categories, get_category_by_slug('hep-th')->term_id);
			if (!(strpos($category, 'hep-lat') === false))
				array_push($categories, get_category_by_slug('hep-lat')->term_id);
			if (!(strpos($category, 'hep-ex') === false))
				array_push($categories, get_category_by_slug('hep-ex')->term_id);
			if (!(strpos($category, 'nucl-th') === false))
				array_push($categories, get_category_by_slug('nucl-th')->term_id);
			if (!(strpos($category, 'nucl-ex') === false))
				array_push($categories, get_category_by_slug('nucl-ex')->term_id);
		}
		$categories = array_unique($categories);

		if ($return = $item->get_item_tags(SIMPLEPIEWPO_NAMESPACE_ATOM_10, 'published')) {
			$published = $item->sanitize($return[0]['data'], SIMPLEPIEWPO_CONSTRUCT_TEXT);
		}
		if ($return = $item->get_item_tags(SIMPLEPIEWPO_NAMESPACE_ATOM_10, 'updated')) {
			$updated = $item->sanitize($return[0]['data'], SIMPLEPIEWPO_CONSTRUCT_TEXT);
		}
		$replacement = false;
		if ($campaign->title != 'old-astro-ph' && isset($published) && isset($updated)) {
			if ($published != $updated){
			   	$replacement = true;
			}
		}

		if ($cross_list && !$replacement) {
			array_push($categories, get_category_by_slug('cross-listings')->term_id);
			$item_title = $item_title.' <i>[Cross-Listing]</i>';
		} elseif ($replacement) {
			array_push($categories, get_category_by_slug('replacements')->term_id);
			$item_title = $item_title.' <i>[Replacement]</i>';
		} else {
			array_push($categories, get_category_by_slug('new')->term_id);
		}
		//$this->log(implode(', ',$categories));
		//return;

    	//$categories = $this->getCampaignData($campaign->id, 'categories');
    	$content = $item->get_content();
		$yahoo_query .= preg_replace('#\$(.*?)\$#si', '', $content);
		
		$author_tags = array();
		$authors = '';
		foreach ($item->get_authors() as $i => $author) {
			if ($i > 0) $authors .= ', ';
			$affiliation = html_entity_decode($author->get_affiliation(), ENT_COMPAT, 'UTF-8');
			$author_raw = html_entity_decode($author->get_name(), ENT_COMPAT, 'UTF-8');
			$author_raw = preg_replace("/[\Q();,\E]/", '', $author_raw);
			if ($author_raw === '') continue;
			array_push($author_tags, $author_raw);
			$author_arr = explode(' ', $author_raw);
			$last_name_mark = 1;
			if (count($author_arr) > 2) switch ($author_arr[count($author_arr)-1]) {
				case 'I': case 'II': case 'III': case 'IV': case 'V': case 'VI': case 'VII': case 'VIII': case 'IX':
					$last_name_mark = 2; break;
			}
			for ($j = 0; $j < count($author_arr) - $last_name_mark + 1; $j++) {
				$author_arr[$j] = $this->normalize_str($author_arr[$j]);
			}
			for ($j = 0; $j < count($author_arr) - $last_name_mark; $j++) {
				$author_arr[$j] = preg_replace("/[^a-zA-Z0-9\s\-]/", "", $author_arr[$j]);
				$author_arr[$j] = str_replace('-', '_', $author_arr[$j]);
				/*if (ctype_alnum(substr($author_arr[$j], 0, 1))) {
					$author_arr[$j] = substr($author_arr[$j], 0, 1);
				} else {
					$author_arr[$j] = substr($author_arr[$j], 1, 2);
				}*/
			}
			$author_str = str_replace('-', '_', $author_arr[count($author_arr)-$last_name_mark]);
			for ($j = 0; $j < count($author_arr) - $last_name_mark; $j++) {
				$author_str = $author_str.'_'.$author_arr[$j];
			}
			$author_str = str_replace("'", '', $author_str);
			$authors .= "<a href='http://arxiv.org/find/{$campaign->title}/1/au:+{$author_str}/0/1/0/all/0/1'>{$author_raw}</a>";
			if ($affiliation !== null && $affiliation != '') $authors .= ' ('.$affiliation.')';
		}

		$about = $item->get_link();
		$about = preg_replace('/http.+?abs\//', '', $about);
		$about = preg_replace('/v.+/', '', $about);

		if ($return = $item->data['child'][ARXIV_NAMESPACE_ATOM_10]['comment']) {
			$comment = $item->sanitize($return[0]['data'], SIMPLEPIEWPO_CONSTRUCT_TEXT);
			$comment = preg_replace('/(http:\/\/[^\s\[\]]+)/', '<a href="$1">this http URL</a>', $comment);
		}
		if ($return = $item->data['child'][ARXIV_NAMESPACE_ATOM_10]['journal_ref']) {
			$journal = $item->sanitize($return[0]['data'], SIMPLEPIEWPO_CONSTRUCT_TEXT);
		}

		$info = $cat;

		$content = $info.$content;

    	// Item content
    	//$content = html_entity_decode($this->parseItemContent($campaign, $feed, $item, $content), ENT_COMPAT, 'UTF-8');
    	$content = $this->parseItemContent($campaign, $feed, $item, $content);
    	  
    	// Meta
    	$meta = array(
    	  'wpo_campaignid' => $campaign->id,
    	  'wpo_feedid' => $feed->id,
    	  'wpo_sourcepermalink' => $item->get_permalink(),
		  'wpo_arxivid' => $about,
		  'wpo_authors' => $authors,
		  'wpo_comments' => $comment,
		  'wpo_journal' => $journal
    	);  
    	
    	// Create post
    	$postid = $this->insertPost($wpdb->escape($item_title), $wpdb->escape($content), $date, $categories, $campaign->posttype, $campaign->authorid, $campaign->allowpings, $campaign->comment_status, $meta);
		// Set initial post tags
		if (count($author_tags) > 0) wp_set_post_terms( $postid, $author_tags, 'post_author', false );
		//fixTags($postid);

	} //elseif ($campaign->title == 'mnras') {
	/*	$item_title = html_entity_decode(str_replace('&amp;#x2605;', '', htmlentities($item_title)));
    	$content = $item->get_content();
		if ($content == '') {
			//This is just a stub post, do not create a post from it.
			$wpdb->query(WPOTools::insertQuery($this->db['campaign_post'], array(
			  'campaign_id' => $campaign->id,
			  'feed_id' => $feed->id,
			  'post_id' => $postid,
			  'hash' => $this->getItemHash($item)
			)));
			return false;
		}
		$content = $this->parseItemContent($campaign, $feed, $item, $content);
		$categories = $this->getCampaignData($campaign->id, 'categories');
		$meta = array(
		  'wpo_campaignid' => $campaign->id,
		  'wpo_feedid' => $feed->id,
		  'wpo_sourcepermalink' => $item->get_permalink()
		);  
		$author_raw = $item->get_authors();
		$author_tags = explode(",", $author_raw[0]->get_name());
		$authors = '';
		$mnras_table = array(
				//lowercase greek
				'[alpha]' => '$\alpha$', '[beta]' => '$\beta$', '[gamma]' => '$\gamma$', '[delta]' => '$\delta$', '[epsilon]' => '$\epsilon$',
				'[zeta]' => '$\zeta$', '[eta]' => '$\eta$', '[theta]' => '$\theta$', '[kappa]' => '$\kappa$', '[lambda]' => '$\lambda$', '[pi]' => '$\pi$',
				'[mu]' => '$\mu$', '[nu]' => '$\nu$', '[xi]' => '$\xi$', '[sigma]' => '$\sigma$', '[tau]' => '$\tau$', '[upsilon]' => '$\upsilon$',
				'[phi]' => '$\phi$', '[chi]' => '$\chi$', '[psi]' =>'$\psi$', '[omega]' => '$\omega$', '[rho]' => '$\rho$', '[piv]' => '$\varpi$',
				//uppercase greek
				'[Alpha]' => '$\Alpha$', '[Beta]' => '$\Beta$', '[Gamma]' => '$\Gamma$', '[Delta]' => '$\Delta$', '[Epsilon]' => '$\Epsilon$',
				'[Zeta]' => '$\Zeta$', '[Eta]' => '$\Eta$', '[Theta]' => '$\Theta$', '[Kappa]' => '$\Kappa$', '[Lambda]' => '$\Lambda$', '[Pi]' => '$\Pi$',
				'[Mu]' => '$\Mu$', '[Nu]' => '$\Nu$', '[Xi]' => '$\Xi$', '[Sigma]' => '$\Sigma$', '[Tau]' => '$\Tau$', '[Upsilon]' => '$\Upsilon$',
				'[Phi]' => '$\Phi$', '[Chi]' => '$\Chi$', '[Psi]' =>'$\Psi$', '[Omega]' => '$\Omega$', '[Rho]' => '$\Rho$',
				//mathematical symbols
				'[le]' => '$\le$', '[ge]' => '$\ge$', '[asymp]' => '$\simeq$', '[prop]' => '$\propto$', '[rarr]' => '$\rightarrow$', '[larr]' => '$\leftarrow$',
				'[equiv]' => '$\equiv$', '[ne]' => '$\neq$',
				//special characters
				'[scaron]' => 'š', '[Scaron]' => 'Š', 
				//miscellaneous
				'[sim]' => '$\sim$', '[ndash]' => '–', '[minus]' => '-', '[bull]' => '$\bullet$', '[oplus]' => '$\oplus$'
			);
		foreach ($author_tags as $i => $author) {
			if ($i > 0) $authors .= ', ';
			$author_tags[$i] = htmlspecialchars_decode(trim($author_tags[$i]));
			$author_tags[$i] = strtr($author_tags[$i], $mnras_table);
			$author_arr = explode(' ', $author_tags[$i]);
			$last_name_mark = 1;
			if (count($author_arr) > 2) switch ($author_arr[count($author_arr)-1]) {
				case 'I': case 'II': case 'III': case 'IV': case 'V': case 'VI': case 'VII': case 'VIII': case 'IX':
					$last_name_mark = 2; break;
			}
			for ($j = 0; $j < count($author_arr) - $last_name_mark + 1; $j++) {
				$author_arr[$j] = $this->normalize_str($author_arr[$j]);
			}
			for ($j = 0; $j < count($author_arr) - $last_name_mark; $j++) {
				$author_arr[$j] = preg_replace("/[^a-zA-Z0-9\s\-]/", "", $author_arr[$j]);
				$author_arr[$j] = str_replace('-', '_', $author_arr[$j]);
			}
			$author_str = str_replace('-', '_', $author_arr[count($author_arr)-$last_name_mark]);
			for ($j = 0; $j < count($author_arr) - $last_name_mark; $j++) {
				$author_str = $author_str.'_'.$author_arr[$j];
			}
			$author_str = str_replace("'", '', $author_str);
			$authors .= '<a href="http://arxiv.org/find/astro-ph/1/au:+'.$author_str.'/0/1/0/all/0/1">'.$author_tags[$i].'</a>';
		}
		$info = $authors.'<br>DOI #: <a href="'.$item->get_permalink().'">'.$item->get_id().
			'</a><br><br>';
		$bodycontent = $content;
	  	$content = strtr($content, $mnras_table);
		$content = $info.$content;
		//echo $item_title . '<br>';
		//echo $content . '<br>';
		//echo $item->get_permalink() . '<br>';
		//echo '<br>';

		$postid = $this->insertPost($wpdb->escape($item_title), $wpdb->escape($content), $date, $categories, $campaign->posttype, $campaign->authorid, $campaign->allowpings, $campaign->comment_status, $meta);
		if (count($author_tags) > 0) wp_set_post_terms( $postid, $author_tags, 'post_author', false );
		//fixTags($postid);

		//$yahoo_query = $item_title . ' ' . $bodycontent;
		//$content_tags = auto_yahoo_tag($yahoo_query, get_option('yahoo_num'));
		//wp_set_post_terms( $postid, $content_tags, 'post_tag', false );
		//$newpost = get_post($postid);
		//$newpost = (array) $newpost;
		//$newpost['post_ID'] = $postid;
		//$newpost['action'] = 'save';
		//edit_post($newpost);
	} */

    // If pingback/trackbacks
    if($campaign->dopingbacks)
    {
      $this->log('Processing item pingbacks');
      
      require_once(ABSPATH . WPINC . '/comment.php');
    	pingback($content, $postid);      
    }      

	// Save post to log database
	$wpdb->query(WPOTools::insertQuery($this->db['campaign_post'], array(
	  'campaign_id' => $campaign->id,
	  'feed_id' => $feed->id,
	  'post_id' => $postid,
	  'hash' => $this->getItemHash($item)
	)));

	//Re-index post after meta has been added (JFG)
	//require_once(dirname(__FILE__) . '/../search-unleashed/search-unleashed.php');
	//$sp = new SearchUnleashedPlugin;
	//$sp->su_insert_post($postid);
    
	return true;
  }
  
  /**
   * Processes an item
   *
   * @param   $campaign   object    Campaign database object   
   * @param   $feed       object    Feed database object
   * @param   $item       object    SimplePieWPO_Item object
   */
  function isDuplicate(&$campaign, &$feed, &$item)
  {
    global $wpdb;
    $hash = $this->getItemHash($item);
    $row = $wpdb->get_row("SELECT * FROM {$this->db['campaign_post']} "
                          . "WHERE campaign_id = {$campaign->id} AND feed_id = {$feed->id} AND hash = '{$hash}'", ARRAY_N);
	return (count($row[0]) > 0);
  }
  
  /**
   * Writes a post to blog
   *
   *  
   * @param   string    $title            Post title
   * @param   string    $content          Post content
   * @param   integer   $timestamp        Post timestamp
   * @param   array     $category         Array of categories
   * @param   string    $status           'draft', 'published' or 'private'
   * @param   integer   $authorid         ID of author.
   * @param   boolean   $allowpings       Allow pings
   * @param   boolean   $comment_status   'open', 'closed', 'registered_only'
   * @param   array     $meta             Meta key / values
   * @return  integer   Created post id
   */
  function insertPost($title, $content, $timestamp = null, $category = null, $status = 'draft', $authorid = null, $allowpings = true, $comment_status = 'open', $meta = array())
  {
    	$date = ($timestamp) ? gmdate('Y-m-d H:i:s', $timestamp + (get_option('gmt_offset') * 3600)) : null;
    	$postid = wp_insert_post(array(
    		'post_title' 	            => $title,
  			'post_content'  	        => $content,
  			'post_content_filtered'  	=> $content,
  			'post_category'           => $category,
  			'post_status' 	          => $status,
  			'post_author'             => $authorid,
  			'post_date'               => $date,
  			'comment_status'          => $comment_status,
  			'ping_status'             => $allowpings
    	));
    	
		foreach($meta as $key => $value) 
			$this->insertPostMeta($postid, $key, $value);			

		return $postid;
  }
  
  /**
   * insertPostMeta
   *
   *
   */
	function insertPostMeta($postid, $key, $value) {
		global $wpdb;
		
		$link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD)
			OR die(mysql_error());
		$value = mysql_real_escape_string($value);
		$result = $wpdb->query( "INSERT INTO $wpdb->postmeta (post_id,meta_key,meta_value ) " 
					                . " VALUES ('$postid','$key','$value') ");
					
		return $wpdb->insert_id;		
	}
  
  /**
   * Parses an item content
   *
   * @param   $campaign       object    Campaign database object   
   * @param   $feed           object    Feed database object
   * @param   $item           object    SimplePieWPO_Item object
   */
  function parseItemContent(&$campaign, &$feed, &$item, $content = '')
  {  
    if ($content == '') $content = $item->get_content();
    
    // Caching
    if(get_option('wpo_cacheimages') || $campaign->cacheimages)
    {
      $images = WPOTools::parseImages($content);
      $urls = $images[2];
      
      if(sizeof($urls))
      {
        $this->log('Caching images');
        
        foreach($urls as $url)
        {
          $newurl = $this->cacheRemoteImage($url);
          if($newurl)
            $content = str_replace($url, $newurl, $content);
        } 
      }
    }
    
    // Template parse
    $vars = array(
      '{content}',
      '{title}',
      '{permalink}',
      '{feedurl}',
      '{feedtitle}',
      '{feedlogo}',
      '{campaigntitle}',
      '{campaignid}',
      '{campaignslug}'
    );
    
    $replace = array(
      $content,
      $item->get_title(),
      $item->get_link(),
      $feed->url,
      $feed->title,
      $feed->logo,
      $campaign->title,
      $campaign->id,
      $campaign->slug
    );
    
    $content = str_ireplace($vars, $replace, ($campaign->template) ? $campaign->template : '{content}');
    
    // Rewrite
    $rewrites = $this->getCampaignData($campaign->id, 'rewrites');
    foreach($rewrites as $rewrite)
    {
      $origin = $rewrite['origin']['search'];
      
      if(isset($rewrite['rewrite']))
      {
        $reword = isset($rewrite['relink']) 
                    ? '<a href="'. $rewrite['relink'] .'">' . $rewrite['rewrite'] . '</a>' 
                    : $rewrite['rewrite'];
        
        if($rewrite['origin']['regex'])
        {
          $content = preg_replace($origin, $reword, $content);
        } else
          $content = str_replace($origin, $reword, $content);
      } else if(isset($rewrite['relink'])) 
        $content = str_replace($origin, '<a href="'. $rewrite['relink'] .'">' . $origin . '</a>', $content);
    }
	$content = preg_replace("/\n/", " ", $content);
    
    return $content;
  }
  
  /**
   * Cache remote image
   *
   * @return string New url
   */
  function cacheRemoteImage($url)
  {
    $contents = @file_get_contents($url);
    $filename = substr(md5(time()), 0, 5) . '_' . basename($url);
    
    $cachepath = $this->cachepath;
    
    if(is_writable($cachepath) && $contents)
    { 
      file_put_contents($cachepath . '/' . $filename, $contents);
      return $this->pluginpath . '/' . get_option('wpo_cachepath') . '/' . $filename;
    }
    
    return false;
  }
   
  /**
   * Parses a feed with SimplePieWPO
   *
   * @param   boolean     $stupidly_fast    Set fast mode. Best for checks
   * @param   integer     $max              Limit of items to fetch
   * @return  SimplePieWPO_Item    Feed object
   **/
  function fetchFeed($url, $stupidly_fast = false, $max = 0)
  {
    # SimplePieWPO
    if(! class_exists('SimplePieWPO'))
      require_once( WPOINC . 'simplepie/simplepie.class.php' );
    
    $feed = new SimplePieWPO();
    $feed->enable_order_by_date(false); // thanks Julian Popov
    $feed->set_feed_url($url);
    $feed->set_item_limit($max);
    $feed->set_stupidly_fast($stupidly_fast);
	$feed->set_timeout(3600);
	$feed->strip_htmltags(false);
    $feed->enable_cache(false);    
    $feed->init();
    $feed->handle_content_type(); 
    
    return $feed;
  }
  
  /**
   * Returns all blog usernames (in form [user_login => display_name (user_login)] )
   *
   * @return array $usernames
   **/
  function getBlogUsernames()
  {
    $return = array();
    $users = get_users_of_blog();
    
    foreach($users as $user)
    {
      if($user->display_name == $user->user_login)
        $return[$user->user_login] = "{$user->display_name}";      
      else
        $return[$user->user_login] = "{$user->display_name} ({$user->user_login})";
    }
    
    return $return;
  }
  
  /**
   * Returns all data for a campaign
   *
   *
   */
  function getCampaignData($id, $section = null)
  {
    global $wpdb;
    $campaign = (array) $this->getCampaignById($id);
    
    if($campaign)
    {
      $campaign_data = $this->campaign_structure;
      
      // Main
      if(!$section || $section == 'main')
      {
        $campaign_data['main'] = array_merge($campaign_data['main'], $campaign);
        $userdata = get_userdata($campaign_data['main']['authorid']);
        $campaign_data['main']['author'] = $userdata->user_login; 
      }
      
      // Categories
      if(!$section || $section == 'categories')
      {
        $categories = $wpdb->get_results("SELECT * FROM {$this->db['campaign_category']} WHERE campaign_id = $id");
        foreach($categories as $category)
          $campaign_data['categories'][] = $category->category_id;
      }
      
      // Feeds
      if(!$section || $section == 'feeds')
      {
        $campaign_data['feeds']['edit'] = array();
        
        $feeds = $this->getCampaignFeeds($id);
        foreach($feeds as $feed)
          $campaign_data['feeds']['edit'][$feed->id] = $feed->url;
      }
      
      // Rewrites      
      if(!$section || $section == 'rewrites')
      {
        $rewrites = $wpdb->get_results("SELECT * FROM {$this->db['campaign_word']} WHERE campaign_id = $id");
        foreach($rewrites as $rewrite)
        {
          $word = array('origin' => array('search' => $rewrite->word, 'regex' => $rewrite->regex), 'rewrite' => $rewrite->rewrite_to, 'relink' => $rewrite->relink);
        
          if(! $rewrite->rewrite) unset($word['rewrite']);
          if(empty($rewrite->relink)) unset($word['relink']);
        
          $campaign_data['rewrites'][] = $word;
        }
      }

      if($section)
        return $campaign_data[$section];
        
      return $campaign_data; 
    }
    
    return false;
  }
  
  /**
   * Retrieves logs from database
   *
   *
   */       
  function getLogs($args = '') 
  {   
    global $wpdb;
    extract(WPOTools::getQueryArgs($args, array('orderby' => 'created_on', 
                                                'ordertype' => 'DESC', 
                                                'limit' => null,
                                                'page' => null,
                                                'perpage' => null))); 
    if(!is_null($page))
    {
      if($page == 0) $page = 1;
      $page--;
      
      $start = $page * $perpage;
      $end = $start + $perpage;
      $limit = "LIMIT {$start}, {$end}";
    }
  	
  	return $wpdb->get_results("SELECT * FROM {$this->db['log']} ORDER BY $orderby $ordertype $limit");
  }
           
  /**
   * Retrieves a campaign by its id
   *
   *
   */  
  function getCampaignById($id)
  {
    global $wpdb;
    
    $id = intval($id);
    return $wpdb->get_row("SELECT * FROM {$this->db['campaign']} WHERE id = $id");
  }
  
  /**
   * Retrieves a feed by its id
   *
   *
   */  
  function getFeedById($id)
  {
    global $wpdb;
    
    $id = intval($id);
    return $wpdb->get_row("SELECT * FROM {$this->db['campaign_feed']} WHERE id = $id");
  }
         
  /**
   * Retrieves campaigns from database
   *
   *
   */       
  function getCampaigns($args = '') 
  {   
    global $wpdb;
    extract(WPOTools::getQueryArgs($args, array('fields' => '*',      
                                                'search' => '',
                                                'orderby' => 'created_on', 
                                                'ordertype' => 'DESC', 
                                                'where' => '',
                                                'unparsed' => false,
                                                'limit' => null)));
  
  	if(! empty($search))
  	  $where .= " AND title LIKE '%{$search}%' ";
  	  
  	if($unparsed)
		$where .= " AND active = 1";
  	//  $where .= " AND active = 1 AND (frequency + UNIX_TIMESTAMP(lastactive)) < ". (current_time('timestamp', true) - get_option('gmt_offset') * 3600) . " ";
  	                              
  	$sql = "SELECT $fields FROM {$this->db['campaign']} WHERE 1 = 1 $where "
         . "ORDER BY $orderby $ordertype $limit";
         
  	return $wpdb->get_results($sql);
  }            
  
  /**
   * Retrieves feeds for a certain campaign
   *
   * @param   integer   $id     Campaign id
   */
  function getCampaignFeeds($id)
  {    
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$this->db['campaign_feed']} WHERE campaign_id = $id");
  }
  
  /**
   * Retrieves all WP posts for a certain campaign
   *
   * @param   integer   $id     Campaign id
   */
  function getCampaignPosts($id)
  {
    global $wpdb;
    return $wpdb->get_results("SELECT post_id FROM {$this->db['campaign_post']} WHERE campaign_id = $id ");          
  }
  
  /**
   * Adds a feed by url and campaign id
   *
   *
   */
  function addCampaignFeed($id, $feed)
  {
    global $wpdb;
    
    $simplepie = $this->fetchFeed($feed, false);
    //$url = $wpdb->escape($simplepie->subscribe_url());
    $url = $wpdb->escape(htmlspecialchars_decode($simplepie->subscribe_url()));
    
    // If it already exists, ignore it
    if(! $wpdb->get_var("SELECT id FROM {$this->db['campaign_feed']} WHERE campaign_id = $id AND url = '$url' "))
    {
      $wpdb->query(WPOTools::insertQuery($this->db['campaign_feed'], 
        array('url' => $url, 
              'title' => $wpdb->escape($simplepie->get_title()),
              'description' => $wpdb->escape($simplepie->get_description()),
              'logo' => $wpdb->escape($simplepie->get_image_url()),
              'campaign_id' => $id)
      ));  
      
      return $wpdb->insert_id;
    }
    
    return false;
  }
  
  
  /**
   * Retrieves feeds from database
   *         
   * @param   mixed   $args
   */  
  function getFeeds($args = '') 
  {
    global $wpdb;
    extract(WPOTools::getQueryArgs($args, array('fields' => '*',  
                                                'campid' => '',    
                                                'join' => false,
                                                'orderby' => 'created_on', 
                                                'ordertype' => 'DESC', 
                                                'limit' => null)));
  	
  	$sql = "SELECT $fields FROM {$this->db['campaign_feed']} cf ";
  	
  	if(!empty($join))
  	  $sql .= "INNER JOIN {$this->db['campaign']} camp ON camp.id = cf.campaign_id ";
  	     
  	if(!empty($campid))
  	  $sql .= "WHERE cf.campaign_id = $campid";
  	
  	return $wpdb->get_results($sql);  
  }        
  
  /**
   * Returns how many seconds left till reprocessing
   *
   * @return seconds
   **/
  function getCampaignRemaining(&$campaign, $gmt = 0)
  {    
    return mysql2date('U', $campaign->lastactive) + $campaign->frequency - current_time('timestamp', true) + ($gmt ? 0 : (get_option('gmt_offset') * 3600));
  }
  
  /**
   * Called when WP-o-Matic admin pages initialize.
   * 
   * 
   */
  function adminInit() 
  {                 
    auth_redirect();
    
    // force display of a certain section    
    $this->section = ($this->setup) ? ((isset($_REQUEST['s']) && $_REQUEST['s']) ? $_REQUEST['s'] : $this->sections[0]) : 'setup';
    
    if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
      die('Please switch to Firefox / Safari');
    
    wp_enqueue_script('prototype');
    wp_enqueue_script('wpoadmin', $this->tplpath . '/admin.js', array('prototype'), $this->version);
    
    if($this->section == 'list')
      wp_enqueue_script('listman');
          
	# Commented out this call because it was screwing with other plugins. Doesn't seem to affect anything, but not sure -- JFG.
    #if(WPOTools::isAjax())
    #{              
    #  $this->admin();
    #  exit;
    #}      
  }
  
  /**
   * Called by admin-header.php
   *
   * @return void
   **/
  function adminHead()
  {
    $this->admin = true;
  }
  
  /**
   * Shows a warning box for setup
   *
   *
   */
  function adminWarning()
  {
    if(! $this->setup && $this->section != 'setup')
    {
      echo "<div id='wpo-warning' class='updated fade-ff0000'><p>" . sprintf(__('Please <a href="%s">click here</a> to setup and configure WP-o-Matic.', 'wpomatic'), $this->adminurl . '&amp;s=setup') . "</p></div>

  		  <style type='text/css'>
  		    " . ($this->newadmin
  		    ?
  		    "
  		    #wpo-warning { position: absolute; top: 4em; right: 0 }
  		    "
  		    :
  		    "
  		    #adminmenu { margin-bottom: 5em; }
  		    #wpo-warning { position: absolute; top: 6.8em; }
  		    "
  		    )
  		    .
  		    "
  		  </style>
  	  "; 
    }
  }
  
  /**
   * Executes the current section method.
   * 
   *
   */
  function admin()
  {                       
    if(in_array($this->section, $this->sections))
    {
      $method = 'admin' . ucfirst($this->section);
      $this->$method();        
    }  
  }
    
  /**
   * Adds the WP-o-Matic item to menu 
   *           
   * 
   */
  function adminMenu()
  {
    add_submenu_page('options-general.php', 'WP-o-Matic', 'WP-o-Matic', 'administrator', basename(__FILE__), array(&$this, 'admin'));
  }                    
    
  /**
   * Outputs the admin header in a template 
   *            
   * 
   */
  function adminHeader()
  {              
    $current = array();
                    
    foreach($this->sections as $s)
      $current[$s] = ($s == $this->section) ? 'class="current"' : '';
    
    include(WPOTPL . 'header.php');
  }                                                                  
                                
  /**
   * Outputs the admin footer in a template
   *            
   * 
   */
  function adminFooter()
  {
    include(WPOTPL . 'footer.php');
  }
    
  /**
   * Home section
   *
   *
   */
  function adminHome()
  {                                   
    $logging = get_option('wpo_log');
    $logs = $this->getLogs('limit=7');    
    $nextcampaigns = $this->getCampaigns('fields=id,title,lastactive,frequency&limit=5' .
                                          '&where=active=1&orderby=UNIX_TIMESTAMP(lastactive)%2Bfrequency&ordertype=ASC');
    $lastcampaigns = $this->getCampaigns('fields=id,title,lastactive,frequency&limit=5&where=UNIX_TIMESTAMP(lastactive)>0&orderby=lastactive');
    $campaigns = $this->getCampaigns('fields=id,title,count&limit=5&orderby=count');
    
    include(WPOTPL . 'home.php');
  }      
    
  
  /** 
   * Setup admin
   *
   *
   */
  function adminSetup()
  {
    if(isset($_POST['dosetup']))
    {
      update_option('wpo_unixcron', isset($_REQUEST['option_unixcron']));
      update_option('wpo_setup', 1);
      
      $this->adminHome();
      exit;
    }
    
    # Commands
    $prefix = $this->getCommand();
    $nocommand = ! file_exists($prefix);
                
    $safe_mode = ini_get('safe_mode');
        
    $command = $this->cron_command;
    $url = $this->cron_url;
        
    include(WPOTPL . 'setup.php');
  }     

  /**
   * List campaigns section
   *
   *
   */
  function adminList()
  {                                                                                
    global $wpdb;
    
    if(isset($_REQUEST['q']))
    {
      $q = $_REQUEST['q'];
      $campaigns = $this->getCampaigns('search=' . $q);
    } else
      $campaigns = $this->getCampaigns('orderby=CREATED_ON');
  
    include(WPOTPL . 'list.php');
  }
            
  /**
   * Add campaign section
   *
   *
   */
  function adminAdd()
  {                
    $data = $this->campaign_structure;
  
    if(isset($_REQUEST['campaign_add']))
    {
      check_admin_referer('wpomatic-edit-campaign');
      
      if($this->errno)
        $data = $this->campaign_data;
      else
        $addedid = $this->adminProcessAdd();   
    }
    
    $author_usernames = $this->getBlogUsernames();   
    $campaign_add = true;
    include(WPOTPL . 'edit.php');
  }     
  
  /**
   * Edit campaign section
   *
   *
   */
  function adminEdit()
  {    
    $id = intval($_REQUEST['id']);
    if(!$id) die("Can't be called directly");
    
    if(isset($_REQUEST['campaign_edit']))
    {
      check_admin_referer('wpomatic-edit-campaign');
            
      $data = $this->campaign_data;
      $submitted = true;
      
      if(! $this->errno) 
      {
        $this->adminProcessEdit($id);
        $edited = true;
        $data = $this->getCampaignData($id);
      }
    } else      
      $data = $this->getCampaignData($id);    
    
    $author_usernames = $this->getBlogUsernames();
    $campaign_edit = true;
    
    include(WPOTPL . 'edit.php');
  }
  
  function adminEditCategories(&$data, $parent = 0, $level = 0, $categories = 0)
  {    
  	if ( !$categories )
  		$categories = get_categories(array('hide_empty' => 0));

    if(function_exists('_get_category_hierarchy'))
      $children = _get_category_hierarchy();
    elseif(function_exists('_get_term_hierarchy'))
      $children = _get_term_hierarchy('category');
    else
      $children = array();

  	if ( $categories ) {
  		ob_start();
  		foreach ( $categories as $category ) {
  			if ( $category->parent == $parent) {
  				echo "\t" . _wpo_edit_cat_row($category, $level, $data);
  				if ( isset($children[$category->term_id]) )
  					$this->adminEditCategories($data, $category->term_id, $level + 1, $categories );
  			}
  		}
  		$output = ob_get_contents();
  		ob_end_clean();

  		echo $output;
  	} else {
  		return false;
  	}
    	
  }
  
  /**
   * Resets a campaign (sets post count to 0, forgets last parsed post)
   *
   *
   * @todo Make it ajax-compatible here and add javascript code
   */
  function adminReset()
  {
    global $wpdb;
    
    $id = intval($_REQUEST['id']);

    if(! defined('DOING_AJAX'))
      check_admin_referer('reset-campaign_'.$id);    
      
    // Reset count and lasactive
    $wpdb->query(WPOTools::updateQuery($this->db['campaign'], array(
      'count' => 0,
      'lastactive' => 0
    ), "id = $id"));

    $wpdb->query("DELETE FROM {$this->db['campaign_post']} WHERE campaign_id = $id");
      
    // Reset feeds hashes, count, and lasactive
    foreach($this->getCampaignFeeds($id) as $feed)
    {
      $wpdb->query(WPOTools::updateQuery($this->db['campaign_feed'], array(
        'count' => 0,
        'lastactive' => 0,
        'hash' => ''
      ), "id = {$feed->id}"));

    }
      
    if(defined('DOING_AJAX'))
      die('1');
    else
      $this->adminList();
  }
  
  /**
   * Deletes a campaign
   *
   *
   */
  function adminDelete()
  {
    global $wpdb;
    
    $id = intval($_REQUEST['id']);

    // If not called through admin-ajax.php
    if(! defined('DOING_AJAX'))
      check_admin_referer('delete-campaign_'.$id);    
      
    $wpdb->query("DELETE FROM {$this->db['campaign']} WHERE id = $id");
    $wpdb->query("DELETE FROM {$this->db['campaign_feed']} WHERE campaign_id = $id");
    $wpdb->query("DELETE FROM {$this->db['campaign_word']} WHERE campaign_id = $id");
    $wpdb->query("DELETE FROM {$this->db['campaign_category']} WHERE campaign_id = $id");
    
    if(defined('DOING_AJAX'))
      die('1');
    else
      $this->adminList();
  }
  
  /**
   * Options section 
   *
   *
   */
  function adminOptions()
  {  
              
    if(isset($_REQUEST['update']))
    {              
      update_option('wpo_unixcron',     isset($_REQUEST['option_unixcron']));
      update_option('wpo_log',          isset($_REQUEST['option_logging']));
      update_option('wpo_log_stdout',   isset($_REQUEST['option_logging_stdout']));      
      update_option('wpo_cacheimages',  isset($_REQUEST['option_caching']));
      update_option('wpo_cachepath',    rtrim($_REQUEST['option_cachepath'], '/'));
      
      $updated = 1;
    }
    
    if(!is_writable($this->cachepath))
      $not_writable = true;
    
    include(WPOTPL . 'options.php');
  }
   
  /**
   * Import section
   *
   *
   */
  function adminImport()
  {  
    global $wpdb;
    
    @session_start();
    
    if(!$_POST) unset($_SESSION['opmlimport']);
    
    if(isset($_FILES['importfile']) || $_POST)
      check_admin_referer('import-campaign');   
           
    if(!isset($_SESSION['opmlimport']))
    {
      if(isset($_FILES['importfile']))      
      {
        if(is_uploaded_file($_FILES['importfile']['tmp_name']))
          $file = $_FILES['importfile']['tmp_name'];
        else
          $file = false;
      } else if(isset($_REQUEST['importurl']))
      {
        $fromurl = true;
        $file = $_REQUEST['importurl'];
      }  
    }
    
    if(isset($file) || ($_POST && isset($_SESSION['opmlimport'])) )
    {               
      require_once( WPOINC . 'xmlparser.class.php' );
    
      $contents = (isset($file) ? @file_get_contents($file) : $_SESSION['opmlimport']);
      $_SESSION['opmlimport'] = $contents;    
    
      # Get OPML data
      $opml = new XMLParser($contents);
      $opml = $opml->getTree();                                             
              
      # Check that it is indeed opml      
      if(is_array($opml) && isset($opml['OPML'])) 
      {                            
        $opml = $opml['OPML'][0];
        
        $title = isset($opml['HEAD'][0]['TITLE'][0]['VALUE']) 
                  ? $opml['HEAD'][0]['TITLE'][0]['VALUE'] 
                  : null;          
                  
        $opml = $opml['BODY'][0];
                   
        $success = 1;
        
        # Campaigns dropdown
        $campaigns = array();
        foreach($this->getCampaigns() as $campaign)
          $campaigns[$campaign->id] = $campaign->title;
      }          
      else 
        $import_error = 1;
    }    
    
    $this->adminImportProcess();
      
    include(WPOTPL . 'import.php');
  }      
  
  /**
   * Import process
   *
   *
   */
  function adminImportProcess()
  {
    global $wpdb;
    
    if(isset($_REQUEST['add']))
    {
      if(!isset($_REQUEST['feed']))
        $add_error = __('You must select at least one feed', 'wpomatic');
      else 
      {
        switch($_REQUEST['import_mode'])
        {
          // Several campaigns
          case '1':
            $created_campaigns = array();

            foreach($_REQUEST['feed'] as $campaignid => $feeds)
            {
              if(!in_array($campaignid, $created_campaigns))
              {
                // Create campaign
                $title = $_REQUEST['campaign'][$campaignid];
                if(!$title) continue;
                
                $slug = WPOTools::stripText($title);
                $wpdb->query("INSERT INTO {$this->db['campaign']} (title, active, slug, lastactive, count) VALUES ('$title', 0, '$slug', 0, 0) ");
                $created_campaigns[] = $wpdb->insert_id;  
              
                // Add feeds
                foreach($feeds as $feedurl => $yes)
                  $this->addCampaignFeed($campaignid, urldecode($feedurl));
                  
              }            
            }
            
            $this->add_success = __('Campaigns added successfully. Feel free to edit them', 'wpomatic');
            
            break;
          
          // All feeds into an existing campaign
          case '2':
            $campaignid = $_REQUEST['import_custom_campaign'];
            
            foreach($_REQUEST['feed'] as $cid => $feeds)
            {
              // Add feeds              
              foreach($feeds as $feedurl => $yes)
                $this->addCampaignFeed($campaignid, urldecode($feedurl));
            }  
            
            $this->add_success = sprintf(__('Feeds added successfully. <a href="%s">Edit campaign</a>', 'wpomatic'), $this->adminurl . '&s=edit&id=' . $campaignid);
            
            break;
            
          // All feeds into new campaign
          case '3':
            $title = $_REQUEST['import_new_campaign'];
            $slug = WPOTools::stripText($title);
            $wpdb->query("INSERT INTO {$this->db['campaign']} (title, active, slug, lastactive, count) VALUES ('$title', 0, '$slug', 0, 0) ");
            $campaignid = $wpdb->insert_id;
            
            // Add feeds
            foreach($_REQUEST['feed'] as $cid => $feeds)
            {
              // Add feeds              
              foreach($feeds as $feedurl => $yes)
                $this->addCampaignFeed($campaignid, urldecode($feedurl));
            }
            
            $this->add_success = sprintf(__('Feeds added successfully. <a href="%s">Edit campaign</a>', 'wpomatic'), $this->adminurl . '&s=edit&id=' . $campaignid);
            
            break;
        }
      }
    }
  }
  
  /**
   * Export
   *
   *
   */
  function adminExport()
  {    
    if(isset($this->export_error))
      $error = $this->export_error;
    
    $campaigns = $this->getCampaigns();
    
    include(WPOTPL . 'export.php');    
  }
  
  /** 
   * Export process
   *
   *
   */
  function adminExportProcess()
  {
    if($_POST)
    {
      if(!isset($_REQUEST['export_campaign']))
      {
        $this->export_error = __('Please select at least one campaign', 'wpomatic');
      } else 
      {
        $campaigns = array();
        foreach($_REQUEST['export_campaign'] as $cid)
        {
          $campaign = $this->getCampaignById($cid);
          $campaign->feeds = (array) $this->getCampaignFeeds($cid);
          $campaigns[] = $campaign;
        }
        
        header("Content-type: text/x-opml");
        header('Content-Disposition: attachment; filename="wpomatic.opml"');
        
        include(WPOTPL . 'export.opml.php');
        exit;
      }
    }
  }
  
  /**
   * Tests a feed
   *
   *
   */
  function adminTestfeed()
  {
    if(!isset($_REQUEST['url'])) return false;
    
    $url = $_REQUEST['url'];
    $feed = $this->fetchFeed($url, false);
    $works = ! $feed->error(); // if no error returned
    
    if(defined('DOING_AJAX')){
      echo intval($works);
      die();
    } else
      include(WPOTPL . 'testfeed.php');
  }
  
  /**
   * Forcedfully processes a campaign
   *
   *
   */
  function adminForcefetch()
  {
    $cid = intval($_REQUEST['id']);
    
    if(! defined('DOING_AJAX'))
      check_admin_referer('forcefetch-campaign_'.$cid);    
    
    $this->forcefetched = $this->processCampaign($cid);    
    
    if(defined('DOING_AJAX'))
      die('1');
    else
      $this->adminList();
  }
  
  /**
   * Checks submitted campaign edit form for errors
   * 
   *
   * @return array  errors 
   */
  function adminCampaignRequest()
  {  
    global $wpdb;
    
    # Main data
    $this->campaign_data = $this->campaign_structure;
    $this->campaign_data['main'] = array(      
        'title'         => $_REQUEST['campaign_title'],
        'active'        => isset($_REQUEST['campaign_active']),
        'slug'          => $_REQUEST['campaign_slug'],
        'template'      => (isset($_REQUEST['campaign_templatechk'])) 
                            ? $_REQUEST['campaign_template'] : null,
        'frequency'     => intval($_REQUEST['campaign_frequency_d']) * 86400 
                          + intval($_REQUEST['campaign_frequency_h']) * 3600 
                          + intval($_REQUEST['campaign_frequency_m']) * 60,
        'cacheimages'   => (int) isset($_REQUEST['campaign_cacheimages']),
        'feeddate'      => (int) isset($_REQUEST['campaign_feeddate']),
        'posttype'      => $_REQUEST['campaign_posttype'],
        'author'        => sanitize_user($_REQUEST['campaign_author']),
        'comment_status' => $_REQUEST['campaign_commentstatus'],
        'allowpings'    => (int) isset($_REQUEST['campaign_allowpings']),
        'dopingbacks'   => (int) isset($_REQUEST['campaign_dopingbacks']),
        'max'           => intval($_REQUEST['campaign_max']),
        'linktosource'  => (int) isset($_REQUEST['campaign_linktosource'])
    );
    
    // New feeds     
    foreach($_REQUEST['campaign_feed']['new'] as $i => $feed) 
    {
      $feed = trim($feed);
      
      if(!empty($feed))
      {        
        if(!isset($this->campaign_data['feeds']['new']))
          $this->campaign_data['feeds']['new'] = array();
          
        $this->campaign_data['feeds']['new'][$i] = $feed;
      }
    } 
    
    // Existing feeds to delete
    if(isset($_REQUEST['campaign_feed']['delete']))
    {
      $this->campaign_data['feeds']['delete'] = array();
      
      foreach($_REQUEST['campaign_feed']['delete'] as $feedid => $yes)
        $this->campaign_data['feeds']['delete'][] = intval($feedid);
    }
    
    // Existing feeds.
    if(isset($_REQUEST['id']))
    {
      $this->campaign_data['feeds']['edit'] = array();
      foreach($this->getCampaignFeeds(intval($_REQUEST['id'])) as $feed)
        $this->campaign_data['feeds']['edit'][$feed->id] = $feed->url;
    }
    
    // Categories
    if(isset($_REQUEST['campaign_categories']))
    {
      foreach($_REQUEST['campaign_categories'] as $category)
      {
        $id = intval($category);
        $this->campaign_data['categories'][] = $category;
      }
    }
    
    # New categories
    if(isset($_REQUEST['campaign_newcat']))
    {
      foreach($_REQUEST['campaign_newcat'] as $k => $on)
      {
        $catname = $_REQUEST['campaign_newcatname'][$k];
        if(!empty($catname))
        {
          if(!isset($this->campaign_data['categories']['new']))
            $this->campaign_data['categories']['new'] = array();
          
          $this->campaign_data['categories']['new'][] = $catname;
        }
      }
    }
    
    // Rewrites
    if(isset($_REQUEST['campaign_word_origin']))
    {
      foreach($_REQUEST['campaign_word_origin'] as $id => $origin_data)
      {
        $rewrite = isset($_REQUEST['campaign_word_option_rewrite']) 
                && isset($_REQUEST['campaign_word_option_rewrite'][$id]); 
        $relink = isset($_REQUEST['campaign_word_option_relink']) 
                && isset($_REQUEST['campaign_word_option_relink'][$id]);  

        if($rewrite || $relink)
        {
          $rewrite_data = trim($_REQUEST['campaign_word_rewrite'][$id]);
          $relink_data = trim($_REQUEST['campaign_word_relink'][$id]);
        
          // Relink data field can't be empty
          if(($relink && !empty($relink_data)) || !$relink) 
          {
            $regex = isset($_REQUEST['campaign_word_option_regex']) 
                  && isset($_REQUEST['campaign_word_option_regex'][$id]);

            $data = array();        
            $data['origin'] = array('search' => $origin_data, 'regex' => $regex);
            
            if($rewrite)
              $data['rewrite'] = $rewrite_data;
              
            if($relink)
              $data['relink'] = $relink_data;
              
            $this->campaign_data['rewrites'][] = $data; 
          }  
        }
      }
    }
    
    $errors = array('basic' => array(), 'feeds' => array(), 'categories' => array(), 
                    'rewrite' => array(), 'options' => array());
    
    # Main    
    if(empty($this->campaign_data['main']['title']))
    {
      $errors['basic'][] = __('You have to enter a campaign title', 'wpomatic');
      $this->errno++;
    }
    
    # Feeds
    $feedscount = 0;
    
    if(isset($this->campaign_data['feeds']['new'])) $feedscount += count($this->campaign_data['feeds']['new']);
    if(isset($this->campaign_data['feeds']['edit'])) $feedscount += count($this->campaign_data['feeds']['edit']);
    if(isset($this->campaign_data['feeds']['delete'])) $feedscount -= count($this->campaign_data['feeds']['delete']);
    
    if(!$feedscount)
    {
      $errors['feeds'][] = __('You have to enter at least one feed', 'wpomatic');
      $this->errno++;
    } else {  
      if(isset($this->campaign_data['feeds']['new']))    
      {
        foreach($this->campaign_data['feeds']['new'] as $feed)
        {
          $simplepie = $this->fetchFeed($feed, false);
          if($simplepie->error())
          {
            $errors['feeds'][] = sprintf(__('Feed <strong>%s</strong> could not be parsed (SimplePieWPO said: %s)', 'wpomatic'), $feed, $simplepie->error());
            $this->errno++;
          }          
        }  
      }
    }
    
    # Categories
    if(! sizeof($this->campaign_data['categories']))
    {
      $errors['categories'][] = __('Select at least one category', 'wpomatic');
      $this->errno++;
    }
    
    # Rewrite
    if(sizeof($this->campaign_data['rewrites']))
    {
      foreach($this->campaign_data['rewrites'] as $rewrite)
      {
        if($rewrite['origin']['regex'])
        {
          if(false === @preg_match($rewrite['origin']['search'], ''))
          {
            $errors['rewrites'][] = __('There\'s an error with the supplied RegEx expression', 'wpomatic');         
            $this->errno++;
          }
        }
      }
    }
    
    # Options    
    if(! get_userdatabylogin($this->campaign_data['main']['author']))
    {
      $errors['options'][] = __('Author username not found', 'wpomatic');
      $this->errno++;
    }
    
    if(! $this->campaign_data['main']['frequency'])
    {
      $errors['options'][] = __('Selected frequency is not valid', 'wpomatic');
      $this->errno++;
    }
    
    if(! ($this->campaign_data['main']['max'] === 0 || $this->campaign_data['main']['max'] > 0))
    {
      $errors['options'][] = __('Max items should be a valid number (greater than zero)', 'wpomatic');
      $this->errno++;
    }
    
    if($this->campaign_data['main']['cacheimages'] && !is_writable($this->cachepath))
    {
      $errors['options'][] = sprintf(__('Cache path (in <a href="%s">Options</a>) must be writable before enabling image caching.', 'wpomatic'), $this->adminurl . '&s=options' );
      $this->errno++;
    }
    
    $this->errors = $errors;
  }
  
  /**
   * Creates a campaign, and runs processEdit. If processEdit fails, campaign is removed
   *
   * @return campaign id if created successfully, errors if not
   */
  function adminProcessAdd()
  {
    global $wpdb;
    
    // Insert a campaign with dumb data
    $wpdb->query(WPOTools::insertQuery($this->db['campaign'], array('lastactive' => 0, 'count' => 0)));
    $cid = $wpdb->insert_id;
    
    // Process the edit
    $this->campaign_data['main']['lastactive'] = 0;
    $this->adminProcessEdit($cid);    
    return $cid;
  }
 
  /**
   * Cleans everything for the given id, then redoes everything
   *
   * @param integer $id           The id to edit
   */
  function adminProcessEdit($id)
  {
    global $wpdb;
    
    // If we need to execute a tool action we stop here
    if($this->adminProcessTools()) return;    
    
    // Delete all to recreate
    $wpdb->query("DELETE FROM {$this->db['campaign_word']} WHERE campaign_id = $id");
    $wpdb->query("DELETE FROM {$this->db['campaign_category']} WHERE campaign_id = $id");    
    
    // Process categories    
    # New
    if(isset($this->campaign_data['categories']['new']))
    {
      foreach($this->campaign_data['categories']['new'] as $category)
        $this->campaign_data['categories'][] = wp_insert_category(array('cat_name' => $category));
      
      unset($this->campaign_data['categories']['new']);
    }
    
    # All
    foreach($this->campaign_data['categories'] as $category)
    {
      // Insert
      $wpdb->query(WPOTools::insertQuery($this->db['campaign_category'], 
        array('category_id' => $category, 
              'campaign_id' => $id)
      ));
    }
    
    // Process feeds
    # New
    if(isset($this->campaign_data['feeds']['new']))
    {
      foreach($this->campaign_data['feeds']['new'] as $feed)
        $this->addCampaignFeed($id, $feed);        
    }
    
    # Delete
    if(isset($this->campaign_data['feeds']['delete']))
    {
      foreach($this->campaign_data['feeds']['delete'] as $feed)
        $wpdb->query("DELETE FROM {$this->db['campaign_feed']} WHERE id = $feed ");
    }
    
    // Process words
    foreach($this->campaign_data['rewrites'] as $rewrite)
    {
      $wpdb->query(WPOTools::insertQuery($this->db['campaign_word'], 
        array('word' => $rewrite['origin']['search'], 
              'regex' => $rewrite['origin']['regex'],
              'rewrite' => isset($rewrite['rewrite']),
              'rewrite_to' => isset($rewrite['rewrite']) ? $rewrite['rewrite'] : '',
              'relink' => isset($rewrite['relink']) ? $rewrite['relink'] : null,
              'campaign_id' => $id)
      ));
    }
    
    // Main 
    $main = $this->campaign_data['main'];

    // Fetch author id
    $author = get_userdatabylogin($this->campaign_data['main']['author']);
    $main['authorid'] = $author->ID;
    unset($main['author']);
    
    // Query
    $query = WPOTools::updateQuery($this->db['campaign'], $main, 'id = ' . intval($id));    
    $wpdb->query($query);
  }
  
  /**
   * Processes edit campaign tools actions
   *
   *
   */
  function adminProcessTools()
  {
    global $wpdb;
        
    $id = intval($_REQUEST['id']);
    
    if(isset($_REQUEST['tool_removeall']))
    {      
      $posts = $this->getCampaignPosts($id);
      
      foreach($posts as $post)
      {
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID = {$post->post_id} ");
      }
            
      // Delete log
      $wpdb->query("DELETE FROM {$this->db['campaign_post']} WHERE campaign_id = {$id} ");
      
      // Update feed and campaign posts count
      $wpdb->query(WPOTools::updateQuery($this->db['campaign'], array('count' => 0), "id = {$id}"));
      $wpdb->query(WPOTools::updateQuery($this->db['campaign_feed'], array('hash' => 0, 'count' => 0), "campaign_id = {$id}"));
      
      $this->tool_success = __('All posts removed', 'wpomatic');
      return true;
    }
    
    if(isset($_REQUEST['tool_changetype']))
    {
      $this->adminUpdateCampaignPosts($id, array(
		  'post_status' => $wpdb->escape($_REQUEST['campaign_tool_changetype'])
	  ));

      $this->tool_success = __('Posts status updated', 'wpomatic');
      return true;
    }
    
    if(isset($_REQUEST['tool_changeauthor']))
    {
      $author = get_userdatabylogin($_REQUEST['campaign_tool_changeauthor']);

      if($author)
      {
        $authorid = $author->ID;      
        $this->adminUpdateCampaignPosts($id, array('post_author' => $authorid)); 
      } else {
        $this->errno = 1;
        $this->errors = array('tools' => array(sprintf(__('Author %s not found', 'wpomatic'), esc_attr($_REQUEST['campaign_tool_changeauthor']))));
      }
      
      $this->tool_success = __('Posts status updated', 'wpomatic');
      return true;
    }
    
    return false;
  }
  
  function adminUpdateCampaignPosts($id, $properties)
  {
    global $wpdb;
    
    $posts = $this->getCampaignPosts($id);
    
    foreach($posts as $post)
      $wpdb->query(WPOTools::updateQuery($wpdb->posts, $properties, "ID = {$post->id}"));
  }
  
  
  /** 
   * Show logs
   *
   *
   */  
  function adminLogs()
  {
    global $wpdb;
    
    // Clean logs?
    if(isset($_REQUEST['clean_logs']))
    {
      check_admin_referer('clean-logs');
      $wpdb->query("DELETE FROM {$this->db['log']} WHERE 1=1 ");
    }
    
    // Logs to show per page
    $logs_per_page = 20;
        
    $page = isset($_REQUEST['p']) ? intval($_REQUEST['p']) : 0;
    $total = $wpdb->get_var("SELECT COUNT(*) as cnt FROM {$this->db['log']} ");
    $logs = $this->getLogs("page={$page}&perpage={$logs_per_page}");
    
    $paging = paginate_links(array(
      'base' => $this->adminurl . '&s=logs&%_%',
      'format' => 'p=%#%',
      'total' => ceil($total / $logs_per_page),
      'current' => $page,
      'end_size' => 3
    ));
    
    include(WPOTPL . 'logs.php');
  }
}        

$wpomatic = & new WPOMatic();
