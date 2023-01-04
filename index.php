<?php
/*
 * Plugin Name: Naran Designated User Switching
 * Description: Preset your frequent user switching list and do it in one click.
 * Version:     1.0.3
 * Author:      Changwoo
 * Author URI:  https://blog.changwoo.pe.kr
 * Plugin URI:  https://github.com/chwnam/naran-designated-user-switching
 * License:     GPLv2 or later
 */

const NDUS_MAIN    = __FILE__;
const NDUS_VERSION = '1.0.3';

if ( ! function_exists( 'ndus_init' ) ) {
	add_action( 'plugins_loaded', 'ndus_init' );
	function ndus_init() {
		if ( ! class_exists( 'user_switching' ) ) {
			add_action( 'admin_notices', 'ndus_notice' );
			return;
		}

		if ( current_user_can( 'administrator' ) ) {
			if ( is_admin() ) {
				add_action( 'show_user_profile', 'ndus_user_profile', 999 );
				add_action( 'edit_user_profile', 'ndus_user_profile', 999 );
				add_action( 'personal_options_update', 'ndus_user_update' );
				add_action( 'edit_user_profile_update', 'ndus_user_update' );
				add_action( 'wp_ajax_ndus_request_user_search', 'ndus_response_user_search' );
				add_action( 'wp_ajax_ndus_request_user_switch', 'ndus_response_user_switch' );
			}
			add_action( 'init', 'ndus_register_resources', 50 );
			add_action( 'admin_bar_menu', 'ndus_admin_bar_menu', 1000 );
		}
	}
}

if ( ! function_exists( 'ndus_notice' ) ) {
	/**
	 * Print admin notice.
	 *
	 * @used-by ndus_init()
	 */
	function ndus_notice() {
		echo '<div class="notice notice-error"><p>';
		printf(
			__( 'Naran Designated User Switching requires <a href="%s" target="_blank">%s</a>. Please install and activate the plugin!', 'ndus' ),
			'https://wordpress.org/plugins/user-switching/',
			__( 'User Switching', 'ndus' )
		);
		echo '</p></div>';
	}
}

