<?php
/**
 * This file belongs to the YIT Plugin Framework.
 *
 * This source file is subject to the GNU GENERAL PUBLIC LICENSE (GPL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Main class
 *
 * @class   YITH_WC_Coupon_Email_System
 * @package Yithemes
 * @since   1.0.0
 * @author  Your Inspiration Themes
 */

if ( ! class_exists( 'YITH_WC_Coupon_Email_System' ) ) {

	class YITH_WC_Coupon_Email_System {

		/**
		 * Single instance of the class
		 *
		 * @var \YITH_WC_Coupon_Email_System
		 * @since 1.0.0
		 */
		protected static $instance;

		/**
		 * Panel object
		 *
		 * @var     /Yit_Plugin_Panel object
		 * @since   1.0.0
		 * @see     plugin-fw/lib/yit-plugin-panel.php
		 */
		protected $_panel = null;

		/**
		 * @var $_premium string Premium tab template file name
		 */
		protected $_premium = 'premium.php';

		/**
		 * @var string Premium version landing link
		 */
		protected $_premium_landing = 'https://yithemes.com/themes/plugins/yith-woocommerce-coupon-email-system/';

		/**
		 * @var string Plugin official documentation
		 */
		protected $_official_documentation = 'https://docs.yithemes.com/yith-woocommerce-coupon-email-system/';

		/**
		 * @var string Yith WooCommerce Coupon Email System panel page
		 */
		protected $_panel_page = 'yith-wc-coupon-email-system';

		/**
		 * @var array
		 */
		protected $_email_types = array();

		/**
		 * @var array
		 */
		var $_available_coupons = array();

		/**
		 * @var array
		 */
		var $_email_templates = array();

		/**
		 * Returns single instance of the class
		 *
		 * @return \YITH_WC_Coupon_Email_System
		 * @since 1.0.0
		 */
		public static function get_instance() {

			if ( is_null( self::$instance ) ) {

				self::$instance = new self( $_REQUEST );

			}

			return self::$instance;

		}

		/**
		 * Constructor
		 *
		 * @since   1.0.0
		 * @return  mixed
		 * @author  Alberto Ruggiero
		 */
		public function __construct() {

			if ( ! function_exists( 'WC' ) ) {
				return;
			}

			$this->_email_types = array(
				'coupon' => array(
					'class' => 'YWCES_Coupon_Mail',
					'file'  => 'class-ywces-coupon-email.php',
					'hide'  => false,
				),
			);

			//Load plugin framework
			add_action( 'plugins_loaded', array( $this, 'plugin_fw_loader' ), 12 );
			add_filter( 'plugin_action_links_' . plugin_basename( YWCES_DIR . '/' . basename( YWCES_FILE ) ), array( $this, 'action_links' ) );
			add_filter( 'yith_show_plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 5 );

			$this->includes();

			add_action( 'init', array( $this, 'init_available_coupons' ), 20 );

			if ( is_admin() ) {

				add_action( 'admin_notices', array( $this, 'ywces_admin_notices' ) );
				add_action( 'ywces_howto', array( $this, 'get_howto_content' ) );
				add_action( 'admin_menu', array( $this, 'add_menu_page' ), 5 );
				add_action( 'yith_coupon_email_system_premium', array( $this, 'premium_tab' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
				add_action( 'admin_notices', array( $this, 'check_active_options' ), 10 );

			}

			add_action( 'ywces_email_header', array( $this, 'get_email_header' ), 10, 2 );
			add_action( 'ywces_email_footer', array( $this, 'get_email_footer' ), 10, 1 );
			add_action( 'woocommerce_order_status_completed', array( $this, 'ywces_user_purchase' ) );
			add_filter( 'woocommerce_email_classes', array( $this, 'ywces_custom_email' ) );

		}

		/**
		 * Files inclusion
		 *
		 * @since   1.0.0
		 * @return  void
		 * @author  Alberto Ruggiero
		 */
		private function includes() {

			include_once( 'includes/class-ywces-emails.php' );

			if ( is_admin() ) {

				include_once( 'includes/class-ywces-ajax.php' );
				include_once( 'templates/admin/class-ywces-custom-send.php' );
				include_once( 'templates/admin/class-yith-wc-custom-textarea.php' );

			}

		}

		/**
		 * Get available coupons
		 *
		 * @since   1.1.4
		 * @return  void
		 * @author  Alberto Ruggiero
		 */
		public function init_available_coupons() {

			$this->_available_coupons = $this->ywces_get_coupons();

		}

		/**
		 * ADMIN FUNCTIONS
		 */

		/**
		 * Add a panel under YITH Plugins tab
		 *
		 * @since   1.0.0
		 * @return  void
		 * @author  Alberto Ruggiero
		 * @use     /Yit_Plugin_Panel class
		 * @see     plugin-fw/lib/yit-plugin-panel.php
		 */
		public function add_menu_page() {

			if ( ! empty( $this->_panel ) ) {
				return;
			}

			if ( defined( 'YWCES_PREMIUM' ) ) {
				$admin_tabs['premium-general'] = __( 'General Settings', 'yith-woocommerce-coupon-email-system' );

				if ( $this->is_multivendor_active() ) {

					$admin_tabs['admin-vendor'] = __( 'Vendors Settings', 'yith-woocommerce-coupon-email-system' );

				}

				$admin_tabs['mandrill'] = __( 'Mandrill Settings', 'yith-woocommerce-coupon-email-system' );
			} else {
				$admin_tabs['general']         = __( 'General Settings', 'yith-woocommerce-coupon-email-system' );
				$admin_tabs['premium-landing'] = __( 'Premium Version', 'yith-woocommerce-coupon-email-system' );
			}

			$admin_tabs['howto'] = __( 'How To', 'yith-woocommerce-coupon-email-system' );


			$args = array(
				'create_menu_page' => true,
				'parent_slug'      => '',
				'page_title'       => __( 'Coupon Email System', 'yith-woocommerce-coupon-email-system' ),
				'menu_title'       => 'Coupon Email System',
				'capability'       => 'manage_options',
				'parent'           => '',
				'parent_page'      => 'yit_plugin_panel',
				'page'             => $this->_panel_page,
				'admin-tabs'       => $admin_tabs,
				'options-path'     => YWCES_DIR . 'plugin-options'
			);

			$this->_panel = new YIT_Plugin_Panel_WooCommerce( $args );

		}

		/**
		 * Initializes CSS and javascript
		 *
		 * @since   1.0.0
		 * @return  void
		 * @author  Alberto Ruggiero
		 */
		public function admin_scripts() {

			wp_enqueue_style( 'ywces-admin', YWCES_ASSETS_URL . '/css/ywces-admin.css', array(), YWCES_VERSION );

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_script( 'ywces-admin', YWCES_ASSETS_URL . '/js/ywces-admin' . $suffix . '.js', array( 'jquery' ), YWCES_VERSION );

			$params = apply_filters( 'ywces_admin_scripts_filter', array(
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'vendor_id'              => '0',
				'before_send_test_email' => __( 'Sending test email...', 'yith-woocommerce-coupon-email-system' ),
				'after_send_test_email'  => __( 'Test email has been sent successfully!', 'yith-woocommerce-coupon-email-system' ),
				'test_mail_wrong'        => __( 'Please insert a valid email address', 'yith-woocommerce-coupon-email-system' )
			) );

			wp_localize_script( 'ywces-admin', 'ywces_admin', $params );

		}

		/**
		 * Advise if the plugin cannot be performed
		 *
		 * @since   1.0.0
		 * @return  void
		 * @author  Alberto Ruggiero
		 */
		public function ywces_admin_notices() {

			$active = apply_filters( 'ywces_multivendor_coupon_active_notice', true );

			if ( count( $this->_available_coupons ) == 0 && $active ): ?>
                <div class="error">
                    <p>
						<?php _e( 'In order to use some of the features of YITH WooCommerce Coupon Email System you need to create at least one coupon', 'yith-woocommerce-coupon-email-system' ); ?>
                    </p>
                </div>
			<?php endif;

		}

		/**
		 * Add the YWCES_Coupon_Mail class to WooCommerce mail classes
		 *
		 * @since   1.0.0
		 *
		 * @param   $email_classes
		 *
		 * @return  array
		 * @author  Alberto Ruggiero
		 */
		public function ywces_custom_email( $email_classes ) {

			foreach ( $this->_email_types as $type => $email_type ) {
				$email_classes[ $email_type['class'] ] = include( "includes/{$email_type['file']}" );
			}

			return $email_classes;
		}

		/**
		 * Get the email header.
		 *
		 * @since   1.0.0
		 *
		 * @param   $email_heading
		 * @param   $template
		 *
		 * @return  void
		 * @author  Alberto Ruggiero
		 */
		public function get_email_header( $email_heading, $template = false ) {

			if ( ! $template ) {
				$template = get_option( 'ywces_mail_template', 'base' );
			}

			if ( array_key_exists( $template, $this->_email_templates ) ) {
				$path   = $this->_email_templates[ $template ]['path'];
				$folder = $this->_email_templates[ $template ]['folder'];

				wc_get_template( $folder . '/email-header.php', array( 'email_heading' => $email_heading ), $path, $path );

			} else {
				wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );

			}

		}

		/**
		 * Get the email footer.
		 *
		 * @since   1.0.0
		 *
		 * @param   $template
		 *
		 * @return  void
		 * @author  Alberto Ruggiero
		 */
		public function get_email_footer( $template = false ) {

			$site_name = get_option( 'blogname' );
			$site_url  = get_option( 'siteurl' );

			if ( ! $template ) {
				$template = get_option( 'ywces_mail_template', 'base' );
			}

			if ( array_key_exists( $template, $this->_email_templates ) ) {
				$path   = $this->_email_templates[ $template ]['path'];
				$folder = $this->_email_templates[ $template ]['folder'];

				wc_get_template( $folder . '/email-footer.php', array(
					'site_name' => $site_name,
					'site_url'  => $site_url
				), $path, $path );

			} else {
				echo apply_filters( 'ywces_footer_link', '<p></p><p><a href="' . $site_url . '">' . $site_name . '</a></p>' );
				wc_get_template( 'emails/email-footer.php' );
			}

		}

		/**
		 * Get placeholder reference content.
		 *
		 * @since   1.0.0
		 * @return  void
		 * @author  Alberto Ruggiero
		 */
		public function get_howto_content() {

			?>
            <div id="plugin-fw-wc">
                <h3>
					<?php _e( 'Placeholder reference', 'yith-woocommerce-coupon-email-system' ); ?>
                </h3>
                <table class="form-table">
                    <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <b>{coupon_description}</b>
                        </th>
                        <td class="forminp">
							<?php _e( 'Replaced with the description of the given coupon. This placeholder must be included.', 'yith-woocommerce-coupon-email-system' ); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <b>{site_title}</b>
                        </th>
                        <td class="forminp">
							<?php _e( 'Replaced with the site title', 'yith-woocommerce-coupon-email-system' ); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <b>{customer_name}</b>
                        </th>
                        <td class="forminp">
							<?php _e( 'Replaced with the customer\'s name', 'yith-woocommerce-coupon-email-system' ) ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <b>{customer_last_name}</b>
                        </th>
                        <td class="forminp">
							<?php _e( 'Replaced with the customer\'s last name', 'yith-woocommerce-coupon-email-system' ) ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <b>{customer_email}</b>
                        </th>
                        <td class="forminp">
							<?php _e( 'Replaced with the customer\'s email', 'yith-woocommerce-coupon-email-system' ) ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <b>{order_date}</b>
                        </th>
                        <td class="forminp">
							<?php _e( 'Replaced with the date of the order', 'yith-woocommerce-coupon-email-system' ) ?>
                        </td>
                    </tr>

					<?php if ( defined( 'YWCES_PREMIUM' ) ) : ?>

                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <b>{purchases_threshold}</b>
                            </th>
                            <td class="forminp">
								<?php _e( 'Replaced with the number of purchases', 'yith-woocommerce-coupon-email-system' ) ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <b>{customer_money_spent}</b>
                            </th>
                            <td class="forminp">
								<?php _e( 'Replaced with the amount of money spent by the customer', 'yith-woocommerce-coupon-email-system' ) ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <b>{spending_threshold}</b>
                            </th>
                            <td class="forminp">
								<?php _e( 'Replaced with the spent amount of money', 'yith-woocommerce-coupon-email-system' ) ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <b>{days_ago}</b>
                            </th>
                            <td class="forminp">
								<?php _e( 'Replaced with the number of days since last purchase', 'yith-woocommerce-coupon-email-system' ) ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <b>{purchased_product}</b>
                            </th>
                            <td class="forminp">
								<?php _e( 'Replaced with the name of a purchased product', 'yith-woocommerce-coupon-email-system' ) ?>
                            </td>
                        </tr>

						<?php if ( $this->is_multivendor_active() ): ?>
                            <tr valign="top">
                                <th scope="row" class="titledesc">
                                    <b>{vendor_name}</b>
                                </th>
                                <td class="forminp">
									<?php _e( 'Replaced with the name of the vendor', 'yith-woocommerce-coupon-email-system' ) ?>
                                </td>
                            </tr>
						<?php endif; ?>

					<?php endif; ?>
                    </tbody>
                </table>
            </div>
			<?php
		}

		/**
		 * Get available coupons
		 *
		 * @since   1.0.0
		 *
		 * @return  array
		 * @author  Alberto Ruggiero
		 */
		public function ywces_get_coupons() {

			$posts = get_posts(
				array(
					'post_type'   => 'shop_coupon',
					'post_status' => 'publish',
					'numberposts' => - 1
				)
			);

			$array = array();

			foreach ( $posts as $post ) {

				$array[ $post->post_title ] = $post->post_title;

			}

			return $array;

		}

		/**
		 * Check if active options have a coupon assigned
		 *
		 * @since   1.0.0
		 * @return  void
		 * @author  Alberto Ruggiero
		 */
		public function check_active_options() {

			$messages = array();

			if ( isset( $_POST['ywces_enable_register'] ) && '1' == $_POST['ywces_enable_register'] ) {

				if ( $_POST['ywces_coupon_register'] == '' ) {

					$messages[] = __( 'You need to select a coupon to send one for a new user registration.', 'yith-woocommerce-coupon-email-system' );

				}

			}

			$messages = apply_filters( 'ywces_check_active_options_premium', $messages );

			if ( ! empty( $messages ) ) :

				?>
                <div class="error">
                    <ul>
						<?php foreach ( $messages as $message ): ?>

                            <li><?php echo $message ?></li>

						<?php endforeach; ?>
                    </ul>
                </div>
			<?php

			endif;

		}

		/**
		 * Check if active options have a coupon assigned
		 *
		 * @since   1.0.0
		 *
		 * @param   $coupon_code
		 *
		 * @return  bool
		 * @author  Alberto Ruggiero
		 */
		public function check_if_coupon_exists( $coupon_code ) {

			$result = false;

			$coupon    = new WC_Coupon( $coupon_code );
			$coupon_id = yit_get_prop( $coupon, 'id' );

			if ( $coupon_id ) {

				if ( $post = get_post( $coupon_id ) ) {

					$result = true;

				}

			}

			return $result;

		}

		/**
		 * Trigger coupons on user purchase
		 *
		 * @since   1.0.0
		 *
		 * @param   $order_id
		 *
		 * @return  void
		 * @author  Alberto Ruggiero
		 */
		public function ywces_user_purchase( $order_id ) {

			if ( wp_get_post_parent_id( $order_id ) ) {
				return;
			}

			$order = wc_get_order( $order_id );

			$customer_id = yit_get_prop( $order, '_customer_user' );

			if ( $customer_id == 0 ) {
				return;
			}

			//Set the user to receive again a coupon after XX days from his last purchase
			update_user_meta( $customer_id, '_last_purchase_coupon_sent', 'no' );

			$order_count   = ywces_order_count( $customer_id );
			$order_date    = date( 'Y-m-d', yit_datetime_to_timestamp( yit_get_prop( $order, 'date_created' ) ) );
			$billing_email = yit_get_prop( $order, 'billing_email' );

			if ( count( $this->_available_coupons ) > 0 ) {

				//Check if is user first purchase
				if ( get_option( 'ywces_enable_first_purchase' ) == 'yes' ) {

					if ( $order_count == 1 ) {

						$coupon_code = get_option( 'ywces_coupon_first_purchase' );

						if ( $this->check_if_coupon_exists( $coupon_code ) ) {

							$args = array(
								'order_date' => $order_date,
							);

							$this->bind_coupon( $coupon_code, $billing_email );

							$email_result = YWCES_Emails()->prepare_coupon_mail( $customer_id, 'first_purchase', $coupon_code, $args );

						}

						return;

					}

				}

			}

			do_action( 'ywces_user_purchase_premium', $order, $customer_id, $order_count, $order_date, $billing_email );

		}

		/**
		 * Add user email to coupon allowed emails
		 *
		 * @since   1.0.4
		 *
		 * @param   $coupon_code
		 * @param   $email
		 *
		 * @return  string
		 * @author  Alberto ruggiero
		 */
		public function bind_coupon( $coupon_code, $email ) {

			$coupon         = new WC_Coupon( $coupon_code );
			$valid_emails   = yit_get_prop( $coupon, 'customer_email' );
			$valid_emails[] = $email;

			$args = array(
				'customer_email'       => $valid_emails,
				'usage_limit_per_user' => '1'
			);

			yit_save_prop( $coupon, $args, false, true );

		}

		/**
		 * YITH FRAMEWORK
		 */

		/**
		 * Load plugin framework
		 *
		 * @since   1.0.0
		 * @return  void
		 * @author  Andrea Grillo <andrea.grillo@yithemes.com>
		 */
		public function plugin_fw_loader() {
			if ( ! defined( 'YIT_CORE_PLUGIN' ) ) {
				global $plugin_fw_data;
				if ( ! empty( $plugin_fw_data ) ) {
					$plugin_fw_file = array_shift( $plugin_fw_data );
					require_once( $plugin_fw_file );
				}
			}
		}

		/**
		 * Premium Tab Template
		 *
		 * Load the premium tab template on admin page
		 *
		 * @since   1.0.0
		 * @return  void
		 * @author  Andrea Grillo <andrea.grillo@yithemes.com>
		 */
		public function premium_tab() {
			$premium_tab_template = YWCES_TEMPLATE_PATH . '/admin/' . $this->_premium;
			if ( file_exists( $premium_tab_template ) ) {
				include_once( $premium_tab_template );
			}
		}

		/**
		 * Get the premium landing uri
		 *
		 * @since   1.0.0
		 * @return  string The premium landing link
		 * @author  Andrea Grillo <andrea.grillo@yithemes.com>
		 */
		public function get_premium_landing_uri() {
			return defined( 'YITH_REFER_ID' ) ? $this->_premium_landing . '?refer_id=' . YITH_REFER_ID : $this->_premium_landing;
		}

		/**
		 * Action Links
		 *
		 * add the action links to plugin admin page
		 * @since   1.0.0
		 *
		 * @param   $links | links plugin array
		 *
		 * @return  mixed
		 * @author  Andrea Grillo <andrea.grillo@yithemes.com>
		 * @use     plugin_action_links_{$plugin_file_name}
		 */
		public function action_links( $links ) {

			$links = yith_add_action_links( $links, $this->_panel_page, false );
			return $links;
		}

		/**
		 * Plugin row meta
		 *
		 * add the action links to plugin admin page
		 *
		 * @since   1.0.0
		 *
		 * @param   $new_row_meta_args
		 * @param   $plugin_meta
		 * @param   $plugin_file
		 * @param   $plugin_data
		 * @param   $status
		 * @param   $init_file
		 *
		 * @return  array
		 * @author  Andrea Grillo <andrea.grillo@yithemes.com>
		 * @use     plugin_row_meta
		 */
		public function plugin_row_meta( $new_row_meta_args, $plugin_meta, $plugin_file, $plugin_data, $status, $init_file = 'YWCES_FREE_INIT' ) {

			if ( defined( $init_file ) && constant( $init_file ) == $plugin_file ) {
				$new_row_meta_args['slug'] = YWCES_SLUG;
			}

			return $new_row_meta_args;

		}
	}



}

if ( ! function_exists( 'ywces_order_count' ) ) {

	function ywces_order_count( $user_id, $vendor_id = '' ) {

		global $wpdb;

		if ( $vendor_id != '' ) {

			$count = $wpdb->get_var( "SELECT COUNT(*)
			FROM $wpdb->posts as posts

			LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id

			WHERE   meta.meta_key       = '_customer_user'
			AND     posts.post_type     IN ('shop_order')
			AND     posts.post_status   IN ('wc-completed', 'wc-refunded')
			AND		posts.post_author	= $vendor_id
			AND     meta_value          = $user_id
		" );

		} else {

			$count = $wpdb->get_var( "SELECT COUNT(*)
			FROM $wpdb->posts as posts

			LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id

			WHERE   meta.meta_key       = '_customer_user'
			AND     posts.post_type     IN ('shop_order')
			AND     posts.post_status   IN ('wc-completed', 'wc-refunded')
			AND     meta_value          = $user_id
		" );

		}

		return absint( $count );
	}

}

if ( ! function_exists( 'ywces_total_spent' ) ) {

	function ywces_total_spent( $user_id, $vendor_id = '' ) {

		global $wpdb;

		if ( $vendor_id != '' ) {

			$spent = $wpdb->get_var( "SELECT SUM(meta2.meta_value)
			FROM $wpdb->posts as posts

			LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			LEFT JOIN {$wpdb->postmeta} AS meta2 ON posts.ID = meta2.post_id

			WHERE   meta.meta_key       = '_customer_user'
			AND     meta.meta_value     = $user_id
			AND     posts.post_type     IN ('shop_order')
			AND     posts.post_status   IN ( 'wc-completed', 'wc-refunded' )
			AND		posts.post_author	= $vendor_id
			AND     meta2.meta_key      = '_order_total'
		" );

		} else {

			$spent = $wpdb->get_var( "SELECT SUM(meta2.meta_value)
			FROM $wpdb->posts as posts

			LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			LEFT JOIN {$wpdb->postmeta} AS meta2 ON posts.ID = meta2.post_id

			WHERE   meta.meta_key       = '_customer_user'
			AND     meta.meta_value     = $user_id
			AND     posts.post_type     IN ('shop_order')
			AND     posts.post_status   IN ( 'wc-completed', 'wc-refunded' )
			AND     meta2.meta_key      = '_order_total'
		" );

		}

		return $spent;
	}

}



