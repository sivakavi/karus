<?php
/**
 * Installation related functions and actions.
 *
 * @class    RP_Install
 * @version  1.0.0
 * @package  RestaurantPress/Classes
 * @category Admin
 * @author   WPEverest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RP_Install Class.
 */
class RP_Install {

	/** @var array DB updates and callbacks that need to be run per version */
	private static $db_updates = array(
		'1.3.0' => array(
			'rp_update_130_termmeta',
			'rp_update_130_food_groups',
			'rp_update_130_db_version',
		),
		'1.3.1' => array(
			'rp_update_131_db_version',
		),
		'1.3.2' => array(
			'rp_update_132_db_version',
		),
	);

	/** @var object Background update class */
	private static $background_updater;

	/**
	 * Hook in tabs.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
		add_action( 'init', array( __CLASS__, 'init_background_updater' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'install_actions' ) );
		add_action( 'in_plugin_update_message-restaurantpress/restaurantpress.php', array( __CLASS__, 'in_plugin_update_message' ) );
		add_filter( 'plugin_action_links_' . RP_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Init background updates.
	 */
	public static function init_background_updater() {
		include_once( dirname( __FILE__ ) . '/class-rp-background-updater.php' );
		self::$background_updater = new RP_Background_Updater();
	}

	/**
	 * Check RestaurantPress version and run the updater is required.
	 *
	 * This check is done on all requests and runs if the versions do not match.
	 */
	public static function check_version() {
		if ( ! defined( 'IFRAME_REQUEST' ) && get_option( 'restaurantpress_version' ) !== RP()->version ) {
			self::install();
			do_action( 'restaurantpress_updated' );
		}
	}

	/**
	 * Install actions when a update button is clicked within the admin area.
	 *
	 * This function is hooked into admin_init to affect admin only.
	 */
	public static function install_actions() {
		if ( ! empty( $_GET['do_update_restaurantpress'] ) ) {
			self::update();
			RP_Admin_Notices::add_notice( 'update' );
		}
		if ( ! empty( $_GET['force_update_restaurantpress'] ) ) {
			do_action( 'wp_rp_updater_cron' );
			wp_safe_redirect( admin_url( 'admin.php?page=rp-settings' ) );
			exit;
		}
	}

	/**
	 * Install RP.
	 */
	public static function install() {
		global $wpdb;

		if ( ! is_blog_installed() ) {
			return;
		}

		if ( ! defined( 'RP_INSTALLING' ) ) {
			define( 'RP_INSTALLING', true );
		}

		// Ensure needed classes are loaded
		include_once( dirname( __FILE__ ) . '/admin/class-rp-admin-notices.php' );

		self::create_options();
		self::create_tables();
		self::create_roles();

		// Register post types
		RP_Post_Types::register_post_types();
		RP_Post_Types::register_taxonomies();

		// Queue upgrades/setup wizard
		$current_rp_version    = get_option( 'restaurantpress_version', null );
		$current_db_version    = get_option( 'restaurantpress_db_version', null );
		$major_rp_version      = substr( RP()->version, 0, strrpos( RP()->version, '.' ) );

		RP_Admin_Notices::remove_all_notices();

		// No versions? This is a new install :)
		if ( is_null( $current_rp_version ) && is_null( $current_db_version ) && apply_filters( 'restaurantpress_enable_setup_wizard', true ) ) {
			set_transient( '_rp_activation_redirect', 1, 30 );
		}

		if ( ! is_null( $current_db_version ) && version_compare( $current_db_version, max( array_keys( self::$db_updates ) ), '<' ) ) {
			RP_Admin_Notices::add_notice( 'update' );
		} else {
			self::update_db_version();
		}

		self::update_rp_version();

		// Flush rules after install
		do_action( 'restaurantpress_flush_rewrite_rules' );

		/*
		 * Deletes all expired transients. The multi-table delete syntax is used
		 * to delete the transient record from table a, and the corresponding
		 * transient_timeout record from table b.
		 *
		 * Based on code inside core's upgrade_network() function.
		 */
		$sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
			WHERE a.option_name LIKE %s
			AND a.option_name NOT LIKE %s
			AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
			AND b.option_value < %d";
		$wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );

