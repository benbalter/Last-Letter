<?php
/*
Plugin Name: Last Letter
Plugin URI: 
Description: A deadman switch for your logins 
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

class Last_Letter {
	
	static $instance;
	public $minimum = 86400; //24 hour minimum notice in seconds
	
	/**
	 * Adds Hooks
	 */
	function __construct() {
		self::$instance = &$this;
		
		add_action( 'init', array( &$this, 'register_post_type' ) );
		add_action( 'admin_init', array( &$this, 'enqueue_script' ) );
		add_action( 'admin_init', array( &$this, 'enqueue_style' ) );

		//this should fire first so all subsequent calls assume user was just seen
		add_action( 'admin_init', array( &$this, 'maybe_checkin' ), 1 );
		add_action( 'wp_login', array( &$this, 'maybe_checkin' ), 100, 2 );
		
		//cron
		add_action( 'last-letter-hourly', array( &$this, 'check_last_seen' ) );
		
		//checkin
		if ( isset( $_GET['checkedin'] ) && isset( $_GET['checkedin'] ) )
			add_action( 'admin_notices', array( &$this, 'checkedin_notice' ) );
		
		//edit post screen
		add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ) );
		add_filter( 'enter_title_here', array( &$this, 'enter_title_filter' ) );
		add_action( 'save_post', array( &$this, 'save_postmeta' ) );
		
		//dashboard
		add_action('wp_dashboard_setup', array( &$this, 'register_widget' ) );
		
		//profile
		add_action( 'show_user_profile', array( &$this, 'profile_toggle' ) );
		add_action( 'edit_user_profile', array( &$this, 'profile_toggle' ) );
		add_action( 'personal_options_update', array( &$this, 'profile_save' ) );
		add_action( 'edit_user_profile_update', array( &$this, 'profile_save' ) );	
				
	}
	
	function dump() {
		echo current_filter() . "\r\n";
	}
	
	/**
	 * Start cron job
	 */
	function activation() {
		wp_schedule_event( time(), 'hourly', 'last-letter-hourly' ); 
	}
	
	/**
	 * Stop cron job
	 */
	function deactivation() {
		wp_clear_scheduled_hook( 'last-letter-hourly' );
	}
	
	/**
	 * Register letter CPT
	 */
	function register_post_type() {
	
		 $labels = array( 
    	    'name' => _x( 'Letters', 'last-letter' ),
    	    'singular_name' => _x( 'Letter', 'last-letter' ),
    	    'add_new' => _x( 'Add New', 'last-letter' ),
    	    'add_new_item' => _x( 'Add New Letter', 'last-letter' ),
    	    'edit_item' => _x( 'Edit Letter', 'last-letter' ),
    	    'new_item' => _x( 'New Letter', 'last-letter' ),
    	    'view_item' => _x( 'View Letter', 'last-letter' ),
    	    'search_items' => _x( 'Search Letters', 'last-letter' ),
    	    'not_found' => _x( 'No letters found', 'last-letter' ),
    	    'not_found_in_trash' => _x( 'No letters found in Trash', 'last-letter' ),
    	    'parent_item_colon' => _x( 'Parent Letter:', 'last-letter' ),
    	    'menu_name' => _x( 'Last Letter', 'last-letter' ),
    	    'all_items' => _x( 'All Letters', 'last-letter' ),
    	);
		
    	$args = array( 
    	    'labels' => $labels,
    	    'hierarchical' => false,
    	    'supports' => array( 'title', 'editor', 'author', 'revisions' ),
    	    'public' => false,
    	    'show_ui' => true,
    	    'show_in_menu' => true,
    	    'show_in_nav_menus' => false,
    	    'publicly_queryable' => false,
    	    'exclude_from_search' => true,
    	    'has_archive' => false,
    	    'query_var' => true,
    	    'can_export' => true,
    	    'rewrite' => false,
    	    'capability_type' => 'post'
    	);
		
    	register_post_type( 'letter', $args );
    	
	}
	
	/**
	 * Register CSS
	 */
	function enqueue_style() {
		wp_enqueue_style( 'last-letter', plugins_url( 'last-letter.css', __FILE__ ) );
	}
	
	/**
	 * Register JS
	 */
	function enqueue_script() {
		$suffix = ( WP_DEBUG ) ? '.dev' : '';
		wp_enqueue_script( 'last-letter', plugins_url( "js/last-letter{$suffix}.js", __FILE__ ) );
		
		$data = array( 
				'hideContent' => __( 'Hide Content', 'last-letter' ),
				'showContent' => __( 'Show Content', 'last-letter' ),
		);
		
		wp_localize_script( 'last-letter', 'last_letter', $data );
		
	}
	
	/**
	 * Register Metaboxes
	 */
	function add_meta_boxes() {
		add_meta_box( 'last-letter-delivery', __( 'Delivery Options', 'last-letter' ), array( &$this, 'delivery_metabox' ), 'letter', 'normal', 'high' );
		add_meta_box( 'last-letter-recipients', __( 'Recipients', 'last-letter' ), array( &$this, 'recipient_metabox' ), 'letter', 'normal', 'high' );
	}
	
	/**
	 * Delivery options metabox
	 */
	function delivery_metabox() {
		global $post;
		$grace_period = get_post_meta( $post->ID, '_last_letter_grace_period', true );
		$days = ( $grace_period ) ? (int) $grace_period / ( 60 * 60 * 24 ) : 30; //convert seconds to days 
		wp_nonce_field( 'last_letter_postmeta', 'last_letter_nonce' );
		?>
		<p><?php printf( __( 'Send the above letter if I don\'t check in for <input type="text" class="small-text" value="%d" name="last-letter[days]" id="days" /> <label for="days">days</label>', 'last-letter'), $days ); ?></p>
		<p class="description"><?php _e( "You'll receive an e-mail warning when half that time has elapsed, and one day out.", 'last-letter' ); ?></p>
		<p><?php printf( __( 'Last checked in: %s ago', 'last-letter'), human_time_diff( $this->get_last_seen( $post->post_author ) ) ); ?>
		<?php $args = array( 'last-letter-checkin' => true, 'last-letter-nonce' => wp_create_nonce( 'last-letter' ) ); ?>
		(<a href="<?php echo esc_url( add_query_arg( $args ) ); ?>"><?php _e( 'check in now', 'last-letter'); ?></a>)
		</p>
		<p><?php _e( 'Your E-Mail:', 'last-letter' ); ?>
		<a href="<?php echo admin_url( 'profile.php#email' ); ?>">
		<?php global $current_user;	$user = get_currentuserinfo();
		echo $current_user->user_email;?></a>
		</p>
		<?php
	
	}
	
	/**
	 * Recipients metabox
	 */
	function recipient_metabox() {
		global $post;
		$recipients = get_post_meta( $post->ID, '_last_letter_recipient' );

		//no recipients yet
		if ( !$recipients )
			$recupients = array();
			
		$recipients[] = array( 'name'=> '', 'email' => '' );
		?>
				
		<div class="recipientDiv description">
			<div class="recipientName">
				<?php _e( 'Name', 'last-letter' ); ?>
			</div>
			<div class="recipientEmail">
				<?php _e( 'E-Mail Address', 'last-letter' ); ?>
			</div>
		</div>	
		<?php foreach ( $recipients as $recipient ) { ?>
		
		<div class="recipientDiv">
			<div class="recipientName">
				<input type="text" class="regular-text" name="last-letter[name][]" value="<?php echo esc_attr( $recipient['name'] ); ?>"/>
			</div>
			<div class="recipientEmail">
				<input type="text" class="regular-text" name="last-letter[email][]" value="<?php echo esc_attr( $recipient['email'] ); ?>"/>
			</div>
			<div class="recipientRemove">
				<a href="#"><?php _e( 'remove', 'last-letter' ); ?></a>
			</div>
		</div>	
		<div class="clear" style="display:none;">&nbsp;</div>
		<?php } ?>
		<p><a class="button" id="newRecipient" href="#"><?php _e('Add New', 'last-letter' ); ?></a>	</p>
		<?php
	}
	
	/**
	 * Loops through all posts and checks timestamps to send out warnings, letters, etc.
	 */
	function check_last_seen( ) {
			
		$letters = get_posts( array( 'post-type' => 'letter', 'post_status' => array( 'publish', 'private' ) ) );
		
		//now in seconds
		$now = time();

		foreach ( $letters as $letter ) {
			
			$last_seen = $this->get_last_seen( $letter->post_author );
			$grace_period = (int) get_post_meta( $letter->ID, '_last_letter_grace_period', true );
			$elapsed = $now - $last_seen;

			//maybe send letter ( time elapsed > grace period )
			if ( $elapsed > $grace_period ) {
				
				$this->send_letter( $letter->ID );
			
			//final warning ( 24 hours remaining, and no final warning on record)
			} else if ( $grace_period - $elapsed < $this->minimum ) {
				
				$this->send_warning( $letter->ID, true );
				
			//warning ( time elapsed is > 1/2 grace period, and no warning on record)
			} else if (	$elapsed > ( $grace_period / 2 ) ) {
			
				$this->send_warning( $letter->ID );
			
			}

		}
		
	}
	
	/**
	 * Send's the user's letter
	 * @param int $letterID the letter to send
	 */
	function send_letter( $letterID ) {
		
		$letter = get_post( $letterID );
		$author = get_userdata( $letter->post_author );
	
		//already sent
		if ( $this->sent( $letter->ID ) )
			return false;
	
		$final_warning_sent = get_post_meta( $letterID, '_last_letter_final_warning_sent', true );
		
		//if for some reason final warning never got sent, send now
		if ( !$this->sent( $letter->ID, '_final_warning' ) ) {
			$this->send_warning( $letter->ID, true );
			return false;
		}
		
		//as a fail safe, requrire a minimum notice before sending letter
		if ( time() - $final_warning_sent < $this->minimum )
			return false;
		
		add_filter('wp_mail_content_type',create_function('', 'return "text/html";'));
		$headers = 'From: "' . $author->display_name . '" <' . $author->user_email . '>' . "\r\n";
		$headers .= 'CC: "' . $author->display_name . '" <' . $author->user_email . '>' . "\r\n";	
		
		if ( wp_mail( $this->get_recipients( $letter->ID), $letter->post_title, apply_filters('the_content', $letter->post_content ), $headers ) )
			add_post_meta( $letter->ID, '_last_letter_sent', time(), true );
	
	}
	
	/**
	 * Get formatted string of letter recipients
	 * @param int $letterID the post ID
	 * @returns string of formatted e-mail addresses
	 */
	function get_recipients( $letterID ) {
		$recipients = get_post_meta( $letterID, '_last_letter_recipient' );

		$formatted = array();
		foreach ( $recipients as $recipient ) {
			if ( empty( $recipient['name'] ) )
				$formatted[] = $recipient['email'];	
			else
				$formatted[] = '"' . $recipient['name'] . '" <' . $recipient['email'] . '>';
		}
		
		$formatted = implode( ',', $formatted );
		return $formatted;
	}
	
	/**
	 * Checks postmeta flags to see if a given letter has been sent
	 * @param int $letterID the letter ID
	 * @param string $letter the flag, e.g., _warning, or _final_warning
	 * @retun bool true if sent
	 */
	function sent( $letterID, $letter = '' ) {
		$meta = get_post_meta( $letterID, "_last_letter{$letter}_sent", true );
		return !empty( $meta );
	}
	
	/**
	 * Send's a warning that a letter is about to be sent
	 * @paran bool $final whether this is a final warning
	 * @returns bool false fail, true success
	 */
	function send_warning( $letterID, $final = false) {

		//already sent final warning
		if ( $final && $this->sent( $letterID, '_final_warning' ) )
			return false;

		//already sent warning
		else if ( !$final && $this->sent( $letterID, '_warning' ) )
			return false;

		$letter = get_post( $letterID );
		$user = get_userdata( $letter->post_author );
		
		$msg = $this->get_warning_message( $final );
		$msg .= "\r\n\r\n" .  '%2$s';
		
		//note: %1$s = post title, %2$s = url to post edit screen
		$msg = apply_filters( 'last_letter_warning', $msg, $final );	
		$msg = sprintf( $msg, $letter->post_title, admin_url( 'post.php?action=edit&post=' . $letterID ) );
			
		if ( wp_mail( $user->user_email, $this->get_warning_subject( $final ), $msg ) ) {
			if ( $final ) 
				add_post_meta( $letterID, '_last_letter_final_warning_sent', time(), true );		
			else 
				add_post_meta( $letterID, '_last_letter_warning_sent', time(), true );		
		}

	}
	
	/**
	 * Returns the proper warning message
	 */
	function get_warning_message( $final = false ) {
	
		if ( $final ) 
			return __( 'This is your final warning before your last letter "%1$s" is delivered. Please check in to prevent it from being sent.', 'last-letter' );
		
		return __( 'Your last letter "%1$s" is at less than 50%%. Please check in to prevent it from being sent', 'last-letter' );
	
	}
	
	/**
	 * Returns warning subject
	 */
	function get_warning_subject( $final = false ) {
	
		if ( $final )
			return __( 'FINAL WARNING: Last Letter will be sent in less than 24 hours', 'last-letter' );
	
		return __( 'WARNING: Last Letter at Less than 50%', 'last-letter' );
		
	}
	
	/**
	 * Checks in user based on preferences
	 */
	function maybe_checkin( $not_used = null , $user = null ) {
			
		//always check in if user manually checks in
		if ( isset( $_GET['last-letter-checkin'] ) ) {
			check_admin_referer( 'last-letter', 'last-letter-nonce' );
			return $this->process_checkin( null, true );
		}
		
		if ( $user == null )
			$user = get_current_user_id();
		
		if ( is_object( $user ) )
			$user = $user->ID;
		
		$toggle = (int) get_user_option( 'last_letter_checkin_method', $user );
		
		//user is logging in and wants to be checked in now
		if ( $toggle == 1 && current_filter() == 'wp_login' )
			$this->process_checkin( $user );	
			
		//user wants to check in on every page view
		if ( $toggle == 2 )
			$this->process_checkin( $user );
			
	}
	
	/**
	 * CB to handle checking in
	 */
	function process_checkin( $userID = '', $redirect = false ) {
			
		if ( $userID == '' )
			$userID = get_current_user_id();
		
		$this->update_last_seen( $userID );
		
		if ( !$redirect )
			return true;
		
		$url = remove_query_arg( 'last-letter-nonce' );
		$url = remove_query_arg( 'last-letter-checkin', $url );
		$url = add_query_arg( 'checkedin', true, $url );
				
		//redirect to prevent dups
		wp_redirect( $url );
		
		return true;
		
	}
	
	/**
	 * Handles user checking in, updates timestamps
 	 * @param int $userID the user's ID
	 */
	function update_last_seen( $userID = '' ) {

		if ( $userID == '' )
			$userID = get_current_user_id();
			
		$letters = get_posts( array( 'post_type' => 'letter', 'post_author' => $userID ) );
		
		$keys = array( '_last_letter_warning_sent', '_last_letter_final_warning_sent', '_last_letter_sent'  );
		
		foreach ( $letters as $letter ) {
		
			foreach ( $keys as $key )
				delete_post_meta( $letter->ID, $key );
	
		}
		
		update_user_option( $userID, 'last-letter-checkin', time() );
		
	}
	
	/**
	 * Returns unix timestamp of user's last checkin
	 */
	function get_last_seen( $userID = null ) {
		
		if ( $userID == null )
			$userID = get_current_user_id();
	
		return get_user_option( 'last-letter-checkin', $userID );
			
	} 
	
	
	/**
	 * CB to display notice when user checks in
	 */
	function checkedin_notice() { ?>
		<div class="updated fade"><p>Successfully checked in</p></div>
	<?php }
	
	/**
	 * Changes title help text to subject
	 */
	function enter_title_filter( $text ) {
		global $post;
		
		if ( !$post ||  $post->post_type != 'letter' )
			return $text;
			
		return __( 'Enter subject here', 'last-letter' );
	} 
	
	/**
	 * CB to store postmeta on post update
	 */
	function save_postmeta() {
		global $post;
		
		if ( !$post )
			return;
		
		if ( $post->post_type != 'letter' )
			return;

		if ( !isset( $_POST['last-letter'] ) )
			return;

		wp_verify_nonce( $_POST['last_letter_nonce'], 'last_letter_postmeta' );
		
		//store grace period
		$grace_period = (int) $_POST['last-letter']['days'] * 60 * 60 * 24; // seconds -> days
		update_post_meta( $post->ID, '_last_letter_grace_period', $grace_period );
		
		//clear all recipients
		delete_post_meta( $post->ID, '_last_letter_recipient' );

		foreach ( $_POST['last-letter']['email'] as $ID => $email ) {
			
			if ( empty( $email ) )
				continue;
				
			add_post_meta( $post->ID, '_last_letter_recipient', array( 'name' => $_POST['last-letter']['name'][$ID], 'email' => $email ) );
			
		}
		
	}
	
	/**
	 * Register dashboard widget and move to the top
	 */
	function register_widget() {
		
		//if they check in on every page, no need to show the widget...
		if ( (int) get_user_option( 'last_letter_checkin_method') == 2 )
			return;
	
		wp_add_dashboard_widget( 'last_letter' , __( 'Check in', 'last-letter' ), array( &$this, 'widget' ) );
	
		//force to top
		global $wp_meta_boxes;
	    $normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
	    $widget_backup = array('last_letter' => $normal_dashboard['last_letter'] );
	    unset($normal_dashboard['last_letter']);
	    $sorted_dashboard = array_merge( $widget_backup, $normal_dashboard);	
	    $wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;

	}
	
	/**
	 * CB to display widget content
	 */
	function widget() {
		?>
		<p><?php printf( __( 'Last checked in: %s ago', 'last-letter'), human_time_diff( $this->get_last_seen( get_current_user_id() ) ) ); ?>
		<?php $args = array( 'last-letter-checkin' => true, 'last-letter-nonce' => wp_create_nonce( 'last-letter' ) ); ?>
		(<a href="<?php echo esc_url( add_query_arg( $args ) ); ?>"><?php _e( 'check in now', 'last-letter' ); ?></a>)
		</p><?php
	}
	
	/**
	 * Adds option to profile page
	 */
	function profile_toggle( $user ) {  ?>
		<h3><?php _e( 'Last Letter', 'last-letter' ); ?></h3>
		<table class="form-table">
			<tr>
				<th> 
					<label>
						<?php _e( 'Check in method', 'last-letter' ); ?>
					</label>
				</th>
				<td>
					<?php $options = array( 
								__( 'Only check me in when I click the "Check In" button', 'last-letter' ),
								__( 'Check me in every time I log in', 'last-letter' ),
								__( 'Check me in every time I view an administrative page', 'last-letter' ),
							);
					
					$toggle = (int) get_user_option( 'last_letter_checkin_method', $user->ID );

					foreach( $options as $value => $option) { ?>
						<input type="radio" name="last_letter_checkin_toggle" value="<?php echo $value; ?>" id="last_letter_checkin_toggle_<?php echo $value; ?>" <?php checked( $toggle, $value ); ?>/> <label for="last_letter_checkin_toggle_<?php echo $value; ?>"><?php echo $option; ?></label><br />
					<?php } ?>
				</td>
			</tr>
		</table><?php 
	}
	
	/**
	 * Saves profile page options
	 */
	function profile_save( $userID ) {
	
		if ( !current_user_can( 'edit_user', $userID ) )
			return false; 
	
		update_user_option( $userID, 'last_letter_checkin_method', (int) $_POST['last_letter_checkin_toggle'] );
		
	}
	

}

$last_letter = new Last_Letter();