if ( ! function_exists( 'ndus_user_profile' ) ) {
	/**
	 * Show per user configuration form.
	 *
	 * @param WP_User $user
	 *
	 * @used-by ndus_init()
	 */
	function ndus_user_profile( WP_User $user ) {
		if ( ! user_can( $user, 'administrator' ) ) {
			return;
		}

		$settings = get_user_meta( $user->ID, 'ndus_settings', true );
		?>
        <div id="ndus-wrap">
            <hr>
            <h2 id="ndus-title"><?php _e( 'Designated User Switching', 'ndus' ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th><label for="ndus-user-preset"><?php _e( 'Predefined Users', 'ndus' ); ?></label></th>
                    <td>
                    <textarea id="ndus-user-preset"
                              name="ndus_settings[user_preset]"
                              rows="6"><?php echo esc_textarea( $settings['user_preset'] ?? '' ); ?></textarea>
                        <p class="description">
							<?php _e( 'Enter user login here, one per a line. The list is sorted alphabetically after update.', 'ndus' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ndus-enable-quick-search"><?php _e( 'Quick Search', 'ndus' ); ?></label></th>
                    <td>
                        <input id="ndus-enable-quick-search"
                               name="ndus_settings[quick_search]"
                               type="checkbox"
                               value="yes"
							<?php checked( $settings['quick_search'] ?? false ); ?>>
                        <label for="ndus-enable-quick-search"><?php _e( 'Enable quick user search &amp; switching.', 'ndus' ); ?></label>
                    </td>
                </tr>
                </tbody>
            </table>
            <hr>
			<?php wp_nonce_field( 'ndus', '_ndus_nonce', false ); ?>
        </div>
		<?php
		wp_enqueue_style( 'ndus-profile' );
	}
}

if ( ! function_exists( 'ndus_user_update' ) ) {
	/**
	 * Update configuration.
	 *
	 * @param int $user_id
	 *
	 * @used-by ndus_init
	 */
	function ndus_user_update( int $user_id ) {
		if (
			wp_verify_nonce( $_REQUEST['_ndus_nonce'] ?? '', 'ndus' ) &&
			user_can( $user_id, 'administrator' ) &&
			isset( $_POST['ndus_settings'] )
		) {
			update_user_meta( $user_id, 'ndus_settings', $_POST['ndus_settings'] ?? [] );
		}
	}
}

if ( ! function_exists( 'ndus_register_resources' ) ) {
	/**
	 * Register any script, style handles.
	 *
	 * @used-by ndus_init()
	 * @uses    ndus_sanitize_settings()
	 */
	function ndus_register_resources() {
		wp_register_script(
			'ndus-quick-search',
			plugins_url( 'quick-search.js', NDUS_MAIN ),
			[ 'jquery', 'jquery-ui-autocomplete' ],
			NDUS_VERSION,
			true
		);

		wp_register_style( 'ndus-admin-bar', plugins_url( 'admin-bar.css', NDUS_MAIN ), [], NDUS_VERSION );
		wp_register_style( 'ndus-profile', plugins_url( 'profile.css', NDUS_MAIN ), [], NDUS_VERSION );

		register_meta(
			'user',
			'ndus_settings',
			[
				'type'              => 'array',
				'description'       => 'Naran designated user switching plugin settings.',
				'single'            => true,
				'sanitize_callback' => 'ndus_sanitize_settings',
				'show_in_rest'      => false,
			]
		);
	}
}

if ( ! function_exists( 'ndus_sanitize_settings' ) ) {
	/**
	 * Usermeta 'ndus_user_preset' value sanitizer function.
	 *
	 * @param array $value
	 *
	 * @return array
	 * @used-by ndus_register_resources()
	 */
	function ndus_sanitize_settings( array $value ): array {
		$sanitized = ndus_get_default_settings();

		$user_preset = $value['user_preset'] ?? '';
		if ( $user_preset ) {
			$exploded  = explode( "\r\n", $user_preset );
			$sanitized = array_map( function ( $item ) { return sanitize_user( $item, true ); }, $exploded );
			$filtered  = array_unique( array_filter( $sanitized ) );
			sort( $filtered );
			$sanitized['user_preset'] = implode( "\r\n", $filtered );
		}

		$quick_search = $value['quick_search'] ?? false;
		if ( $quick_search ) {
			$sanitized['quick_search'] = filter_var( $quick_search, FILTER_VALIDATE_BOOLEAN );
		}

		return $sanitized;
	}
}

if ( ! function_exists( 'ndus_get_default_settings' ) ) {
	/**
	 * Return the default settings.
	 *
	 * @return array
	 */
	function ndus_get_default_settings(): array {
		return [
			'user_preset'  => '',
			'quick_search' => false,
		];
	}
}

if ( ! function_exists( 'ndus_admin_bar_menu' ) ) {
	/**
	 * Add admin bar menu.
	 *
	 * @param WP_Admin_Bar $admin_bar
	 *
	 * @used-by ndus_init()
	 */
	function ndus_admin_bar_menu( WP_Admin_Bar $admin_bar ) {
		$ndus_settings = get_user_meta( get_current_user_id(), 'ndus_settings', true );
		$user_preset   = explode( "\r\n", $ndus_settings['user_preset'] ?? '' );
		$quick_search  = $ndus_settings['quick_search'] ?? false;

		$user_query = new WP_User_Query(
			[
				'count_total' => false,
				'login__in'   => $user_preset,
			]
		);

		/** @var WP_User[] $users */
		$users   = $user_query->get_results();
		$current = get_current_user_id();

		foreach ( $users as $user ) {
			if ( $user->ID !== $current && $user->exists() ) {
				$admin_bar->add_menu(
					[
						'parent' => 'user-actions',
						'id'     => 'switch-to-' . $user->user_login,
						'title'  => 'Switch to ' . $user->user_login,
						'href'   => user_switching::switch_to_url( $user ),
					]
				);
			}
		}

		if ( $quick_search ) {
			$screen_reader_text = esc_attr__( 'Quick switch:', 'ndus' );
			$placeholder        = esc_attr__( 'Min. 3 characters.', 'ndus' );
			$button_text        = esc_html__( 'Switch', 'ndus' );

			$html = '<div id="ndus-quick-search-wrap">' .
			        '  <label for="ndus-quick-search" class="screen-reader-text">' . $screen_reader_text . '</label>' .
			        '  <input type="text" id="ndus-quick-search" placeholder="' . $placeholder . '" value="" autocomplete="off">' .
			        '  <button id="ndus-switch" type="button" class="button button-primary" disabled="disabled">' . $button_text . '</button>' .
			        '</div>';

			$admin_bar->add_menu(
				[
					'parent' => 'user-actions',
					'id'     => 'ndus-quick-search',
					'title'  => $html,
					'href'   => '#',
				]
			);

			wp_enqueue_script( 'ndus-quick-search' );
			wp_localize_script(
				'ndus-quick-search',
				'ndusQuickSearch',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => [
						'search' => wp_create_nonce( 'ndus-quick-search-search' ),
						'switch' => wp_create_nonce( 'ndus-quick-search-switch' ),
					],
				]
			);

			wp_enqueue_style( 'ndus-admin-bar' );
		}
	}
}


if ( ! function_exists( 'ndus_response_user_search' ) ) {
	/**
	 * AJAX Callback: user searching.
	 */
	function ndus_response_user_search() {
		check_ajax_referer( 'ndus-quick-search-search', 'nonce' );

		$keyword = sanitize_key( wp_unslash( $_GET['keyword'] ?? '' ) );

		if ( $keyword && current_user_can( 'administrator' ) ) {
			$query = new WP_User_Query(
				[
					'search'         => '*' . $keyword . '*',
					'search_columns' => 'login, email, display_name',
					'exclude'        => [ get_current_user_id() ],
					'count_total'    => false,
				]
			);

			$result = [];

			foreach ( $query->get_results() as $user ) {
				$result[] = [
					'label' => sprintf( '[#%d] %s', $user->ID, $user->user_login ),
					'value' => $user->user_login,
				];
			}

			wp_send_json_success( $result );
		}
	}
}

if ( ! function_exists( 'ndus_response_user_switch' ) ) {
	/**
	 * AJAX Callback: user switching.
	 */
	function ndus_response_user_switch() {
		check_ajax_referer( 'ndus-quick-search-switch', 'nonce' );
		if ( current_user_can( 'administrator' ) && ( $user_login = sanitize_user( $_REQUEST['user_login'] ) ) ) {
			$user = get_user_by( 'login', $user_login );
			if ( $user && $user->exists() && class_exists( 'user_switching' ) ) {
				wp_send_json_success( [ 'url' => user_switching::switch_to_url( $user ) ] );
			}
		}
	}
}