		// Trigger action
		do_action( 'restaurantpress_installed' );
	}

	/**
	 * Update RP version to current.
	 */
	private static function update_rp_version() {
		delete_option( 'restaurantpress_version' );
		add_option( 'restaurantpress_version', RP()->version );
	}

	/**
	 * Get list of DB update callbacks.
	 *
	 * @since  1.3.2
	 * @return array
	 */
	public static function get_db_update_callbacks() {
		return self::$db_updates;
	}

	/**
	 * Push all needed DB updates to the queue for processing.
	 */
	private static function update() {
		$current_db_version = get_option( 'restaurantpress_db_version' );
		$update_queued      = false;

		foreach ( self::get_db_update_callbacks() as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					self::$background_updater->push_to_queue( $update_callback );
					$update_queued = true;
				}
			}
		}

		if ( $update_queued ) {
			self::$background_updater->save()->dispatch();
		}
	}

	/**
	 * Update DB version to current.
	 * @param string $version
	 */
	public static function update_db_version( $version = null ) {
		delete_option( 'restaurantpress_db_version' );
		add_option( 'restaurantpress_db_version', is_null( $version ) ? RP()->version : $version );
	}

	/**
	 * Default options
	 *
	 * Sets up the default options used on the settings page
	 */
	private static function create_options() {
		// Include settings so that we can run through defaults
		include_once( dirname( __FILE__ ) . '/admin/class-rp-admin-settings.php' );

		$settings = RP_Admin_Settings::get_settings_pages();

		foreach ( $settings as $section ) {
			if ( ! method_exists( $section, 'get_settings' ) ) {
				continue;
			}
			$subsections = array_unique( array_merge( array( '' ), array_keys( $section->get_sections() ) ) );

			foreach ( $subsections as $subsection ) {
				foreach ( $section->get_settings( $subsection ) as $value ) {
					if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
						$autoload = isset( $value['autoload'] ) ? (bool) $value['autoload'] : true;
						add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
					}
				}
			}
		}
	}

	/**
	 * Set up the database table which the plugin need to function.
	 *
	 * Tables:
	 *    restaurantpress_termmeta - Term meta table - sadly WordPress does not have termmeta so we need our own
	 */
	private static function create_tables() {
		global $wpdb;

		$wpdb->hide_errors();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( self::get_schema() );
	}

	/**
	 * Get Table schema.
	 * @return string
	 */
	private static function get_schema() {
		global $wpdb;

		$charset_collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$charset_collate = $wpdb->get_charset_collate();
		}

		/*
		 * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
		 * As of WordPress 4.2, however, we moved to utf8mb4, which uses 4 bytes per character. This means that an index which
		 * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
		 */
		$max_index_length = 191;

		$tables = '';

		// Term meta is only needed for old installs.
		if ( ! function_exists( 'get_term_meta' ) ) {
			$tables .= "
CREATE TABLE {$wpdb->prefix}restaurantpress_termmeta (
  meta_id bigint(20) NOT NULL auto_increment,
  restaurantpress_term_id bigint(20) NOT NULL,
  meta_key varchar(255) default NULL,
  meta_value longtext NULL,
  PRIMARY KEY  (meta_id),
  KEY restaurantpress_term_id (restaurantpress_term_id),
  KEY meta_key (meta_key($max_index_length))
) $charset_collate;
			";
		}

		return $tables;
	}

	/**
	 * Create roles and capabilities.
	 */
	public static function create_roles() {
		global $wp_roles;

		if ( ! class_exists( 'WP_Roles' ) ) {
			return;
		}

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		$capabilities = self::get_core_capabilities();

		foreach ( $capabilities as $cap_group ) {
			foreach ( $cap_group as $cap ) {
				$wp_roles->add_cap( 'administrator', $cap );
			}
		}
	}

	/**
	 * Get capabilities for RestaurantPress.
	 * @return array
	 */
	private static function get_core_capabilities() {
		$capabilities = array();

		$capabilities['core'] = array(
			'manage_restaurantpress'
		);

		$capability_types = array( 'food_menu', 'food_group' );

		foreach ( $capability_types as $capability_type ) {

			$capabilities[ $capability_type ] = array(
				// Post type
				"edit_{$capability_type}",
				"read_{$capability_type}",
				"delete_{$capability_type}",
				"edit_{$capability_type}s",
				"edit_others_{$capability_type}s",
				"publish_{$capability_type}s",
				"read_private_{$capability_type}s",
				"delete_{$capability_type}s",
				"delete_private_{$capability_type}s",
				"delete_published_{$capability_type}s",
				"delete_others_{$capability_type}s",
				"edit_private_{$capability_type}s",
				"edit_published_{$capability_type}s",

				// Terms
				"manage_{$capability_type}_terms",
				"edit_{$capability_type}_terms",
				"delete_{$capability_type}_terms",
				"assign_{$capability_type}_terms"
			);
		}

		return $capabilities;
	}

	/**
	 * restaurantpress_remove_roles function.
	 */
	public static function remove_roles() {
		global $wp_roles;

		if ( ! class_exists( 'WP_Roles' ) ) {
			return;
		}

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		$capabilities = self::get_core_capabilities();

		foreach ( $capabilities as $cap_group ) {
			foreach ( $cap_group as $cap ) {
				$wp_roles->remove_cap( 'administrator', $cap );
			}
		}
	}

	/**
	 * Show plugin changes. Code adapted from W3 Total Cache.
	 */
	public static function in_plugin_update_message( $args ) {
		$transient_name = 'rp_upgrade_notice_' . $args['Version'];

		if ( false === ( $upgrade_notice = get_transient( $transient_name ) ) ) {
			$response = wp_safe_remote_get( 'https://plugins.svn.wordpress.org/restaurantpress/trunk/readme.txt' );

			if ( ! is_wp_error( $response ) && ! empty( $response['body'] ) ) {
				$upgrade_notice = self::parse_update_notice( $response['body'], $args['new_version'] );
				set_transient( $transient_name, $upgrade_notice, DAY_IN_SECONDS );
			}
		}

		echo wp_kses_post( $upgrade_notice );
	}

	/**
	 * Parse update notice from readme file
	 * @param  string $content
	 * @param  string $new_version
	 * @return string
	 */
	private static function parse_update_notice( $content, $new_version ) {
		// Output Upgrade Notice.
		$matches        = null;
		$regexp         = '~==\s*Upgrade Notice\s*==\s*=\s*(.*)\s*=(.*)(=\s*' . preg_quote( RP_VERSION ) . '\s*=|$)~Uis';
		$upgrade_notice = '';

		if ( preg_match( $regexp, $content, $matches ) ) {
			$version = trim( $matches[1] );
			$notices = (array) preg_split('~[\r\n]+~', trim( $matches[2] ) );

			// Check the latest stable version and ignore trunk.
			if ( $version === $new_version && version_compare( RP_VERSION, $version, '<' ) ) {

				$upgrade_notice .= '<div class="rp_plugin_upgrade_notice">';

				foreach ( $notices as $index => $line ) {
					$upgrade_notice .= wp_kses_post( preg_replace( '~\[([^\]]*)\]\(([^\)]*)\)~', '<a href="${2}">${1}</a>', $line ) );
				}

				$upgrade_notice .= '</div> ';
			}
		}

		return wp_kses_post( $upgrade_notice );
	}

	/**
	 * Display action links in the Plugins list table.
	 * @param  array $actions
	 * @return array
	 */
	public static function plugin_action_links( $actions ) {
		$new_actions = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=rp-settings' ) . '" title="' . esc_attr( __( 'View RestaurantPress Settings', 'restaurantpress' ) ) . '">' . __( 'Settings', 'restaurantpress' ) . '</a>',
		);

		return array_merge( $new_actions, $actions );
	}

	/**
	 * Display row meta in the Plugins list table.
	 * @param  array  $plugin_meta
	 * @param  string $plugin_file
	 * @return array
	 */
	public static function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( RP_PLUGIN_BASENAME == $plugin_file ) {
			$new_plugin_meta = array(
				'docs'    => '<a href="' . esc_url( apply_filters( 'restaurantpress_docs_url', 'https://docs.wpeverest.com/docs/restaurantpress/' ) ) . '" title="' . esc_attr( __( 'View RestaurantPress Documentation', 'restaurantpress' ) ) . '">' . __( 'Docs', 'restaurantpress' ) . '</a>',
				'support' => '<a href="' . esc_url( apply_filters( 'restaurantpress_support_url', 'https://wpeverest.com/support-forum/' ) ) . '" title="' . esc_attr( __( 'Visit Free Customer Support Forum', 'restaurantpress' ) ) . '">' . __( 'Free Support', 'restaurantpress' ) . '</a>',
			);

			return array_merge( $plugin_meta, $new_plugin_meta );
		}

		return (array) $plugin_meta;
	}
}

RP_Install::init();
