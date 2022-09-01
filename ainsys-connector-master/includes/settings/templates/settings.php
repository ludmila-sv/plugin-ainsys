<?php

namespace Ainsys\Connector\Master\Settings;

/**
 * Simplified reference to class which linked this template.
 *
 * @var Admin_UI;
 */
$admin_ui = $this;

try {
	$status = $admin_ui->is_ainsys_integration_active( 'check' );
} catch ( \Exception $e ) {
	echo esc_html( $e->getMessage() );
}

?>
<div class="wrap ainsys_settings_wrap">
	<h1><img src="<?php echo AINSYS_CONNECTOR_URL; ?>/assets/img/logo.svg" alt="Ainsys logo" class="ainsys-logo"></h1>

	<div class="nav-tab-wrapper ainsys-nav-tab-wrapper">
		<a class="nav-tab nav-tab-active" href="#setting_section_general" data-target="setting_section_general">
		<?php
			_e( 'General', AINSYS_CONNECTOR_TEXTDOMAIN )
		?>
			</a>
		<a class="nav-tab" href="#setting_section_log" data-target="setting_section_log">
		<?php
			_e( 'Transfer log', AINSYS_CONNECTOR_TEXTDOMAIN )
		?>
			</a>
		<a class="nav-tab" href="#setting_entities_section" data-target="setting_entities_section">
		<?php
			_e( 'Entities export settings', AINSYS_CONNECTOR_TEXTDOMAIN )
		?>
			</a>
	</div>

	<div id="setting_section_general" class="tab-target nav-tab-active tab-target-active">
		<div class="ainsys-settings-blocks">
			<div class="ainsys-settings-block ainsys-settings-block--connection">
				<h2><?php _e( 'Connection Settings', AINSYS_CONNECTOR_TEXTDOMAIN ); ?></h2>

				<form method="post" action="options.php">
					<?php settings_fields( $admin_ui->settings::get_option_name( 'group' ) ); ?>
					<div class="aisys-form-group">
						<label class="aisys-form-label">
							<?php _e( 'AINSYS handshake url for the connector. You can find it in your ', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
							<a href="https://app.ainsys.com/en/settings/workspaces" target="_blank">
								<?php _e( 'dashboard', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
							</a>.
						</label>
						<div class="aisys-form-input">
							<input type="text" size="50" name="<?php echo esc_html( $admin_ui->settings::get_option_name( 'ansys_api_key' ) ); ?>" placeholder="XXXXXXXXXXXXXXXXXXXXX" value="<?php echo esc_html( $admin_ui->settings::get_option( 'ansys_api_key' ) ); ?>"/>
						</div>
					</div>
					<div class="aisys-form-group">
						<label class="aisys-form-label">
							<?php _e( 'Server hook_url', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
						</label>
						<div class="aisys-form-input">
							<input type="text" size="50" name="<?php echo esc_attr( $admin_ui->settings::get_option_name( 'hook_url' ) ); ?>" value="<?php echo esc_attr( $admin_ui->settings::get_option( 'hook_url' ) ); ?>" disabled/>
						</div>
					</div>
					<div class="aisys-form-group">
						<label class="aisys-form-label">
							<?php _e( 'Резервный e-mail', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
							<span><?php _e( 'Used for error reports', AINSYS_CONNECTOR_TEXTDOMAIN ); ?></span>
						</label>
						<div class="aisys-form-input">
							<input type="text" name="<?php echo esc_attr( $admin_ui->settings::get_option_name( 'backup_email' ) ); ?>" placeholder="backup@email.com" value="<?php echo esc_attr( $admin_ui->settings::get_backup_email() ); ?>"/>
						</div>
					</div>
					<div class="aisys-form-group aisys-form-group-checkbox">
						<div class="aisys-form-input">
							<input id="full_uninstall_checkbox" type="checkbox" name="<?php echo esc_attr( $admin_ui->settings::get_option_name( 'full_uninstall' ) ); ?>" value="<?php esc_attr( $admin_ui->settings::get_option( 'full_uninstall' ) ); ?>" <?php checked( 1, esc_html( $admin_ui->settings::get_option( 'full_uninstall' ) ), true ); ?> />
						</div>
						<label class="aisys-form-label">
							<?php _e( 'Purge all stored data during deactivation ', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
							<div class="aisys-form-label-note"><?php _e( 'NB: if you delete the plugin from WordPress admin panel it will clear data regardless of this checkbox', AINSYS_CONNECTOR_TEXTDOMAIN ); ?></div>
						</label>
					</div>
					<div class="aisys-form-group aisys-form-group-checkbox">
						<div class="aisys-form-input">
							<input id="display_debug" type="checkbox" name="<?php echo esc_attr( $admin_ui->settings::get_option_name( 'display_debug' ) ); ?>" value="<?php esc_attr( $admin_ui->settings::get_option( 'display_debug' ) ); ?>" <?php checked( 1, esc_html( $admin_ui->settings::get_option( 'display_debug' ) ), true ); ?> />
						</div>
						<label class="aisys-form-label">
							<?php _e( 'Display debug information', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
						</label>
					</div>

					<div class="submit">
						<input type="submit" class="btn btn-primary" value="<?php _e( 'Save', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>"/>
						<?php if ( ! empty( $status ) && 'success' === $status['status'] ) { ?>
							<a id="remove_ainsys_integration" class="btn btn-secondary"><?php _e( 'Disconect integration', AINSYS_CONNECTOR_TEXTDOMAIN ); ?></a>
						<?php } ?>
					</div>
				</form>

			</div>

			<div class="ainsys-settings-block ainsys-settings-block--status">
				<?php
				$ainsys_status_items = apply_filters( 'ainsys_status_list', array() );

				$ainsys_status_items['curl'] = array(
					'title'  => 'CURL',
					'active' => extension_loaded( 'curl' ),
				);
				$ainsys_status_items['ssl']  = array(
					'title'  => 'SSL',
					'active' => \is_ssl(),
				);

				?>

				<h2><?php _e( 'Connection Status', AINSYS_CONNECTOR_TEXTDOMAIN ); ?></h2>
				<ul class="ainsys-status-items">
					<li class="ainsys-li-underline">
						<span class="ainsys-status-title"><?php _e( 'Conection', AINSYS_CONNECTOR_TEXTDOMAIN ); ?></span>
						<?php
						if ( ! empty( $status ) && 'success' === $status['status'] ) :
							?>
							<span style="color: #37b34a;">
								<i class="fa fa-check-circle-o" aria-hidden="true"></i> <?php _e( 'Working', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
							</span>
							<?php
						else :
							?>
							<span style="color: #d5031e;">
								<i class="fa fa-times-circle-o" aria-hidden="true"></i> <?php _e( 'No AINSYS integration', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
							</span>
							<?php
						endif;
						?>
					</li>

					<?php foreach ( $ainsys_status_items as $status_key => &$status_item ) : ?>
						<li>
							<span class="ainsys-status-title"><?php echo esc_html( $status_item['title'] ); ?></span>
							<?php if ( $status_item['active'] ) : ?>
								<span style="color: #37b34a;">
									<i class="fa fa-check-circle-o" aria-hidden="true"></i> <?php _e( 'Enabled', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
								</span>
							<?php else : ?>
								<span style="color: #d5031e;">
									<i class="fa fa-times-circle-o" aria-hidden="true"></i> <?php _e( 'Disabled', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>

					<li class="ainsys-li-overline">
						<span class="ainsys-status-title"><?php _e( 'PHP version 7.2+', AINSYS_CONNECTOR_TEXTDOMAIN ); ?></span>
						<?php
						if ( version_compare( PHP_VERSION, '7.2.0' ) > 0 ) :
							?>
							<span style="color: #37b34a;"><i class="fa fa-check-circle-o" aria-hidden="true"></i> PHP <?php echo esc_html( PHP_VERSION ); ?></span>
						<?php else : ?>
							<span style="color: #d5031e;">
								<i class="fa fa-times-circle-o" aria-hidden="true"></i> <?php _e( 'Bad PHP version ', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>(
								<?php echo esc_html( PHP_VERSION ); ?>
								).
								<?php _e( 'Update on your hosting', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
							</span>
						<?php endif; ?>
					</li>
					<li>
						<span class="ainsys-status-title"><?php _e( 'Backup email', AINSYS_CONNECTOR_TEXTDOMAIN ); ?></span>
						<?php
						if ( ! empty( $admin_ui->settings::get_backup_email() ) && filter_var( $admin_ui->settings::get_backup_email(), FILTER_VALIDATE_EMAIL ) ) :
							?>
							<span style="color: #37b34a;">
								<i class="fa fa-check-circle-o" aria-hidden="true"></i> <?php _e( 'Valid', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
							</span>
						<?php else : ?>
							<span style="color: #d5031e;">
								<i class="fa fa-times-circle-o" aria-hidden="true"></i> <?php _e( 'Invalid', AINSYS_CONNECTOR_TEXTDOMAIN ); ?>
							</span>
						<?php endif; ?>
					</li>
				</ul>

			</div>
		</div>

	</div>

	<div id="setting_section_log" class="tab-target">
		<?php
		$start = $admin_ui->settings::get_option( 'do_log_transactions' ) ? ' disabled' : '';
		$stop  = $admin_ui->settings::get_option( 'do_log_transactions' ) ? '' : ' disabled';

		$controls  = '<div class="controls">';
		$controls .= '<a id="start_loging" class="button button-primary loging_controll' . $start . '">' . __( 'Start loging', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a>';
		$controls .= '<select id="start_loging_timeinterval" class="' . $start . '" name="loging_timeinterval">
                            <option value="1">' . __( '1 hour', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                            <option value="5">' . __( '5 hours', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                            <option value="12">' . __( '12 hours', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                            <option value="24">' . __( '24 hours', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                            <option value="-1" selected="selected">' . __( 'unlimited', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                    </select>';
		$controls .= '<a id="stop_loging" class="button button-primary loging_controll' . $stop . '">' . __( 'Stop loging', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a>';
		$controls .= '<a id="reload_log" class="button button-primary">' . __( 'Reload', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a>';
		$controls .= '<a id="clear_log" class="button button-primary">' . __( 'Clear log', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a>
                    </div>';

		echo '<div class="log_block">' . $controls . $admin_ui->logger::generate_log_html() . '</div>';

		?>
	</div>

	<div id="setting_entities_section" class="tab-target">
		<?php echo $admin_ui->generate_entities_html(); ?>

		<p>  
		<?php
			_e( 'Detailed', AINSYS_CONNECTOR_TEXTDOMAIN )
		?>
			<a
				href="https://gitlab.ainsys.com/dev06/ainsys_connector">
				<?php
				_e( ' API integration', AINSYS_CONNECTOR_TEXTDOMAIN )
				?>
				</a> 
				<?php
				_e( ' documentation.', AINSYS_CONNECTOR_TEXTDOMAIN )
				?>
			</p>
	</div>
</div>
<script>
	jQuery( document ).ready( function ( $ ) {
		$( '#full_uninstall_checkbox' ).on( 'click', function () {
			let val = $( this ).val() == 1 ? 0 : 1
			$( this ).attr( 'value', val )
			$( this ).prop( 'checked', val )
		} )
		$( '#display_debug' ).on( 'click', function () {
			let val = $( this ).val() == 1 ? 0 : 1
			$( this ).attr( 'value', val )
			$( this ).prop( 'checked', val )
		} )
	} )
</script>

<!--        !!Debug  BLOCK !!           -->
<?php echo $admin_ui->generate_debug_log(); ?>
