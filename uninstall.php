<?php
/**
 * Removes what the plugin stored.
 *
 * This file runs WITHOUT the plugin being loaded: no constants, no classes, no
 * functions of ours exist here. The option name is therefore written out in
 * full, and has to be kept in step with HCFD\Settings::OPTION by hand.
 *
 * @package HypermediaCarouselForDatastar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/** Mirrors HCFD\Settings::OPTION. */
const HCFD_UNINSTALL_OPTION = 'hcfd_settings';

delete_option( HCFD_UNINSTALL_OPTION );

if ( is_multisite() ) {
	/*
	 * Walking every site of a very large network in one request times out, and
	 * a stranded option row is a smaller problem than a half-finished
	 * uninstall.
	 */
	if ( ! wp_is_large_network() ) {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $hcfd_site_id ) {
			switch_to_blog( $hcfd_site_id );
			delete_option( HCFD_UNINSTALL_OPTION );
			restore_current_blog();
		}
	}

	delete_site_option( HCFD_UNINSTALL_OPTION );
}
