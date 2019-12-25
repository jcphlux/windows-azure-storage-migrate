<?php
/**
 * Plugin Name: Microsoft Azure Storage Migration for WordPress
 * Version: 1.0.0
 * Plugin URI: https://wordpress.org/plugins/windows_azure_storage_migrate/
 * Description: This will add the ability to migration existing media files to azure for Microsoft Azure Storage for WordPress. This requires the Microsoft Azure Storage for WordPress plugin.
 * Author: Phlux Apps LLC.
 * Author URI: http://www.phluxapps.com/
 *
 * Text Domain: windows_azure_storage_migrate
 * Domain Path: /lang/
 *
 * @category  WordPress_Plugin
 * @package   Microsoft Azure Storage Migration for WordPress/Runner
 * @author    Phlux Apps LLC.
 * @copyright Phlux Apps LLC.
 * @link      http://www.phluxapps.com
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class Windows_Azure_Storage_Migrate_Runner {

	/**
	 * The single instance of Windows_Azure_Storage_Migrate_Runner.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin runner.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Constructor function.
	 *
	 * @since 1.0.0
	 * 
	 * @param object $parent Parent object.
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;

		$this->base = 'wam_';

		// Add runner page to menu.
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );		

		add_action('wp_ajax_windows_azure_storage_migrate_media', array( $this, 'windows_azure_storage_migrate_media' ) );
	}

	/**
	 * Add runner page to admin menu
	 *
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	public function add_menu_item() {		
		if ( current_user_can( 'manage_options' ) ) {
			$page = $this->parent->_token;
			add_options_page(
				__( 'Microsoft Azure Migrate', 'windows-azure-storage-migrate' ),
				__( 'Microsoft Azure Migrate', 'windows-azure-storage-migrate' ),
				'manage_options',
				$page. '_page',
				array( $this, 'runner_page' )
			);	
		}		
	}

	/**
	 * Load runner page content.
	 *
	 * @since 1.0.0
	 * 
	 * @return void
	 */
	public function runner_page() {
		wp_register_script( $this->parent->_token . '-runner-js', $this->parent->assets_url . 'js/runner' . $this->parent->script_suffix . '.js', array( 'jquery' ) , '1.0.0', true );
		wp_localize_script( $this->parent->_token . '-runner-js', 'myAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));   
		wp_enqueue_script( $this->parent->_token . '-runner-js' );

		$disabled = empty(Windows_Azure_Helper::get_default_container()) || empty(Windows_Azure_Helper::get_account_name()) || empty(Windows_Azure_Helper::get_account_key());

		$this->windows_azure_storage_runner_preamble($disabled);		

		echo '<div class="wrap" id="' . $this->parent->_token . '_runner">';	
		echo '<div id="icon-options-general" class="icon32"><br/></div>';
		
		if ( !Windows_Azure_Helper::get_use_for_default_upload()){
			echo '<p>Unable to Migrate! </p>';
		} else{
			$nonce = wp_create_nonce("windows_azure_storage_runner_nonce");
			$total = array_sum( (array) wp_count_attachments() ); 
			
			echo '<div id="responce"></div>';
			echo '<p class="submit">';
			echo '<input type="button" class="button submit button-primary azure-migrate-button" data-nonce="' . $nonce . '" data-total="' . $total . '" value="' . __( 'Migrate Existing Media', 'windows-azure-storage' ) . '"' . disabled( $disabled ) . '/>';
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Preamble text on Microsoft Azure Storage plugin migrate media page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function windows_azure_storage_runner_preamble($disabled) {	
		echo '<div class="wrap">';
		echo '<h2>';
		echo '<img src="' . esc_url( MSFT_AZURE_PLUGIN_URL . 'images/azure-icon.png' ) . '" alt="' . __( 'Microsoft Azure', 'windows-azure-storage' ) . '" style="width:32px">';
		esc_html_e( 'Microsoft Azure Storage Migration for WordPress', 'windows-azure-storage' );
		echo '</h2>';
		echo '<p>';
		if($disabled){
			esc_html_e('Please update you Microsoft Azure Storage for WordPress Setting before trying to migrate existing media.');
		}else{
			esc_html_e('Migrate your existing media files to your Microsoft Azure Storage.'); 
		}
		echo '</p>';
		echo '</div>';
	}

	/**
	 * A
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function windows_azure_storage_migrate_media(){
		// nonce check for an extra layer of security, the function will exit if it fails
		if ( !wp_verify_nonce( $_REQUEST['nonce'], "windows_azure_storage_runner_nonce")) {
			$result['data'] ="Invalid request";
			$result['type'] = "error";
		}else{
			$page = $_REQUEST["page"];

			$delete_local = Windows_Azure_Helper::delete_local_file();

			$posts = get_posts(array('post_type' => "attachment", "posts_per_page" => 1, 'offset' => $page));
			if($posts){
				foreach ($posts as $post) {				
					$name = $post->post_title . " " . $post->ID;

					$existingAzureMeta = get_post_meta($post->ID, "windows_azure_storage_info", true);
					if (isset($existingAzureMeta) && empty($existingAzureMeta) == false) {
						$result['data'] = $name . " already migrated";
						$result['type'] = "warning";
					}else{
						$file = wp_get_attachment_metadata($post->ID, true);

						$result['moved'] = windows_azure_storage_wp_generate_attachment_metadata($file, $post->ID);
						
						if($delete_local){
							$result['delete_local'] = windows_azure_storage_delete_local_files($file, $post->ID);
						}
						$result['data'] = $name . " migrated";
						$result['type'] = "success";
					}			
				}
			}else{
				$result['type'] = "none";
			}
			
		}
		
		// Check if action was fired via Ajax call. If yes, JS code will be triggered, else the user is redirected to the post page
		if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			$result = json_encode($result);			
			echo $result;
		}
		else {
			header("Location: ".$_SERVER["HTTP_REFERER"]);
		}
		// don't forget to end your scripts with a die() function - very important
		die();
	}
	
	/**
	 * Main WordPress_Plugin_Template_Settings Instance
	 *
	 * Ensures only one instance of WordPress_Plugin_Template_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see WordPress_Plugin_Template()
	 * @param object $parent Object instance.
	 * @return object WordPress_Plugin_Template_Settings instance
	 */
	public static function instance( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of WordPress_Plugin_Template_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of WordPress_Plugin_Template_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __wakeup()
}


