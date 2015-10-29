<?php

/**
 * Plugin Name: CSV Twitter Importer for OZH Twitter Archiver
 * Plugin URI: http://www.jackreichert.com/2015/07/02/how-to-jsonp-ajax-to-ssl-in-wordpress-an-easier-way/
 * Description: A paradigm for easy AJAX over SSL in WordPress using JSONP.
 * Version: 0.1
 * Author: jackreichert
 * Author URI: http://www.jackreichert.com/
 * License: GPL3
 */
class CSV_Twitter_Importer {
	function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'wp_ajax_import_tweets', array( $this, 'wp_jsonp_func' ) );
		add_action( 'init', array( $this, 'create_tweet_type' ) );
		add_action( 'init', array( $this, 'create_tweet_tax' ) );
		add_filter( 'the_content', array( $this, 'return_oembed' ) );
		add_filter( 'ozh_ta_insert_tweets_post', array( $this, 'import_as_tweet' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'wp_jsonp_scripts' ) );
		add_action( 'set_object_terms', array( $this, 'post_insert_term' ), 10, 4 );
		add_action( 'save_post', array( $this, 'post_insert_tweet' ), 10, 2 );
	}

	// enqueue scripts
	function wp_jsonp_scripts() {
		wp_enqueue_script( 'wp_jsonp_script', plugins_url( '/jsonp.js', __FILE__ ), array( 'jquery' ) );
		wp_localize_script( 'wp_jsonp_script', 'wp_jsonp_vars', array(
			'ajaxurl'     => admin_url( 'admin-ajax.php' ),
			'wpAJAXNonce' => wp_create_nonce( 'wpAJAX-nonce' )
		) );
	}

	function import_as_tweet( $post ) {
		$post['post_type'] = 'tweet';

		return $post;
	}

	// Register Custom Post Type
	function create_tweet_type() {
		$labels = array(
			'name'          => _x( 'Tweets', 'Post Type General Name', 'text_domain' ),
			'singular_name' => _x( 'Tweet', 'Post Type Singular Name', 'text_domain' )
		);
		$args   = array(
			'label'               => __( 'Tweet', 'text_domain' ),
			'description'         => __( 'Tweet', 'text_domain' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'author', 'custom-fields' ),
			'taxonomies'          => array( 'hashtag' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'page',
		);
		register_post_type( 'tweet', $args );

	}

	// Register Custom Taxonomy
	function create_tweet_tax() {
		$labels = array(
			'name'          => _x( 'Hashtags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name' => _x( 'Hashtag', 'Taxonomy Singular Name', 'text_domain' )
		);
		$args   = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => true,
		);
		register_taxonomy( 'hashtag', array( 'tweet' ), $args );

		$labels = array(
			'name'          => _x( 'Mentions', 'Taxonomy General Name', 'text_domain' ),
			'singular_name' => _x( 'Mention', 'Taxonomy Singular Name', 'text_domain' )
		);
		$args   = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => true,
		);
		register_taxonomy( 'mention', array( 'tweet' ), $args );

	}

	function return_oembed( $content ) {
		if ( is_singular( 'tweet' ) || is_post_type_archive( 'tweet' ) ) {
//			global $post, $ozh_ta;
//			$embed = wp_oembed_get( "http://twitter.com/" . $ozh_ta['screen_name'] . "/status/" . get_post_meta( $post->ID, 'ozh_ta_id', true ) );

//			return ( "" != $embed ) ? $embed : $content;
			return $content;
		}

		return $content;
	}

	function add_admin_page() {
		$page = add_options_page( 'Import Tweets', 'Import Tweets', 'manage_options', 'ozh_ta_csv', array(
			$this,
			'ozh_ta_csv_page'
		) );
	}

	function wp_jsonp_func() {
		if ( ! wp_verify_nonce( $_GET['ajaxSSLNonce'], 'wpAJAX-nonce' ) ) {
			error_log( 'Busted!' );
		}
		$response = array();
		$method   = $_GET['method'];

		/**
		 * Send what should be processed here.
		 * Note: since you are sending the method via ajax, you MUST validate your data.
		 * If you don't you probably should check your SQL queries because you probably don't sanitize those either.
		 */
		switch ( $method ) {
			case 'import_tweets':
				// if you get "hello world back, then it worked"
				global $wpdb;
				$tids       = [ ];
				$orig_count = 0;
				if ( is_array( $_GET["params"]["variable"] ) ) {
					$orig_count = count( $_GET["params"]["variable"] );
					foreach ( $_GET["params"]["variable"] as $tid ) {
						$sql = $wpdb->prepare( "SELECT post_id
                            FROM `$wpdb->postmeta`
                            WHERE `meta_key` = 'ozh_ta_id' AND `meta_value` = '%d' LIMIT 0,1", $tid ); // Yeah, trusting api.twitter.com so we don't sanitize the SQL query, yeeeha

						if ( ! $wpdb->get_var( $sql ) && intval( $tid ) ) {
							$tids[] = $tid;
						}
					}
				}

				if ( count( $tids ) > 0 ) {
					$response = $this->ozh_ta_import_bunch_o_tweets( implode( ",", $tids ) );
				} else {
					$response = "No new tweets in this batch";
				}
				break;
			default:
				$response = "You didn't send any methods to process your variables?!";
				break;
		}
		// response output
		header( "content-type: text/javascript; charset=utf-8" ); // We're sending back a javascript function, remember?
		header( "access-control-allow-origin: *" ); // This is needed for JSONP.
		echo htmlspecialchars( $_GET['callback'] ) . '(' . json_encode( $response ) . ')'; // jQuery set up the callback for us.
		// IMPORTANT: don't forget to "exit"
		exit;

	}

	function ozh_ta_import_bunch_o_tweets( $id ) {
		if ( file_exists( plugin_dir_path( __FILE__ ) . '../ozh-tweet-archiver/inc/import.php' ) ) {
			include( plugin_dir_path( __FILE__ ) . '../ozh-tweet-archiver/inc/import.php' );
			if ( function_exists( 'ozh_ta_get_single_tweet' ) ) {
				if ( $tweets = self::get_single_tweet( $id ) ) {
					if ( ! is_array( $tweets ) ) {
						$tweets = array( $tweets );
					}
					$response = ozh_ta_insert_tweets( $tweets );

					foreach ( $tweets as $i => $tweet ) {
						$this->process_mentions_hashtags( $tweet );
					}

					return $response;
				} else {
					return " -- couldn't get tweets -- ";
				}
			} else {
				return " -- function doesn't exist -- ";
			}
		} else {
			return " -- plugin doesn't exist -- ";
		}

	}

	static function get_single_tweet( $id ) {
		global $ozh_ta;

		if ( ! ozh_ta_is_configured() ) {
			ozh_ta_debug( 'Config incomplete, cannot import tweets' );

			return false;
		}

		$api     = 'https://api.twitter.com/1.1/statuses/lookup.json?id=' . $id;
		$headers = array(
			'Authorization' => 'Bearer ' . $ozh_ta['access_token'],
		);

		ozh_ta_debug( "Polling $api" );

		$response = wp_remote_get( $api, array(
			'headers' => $headers,
			'timeout' => 10
		) );

		$tweet = json_decode( wp_remote_retrieve_body( $response ) );

		if ( isset( $tweet->errors ) ) {
			ozh_ta_debug( "Error with tweet #$id : " . $tweet->errors[0]->message );

			return false;
		}

		return $tweet;
	}

	function process_mentions_hashtags( $tweet ) {
		$post_id      = $this->get_postId_for_tweet( (string) $tweet->id_str );
		$has_hashtags = count( $tweet->entities->hashtags ) > 0;

		if ( $has_hashtags ) {
			$hashtags = ozh_ta_get_hashtags( $tweet );
			$hashtags = implode( ', ', $hashtags );
			wp_set_post_terms( $post_id, $hashtags, 'hashtag' );
		}

		if ( isset( $tweet->entities->user_mentions ) && $mentions = $tweet->entities->user_mentions ) {
			$m = [ ];
			foreach ( $mentions as $mention ) {
				$m[] = $mention->screen_name;
			}
			if ( 0 < count( $mentions ) ) {
				$mentions = implode( ', ', $m );
				wp_set_post_terms( $post_id, $mentions, 'mention' );
			}
		}
	}

	function get_postId_for_tweet( $tweetId ) {
		global $wpdb;
		$tid = preg_replace( "/[^A-Za-z0-9]/", '', $tweetId );
		$sql = $wpdb->prepare( "SELECT post_id
		        FROM `$wpdb->postmeta`
				WHERE `meta_key` = 'ozh_ta_id' AND `meta_value` = '%d' LIMIT 0,1", $tid );

		return $wpdb->get_var( $sql );
	}

	function post_insert_term( $object_id, $terms, $tt_ids, $taxonomy ) {
		global $ozh_ta;
		if ( 'tweet' == get_post_type( $object_id ) && 'post_tag' == $taxonomy && 'yes' == $ozh_ta['add_hash_as_tags'] ) {
			$in = '(';
			foreach ( $tt_ids as $tid ) {
				$in .= $tid . ',';
			}
			$in = rtrim( $in, ',' ) . ')';

			global $wpdb;
			$sql = "UPDATE `$wpdb->term_taxonomy` SET taxonomy='hashtag' WHERE term_id IN $in";
			$wpdb->query( $sql );
		}
	}

	function post_insert_tweet( $post_id ) {
		global $ozh_ta;

		if ( $ozh_ta['link_usernames'] == 'yes' ) {
			$tweet         = get_post( $post_id );
			$dom           = new DOMDocument;
			$dom->encoding = 'utf-8';
			if ( '' != $tweet->post_content ) {
				$dom->loadHTML( utf8_decode( $tweet->post_content ) );
				$mentions = array();
				foreach ( $dom->getElementsByTagName( 'span' ) as $node ) {
					if ( false !== strpos( $node->getAttribute( "class" ), 'username' ) && 0 === strpos( $node->nodeValue, '@' ) ) {
						$mentions[] = substr( $node->nodeValue, 1 );
					}
				}
				wp_set_post_terms( $post_id, $mentions, 'mention', true );
			}
		}
	}

	function get_tweetId_for_posId( $post_id ) {
		global $wpdb;
		$post_id = intval( $post_id );
		$sql     = $wpdb->prepare( "SELECT meta_value
			        FROM `$wpdb->postmeta`
					WHERE `meta_key` = 'ozh_ta_id' AND `post_id` = '%d ' LIMIT 0,1", $post_id );

		return $wpdb->get_var( $sql );
	}

	function ozh_ta_csv_page() {
		if ( isset( $_FILES['csv'] ) && $_FILES['csv']['error'] == 0 ) :
			$csv     = array();
			$name    = $_FILES['csv']['name'];
			$ext     = strtolower( end( explode( '.', $_FILES['csv']['name'] ) ) );
			$type    = $_FILES['csv']['type'];
			$tmpName = $_FILES['csv']['tmp_name'];

			// check the file is a csv
			if ( $ext === 'csv' ) {
				if ( ( $handle = fopen( $tmpName, 'r' ) ) !== false ) {
					// necessary if a large csv file
					set_time_limit( 0 );

					$row = 0;

					while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
						// number of fields in the csv
						$col_count = count( $data );

						// get the tweet ids from the csv
						$csv[ $row ] = $data[0];

						// inc the row
						$row ++;
					}
					fclose( $handle );
				} ?>
				<script>
					var twitter_ids = <?php echo json_encode($csv); ?>,
						error_ids = [];
					(function ($) {
						// 150 calls per 15 minues. 900sec/150 + a bit for safety.
						var timeout = 7500,
							error_tweets,
							expr = new RegExp('[0-9]{18}', 'gi');
						import_tweets = function (tweets) {
							if (tweets.hasOwnProperty('inserted')) {
								$('#csv_file_upload').prepend("<p>Inserted: " + tweets.inserted + ", skipped: " + tweets.skipped + ", tagged: " + tweets.tagged + ", num_tags: " + tweets.num_tags + ", remaining: " + twitter_ids.length + ".</p>");
								timeout = 7500;
							} else {
								$('#csv_file_upload').prepend("<p>" + tweets + ", remaining: " + twitter_ids.length + ".</p>");
								if (tweets.indexOf('No new tweets in this batch') > -1) {
									timeout = 3000;
								}
								var regres;
								while (regres = expr.exec(tweets)) {
									error_ids.push(regres);
								}
							}
						}, check = function () {
							if (twitter_ids.length >= 1) {
								// was timing out at the 100 max you can play with this, will differ per server
								var batch = twitter_ids.splice(0, 15);
								wp_jsonp("import_tweets", "import_tweets", {variable: batch}, import_tweets);
								// 150 calls per 15 minues. 900sec/150 + a bit for safety.
								setTimeout(check, timeout);
							} else {
								if (error_ids.length > 0) {
									$('#csv_file_upload').prepend("<p>" + error_ids.length + " tweets not imported. You may have hit a limit, you can try again in 15 minutes to get the rest.</p>");
								}
								$('#csv_file_upload').prepend("<p>Done.</p>");
							}
						}
						$(document).ready(function () {
							$('#csv_file_upload').html("<p>Found " + twitter_ids.length + " tweets.</p>");
							check();
						});
					})(jQuery);
				</script>
				<?php
			}
		endif; ?>
		<div class="wrap">
			<div id="csv_file_upload">
				<form action="options-general.php?page=ozh_ta_csv" method="post" enctype="multipart/form-data">
					<input type="file" name="csv">
					<button type="submit">Submit</button>
				</form>
			</div>
		</div>
		<?php
	}
}

$CSV_Twitter_Importer = new CSV_Twitter_Importer();