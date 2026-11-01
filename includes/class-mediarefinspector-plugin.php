<?php
/**
 * Admin interface for Media Reference Inspector.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the Media Reference Inspector admin experience.
 */
class MediaRefInspector_Plugin {

	/**
	 * Scanner service.
	 *
	 * @var MediaRefInspector_Scanner
	 */
	private $scanner;

	/**
	 * Admin page hook suffix.
	 *
	 * @var string|false
	 */
	private $page_hook = false;

	/**
	 * Constructor.
	 *
	 * @param MediaRefInspector_Scanner $scanner Scanner service.
	 */
	public function __construct( MediaRefInspector_Scanner $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_mediarefinspector_get_bulk_ids', array( $this, 'ajax_get_bulk_ids' ) );
		add_action( 'wp_ajax_mediarefinspector_bulk_scan_item', array( $this, 'ajax_bulk_scan_item' ) );
		add_action( 'wp_ajax_mediarefinspector_delete_preview', array( $this, 'ajax_delete_preview' ) );
		add_action( 'wp_ajax_mediarefinspector_delete_confirm', array( $this, 'ajax_delete_confirm' ) );
		add_action( 'admin_post_mediarefinspector_send_support_email', array( $this, 'handle_support_email' ) );
		add_filter( 'media_row_actions', array( $this, 'add_media_row_action' ), 10, 3 );
	}

	/**
	 * Adds the plugin page under Media.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		$this->page_hook = add_media_page(
			__( 'Media Reference Inspector', 'media-reference-inspector' ),
			__( 'Media References', 'media-reference-inspector' ),
			'manage_options',
			'media-reference-inspector',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Loads assets only on the plugin screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! $this->page_hook || $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'mediarefinspector-admin',
			plugins_url( 'assets/css/admin.css', MEDIAREFINSPECTOR_FILE ),
			array(),
			MEDIAREFINSPECTOR_VERSION
		);

		wp_enqueue_style( 'mediarefinspector-dashboard', plugins_url( 'assets/css/dashboard-v260.css', MEDIAREFINSPECTOR_FILE ), array( 'mediarefinspector-admin' ), MEDIAREFINSPECTOR_VERSION );

		wp_enqueue_script(
			'mediarefinspector-admin',
			plugins_url( 'assets/js/admin.js', MEDIAREFINSPECTOR_FILE ),
			array(),
			MEDIAREFINSPECTOR_VERSION,
			true
		);

		wp_localize_script(
			'mediarefinspector-admin',
			'MediaRefInspectorAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'version' => MEDIAREFINSPECTOR_VERSION,
				'nonce'   => wp_create_nonce( 'mediarefinspector_bulk_scan' ),
				'strings' => array(
					'starting'         => __( 'Preparing media scan…', 'media-reference-inspector' ),
					'scanning'         => __( 'Scanning media…', 'media-reference-inspector' ),
					'complete'         => __( 'Bulk scan complete.', 'media-reference-inspector' ),
					'failed'           => __( 'The bulk scan could not be completed. Please try again.', 'media-reference-inspector' ),
					'noItems'          => __( 'No media items matched these filters.', 'media-reference-inspector' ),
					'cancelled'        => __( 'Bulk scan stopped.', 'media-reference-inspector' ),
					'referenced'       => __( 'Referenced', 'media-reference-inspector' ),
					'noReferences'     => __( 'No supported references found', 'media-reference-inspector' ),
					'error'            => __( 'Needs review', 'media-reference-inspector' ),
					'csvFilename'      => 'media-reference-inspector-report.csv',
					'inspect'          => __( 'Inspect', 'media-reference-inspector' ),
					'editMedia'        => __( 'Edit media', 'media-reference-inspector' ),
					'confirmStart'     => __( 'Start a read-only scan of the filtered media items?', 'media-reference-inspector' ),
					'noSupportedProof' => __( 'No supported references found does not prove a file is unused.', 'media-reference-inspector' ),
				),
			)
		);
		wp_enqueue_script( 'mediarefinspector-dashboard', plugins_url( 'assets/js/dashboard.js', MEDIAREFINSPECTOR_FILE ), array(), MEDIAREFINSPECTOR_VERSION, true );
		/* translators: 1: Number of recent media items currently displayed, 2: Total available recent media items. */
		$recent_count_label = __( '%1$d / %2$d recent media shown', 'media-reference-inspector' );
		wp_localize_script( 'mediarefinspector-dashboard', 'MediaRefInspectorDashboard', array( 'countLabel' => $recent_count_label ) );
		wp_enqueue_script( 'mediarefinspector-cleanup', plugins_url( 'assets/js/media-cleanup.js', MEDIAREFINSPECTOR_FILE ), array(), MEDIAREFINSPECTOR_VERSION, true );
		/* translators: %d: Number of media items selected for deletion review. */
		$selected_count_label = __( '%d selected', 'media-reference-inspector' );
		wp_localize_script( 'mediarefinspector-cleanup', 'MediaRefInspectorCleanup', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'mediarefinspector_delete_media' ),
			'mediaTrash' => $this->media_trash_enabled(),
			'strings' => array(
				'loading' => __( 'Preparing deletion review…', 'media-reference-inspector' ),
				'noSelection' => __( 'Select at least one media item first.', 'media-reference-inspector' ),
				'networkError' => __( 'Could not contact WordPress. Nothing was deleted; please try again.', 'media-reference-inspector' ),
				'tokenExpired' => __( 'This review expired. Select the media again and retry.', 'media-reference-inspector' ),
				'deleting' => __( 'Processing selected media…', 'media-reference-inspector' ),
				'permanent' => __( 'Permanently delete', 'media-reference-inspector' ),
				'trash' => __( 'Move to Trash', 'media-reference-inspector' ),
				'item' => __( 'item', 'media-reference-inspector' ),
				'items' => __( 'items', 'media-reference-inspector' ),
				'confirmedWord' => 'DELETE',
				'selectedCount' => $selected_count_label,
			),
		) );
		wp_enqueue_script( 'mediarefinspector-site-audit', plugins_url( 'assets/js/site-audit.js', MEDIAREFINSPECTOR_FILE ), array( 'mediarefinspector-admin' ), MEDIAREFINSPECTOR_VERSION, true );
	}

	/**
	 * Adds an Inspector shortcut to Media Library row actions.
	 *
	 * @param string[] $actions  Existing row actions.
	 * @param WP_Post  $post     Attachment post.
	 * @param bool     $detached Whether the media list is detached-only.
	 * @return string[]
	 */
	public function add_media_row_action( $actions, $post, $detached ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required filter signature.
		if ( ! current_user_can( 'manage_options' ) || ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return $actions;
		}

		$cached = get_transient( 'mediarefinspector_scan_status_' . absint( $post->ID ) );
		$url    = $this->get_scan_url( $post->ID, '', 1 );
		$label  = is_array( $cached ) ? __( 'Re-scan references', 'media-reference-inspector' ) : __( 'Check references', 'media-reference-inspector' );
		$actions['mediarefinspector'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $label ) );

		if ( is_array( $cached ) && ! empty( $cached['status'] ) ) {
			$count = isset( $cached['count'] ) ? absint( $cached['count'] ) : 0;
			if ( 'referenced' === $cached['status'] ) {
				/* translators: %d: Cached reference count. */
				$status_label = sprintf( _n( '%d recent reference', '%d recent references', $count, 'media-reference-inspector' ), $count );
				$status_class = 'is-referenced';
			} else {
				$status_label = __( 'Review: no supported references', 'media-reference-inspector' );
				$status_class = 'is-review';
			}
			$actions['mediarefinspector_status'] = '<span class="mediarefinspector-row-status ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
		} else {
			$actions['mediarefinspector_status'] = '<span class="mediarefinspector-row-status is-pending">' . esc_html__( 'Not scanned', 'media-reference-inspector' ) . '</span>';
		}

		return $actions;
	}

	/**
	 * Renders the plugin page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'media-reference-inspector' ) );
		}

		$tab = $this->get_current_tab();
		?>
		<div class="wrap mediarefinspector-admin">
			<?php $this->render_header( $tab ); ?>

			<div class="mediarefinspector-content" role="region" aria-label="<?php echo esc_attr__( 'Media Reference Inspector content', 'media-reference-inspector' ); ?>">
				<?php
				if ( 'dashboard' === $tab ) {
					$this->render_dashboard_tab();
				} elseif ( 'explorer' === $tab ) {
					$this->render_explorer_tab();
				} elseif ( 'bulk' === $tab ) {
					$this->render_bulk_tab();
				} elseif ( 'audit' === $tab ) {
					$this->render_audit_tab();
				} elseif ( 'duplicates' === $tab ) {
					$this->render_duplicates_tab();
				} elseif ( 'broken' === $tab ) {
					$this->render_broken_tab();
				} elseif ( 'site-audit' === $tab ) {
					$this->render_site_audit_tab();
				} elseif ( 'help' === $tab ) {
					$this->render_help_tab();
				} else {
					$this->render_scanner_tab();
				}
				?>
			</div>
		</div>
		<?php
		$this->mark_feature_seen( $tab );
	}

	/**
	 * Renders the branded WordPress-native page header and navigation.
	 *
	 * @param string $tab Active tab.
	 * @return void
	 */
	private function render_header( $tab ) {
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'media-reference-inspector' ),
			'explorer'  => __( 'Media Explorer', 'media-reference-inspector' ),
			'scanner'    => __( 'Scanner', 'media-reference-inspector' ),
			'bulk'       => __( 'Bulk Scan', 'media-reference-inspector' ),
			'audit'      => __( 'Page Audit', 'media-reference-inspector' ),
			'duplicates' => __( 'Duplicates', 'media-reference-inspector' ),
			'broken'     => __( 'Broken URLs', 'media-reference-inspector' ),
			'site-audit' => __( 'Site Audit', 'media-reference-inspector' ),
			'help'       => __( 'Help', 'media-reference-inspector' ),
		);
		?>
		<header class="mediarefinspector-header">
			<div class="mediarefinspector-brand">
				<div class="mediarefinspector-brand-mark" aria-hidden="true"><span class="dashicons dashicons-search"></span></div>
				<div class="mediarefinspector-brand-copy">
					<h1><?php esc_html_e( 'Media Reference Inspector', 'media-reference-inspector' ); ?></h1>
					<p><?php esc_html_e( 'Find where WordPress media is referenced before you replace, reorganize, or remove it.', 'media-reference-inspector' ); ?></p>
					<div class="mediarefinspector-badges" aria-label="<?php echo esc_attr__( 'Plugin status', 'media-reference-inspector' ); ?>">
						<span class="mediarefinspector-badge mediarefinspector-badge-safe"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span><?php esc_html_e( 'Read-only scans', 'media-reference-inspector' ); ?></span>
						<span class="mediarefinspector-badge"><?php echo esc_html( MEDIAREFINSPECTOR_VERSION ); ?></span>
						<span class="mediarefinspector-badge"><?php esc_html_e( 'No tracking', 'media-reference-inspector' ); ?></span>
					</div>
				</div>
			</div>
			<nav class="nav-tab-wrapper mediarefinspector-tabs" aria-label="<?php echo esc_attr__( 'Inspector sections', 'media-reference-inspector' ); ?>">
				<?php foreach ( $tabs as $tab_key => $label ) : ?>
					<?php
					$url = add_query_arg(
						array(
							'page' => 'media-reference-inspector',
							'tab'  => $tab_key,
						),
						admin_url( 'upload.php' )
					);
					?>
					<a class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>" <?php echo $tab === $tab_key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?><?php if ( $this->is_new_feature( $tab_key ) ) : ?><span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span><?php endif; ?></a>
				<?php endforeach; ?>
			</nav>
		</header>
		<?php
	}

	/**
	 * Shows lightweight attachment statistics with cached-only reference statuses.
	 * Never performs reference scans on dashboard page load.
	 *
	 * @return void
	 */
	private function render_dashboard_tab() {
		global $wpdb;
		$counts = wp_count_posts( 'attachment' );
		$total = $counts && isset( $counts->inherit ) ? absint( $counts->inherit ) : 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate metadata only; no content scanning.
		$mime_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT SUBSTRING_INDEX(post_mime_type, %s, 1) AS family, COUNT(*) AS quantity FROM %i WHERE post_type = %s AND post_status = %s GROUP BY family', '/', $wpdb->posts, 'attachment', 'inherit' ) );
		$families = array( 'image' => 0, 'video' => 0, 'audio' => 0, 'other' => 0 );
		foreach ( is_array( $mime_rows ) ? $mime_rows : array() as $row ) {
			$family = isset( $families[ $row->family ] ) && 'other' !== $row->family ? $row->family : 'other';
			$families[ $family ] += max( 0, (int) $row->quantity );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded aggregate metadata count.
		$recent_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_type = %s AND post_status = %s AND post_date_gmt >= %s', $wpdb->posts, 'attachment', 'inherit', gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
		$latest = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 8, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
		$checked_count = 0;
		$review_count = 0;
		foreach ( $latest as $attachment ) {
			$status = $this->get_cached_reference_status( $attachment->ID );
			if ( 'is-pending' !== $status['class'] ) {
				++$checked_count;
				if ( 'is-review' === $status['class'] ) {
					++$review_count;
				}
			}
		}
		$checked_percent = count( $latest ) ? (int) round( $checked_count * 100 / count( $latest ) ) : 0;
		$links = array(
			'explorer'   => $this->get_tab_url( 'explorer' ),
			'scanner'    => $this->get_tab_url( 'scanner' ),
			'bulk'       => $this->get_tab_url( 'bulk' ),
			'site-audit' => $this->get_tab_url( 'site-audit' ),
			'broken'     => $this->get_tab_url( 'broken' ),
			'duplicates' => $this->get_tab_url( 'duplicates' ),
		);
		?>
		<section class="mediarefinspector-dashboard mri26-dashboard" aria-labelledby="mediarefinspector-dashboard-heading">
			<div class="mri26-welcome">
				<div class="mri26-welcome-content">
					<span class="mri26-eyebrow"><span class="mri26-live-dot" aria-hidden="true"></span><?php esc_html_e( 'MEDIA INTELLIGENCE  /  V2.7', 'media-reference-inspector' ); ?></span>
					<h2 id="mediarefinspector-dashboard-heading"><?php esc_html_e( 'Every asset. Clearer insight.', 'media-reference-inspector' ); ?></h2>
					<p><?php esc_html_e( 'Explore your library, trace supported references, and review media with confidence. Scans never alter files; removal requires your confirmation.', 'media-reference-inspector' ); ?></p>
					<div class="mri26-welcome-actions"><a class="mri26-btn mri26-btn-light" href="<?php echo esc_url( $links['explorer'] ); ?>"><span class="dashicons dashicons-search" aria-hidden="true"></span><?php esc_html_e( 'Explore library', 'media-reference-inspector' ); ?></a><a class="mri26-btn mri26-btn-glass" href="<?php echo esc_url( $links['bulk'] ); ?>"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span><?php esc_html_e( 'Start a scan', 'media-reference-inspector' ); ?></a></div>
					<div class="mri26-welcome-foot"><span><span class="dashicons dashicons-shield" aria-hidden="true"></span><?php esc_html_e( 'Read-only inspections', 'media-reference-inspector' ); ?></span><span><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e( 'Manual cleanup only', 'media-reference-inspector' ); ?></span></div>
				</div>
				<div class="mri26-orbit" aria-hidden="true"><div class="mri26-orbit-disc"><span class="dashicons dashicons-format-gallery"></span></div><span class="mri26-orbit-dot mri26-orbit-dot-one"></span><span class="mri26-orbit-dot mri26-orbit-dot-two"></span><span class="mri26-orbit-dot mri26-orbit-dot-three"></span></div>
			</div>
			<div class="mri26-section-label"><div><span class="mri26-section-kicker"><?php esc_html_e( 'AT A GLANCE', 'media-reference-inspector' ); ?></span><h3><?php esc_html_e( 'Library overview', 'media-reference-inspector' ); ?></h3></div><span class="mri26-soft-tag"><span class="dashicons dashicons-database" aria-hidden="true"></span><?php esc_html_e( 'Live metadata', 'media-reference-inspector' ); ?></span></div>
			<div class="mri26-stat-grid">
				<div class="mri26-stat mri26-stat-blue"><div class="mri26-stat-top"><span class="dashicons dashicons-admin-media" aria-hidden="true"></span><span class="mri26-stat-corner" aria-hidden="true">01</span></div><span class="mri26-stat-label"><?php esc_html_e( 'Total media', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><small><?php esc_html_e( 'Attachment records', 'media-reference-inspector' ); ?></small></div>
				<div class="mri26-stat mri26-stat-purple"><div class="mri26-stat-top"><span class="dashicons dashicons-format-image" aria-hidden="true"></span><span class="mri26-stat-corner" aria-hidden="true">02</span></div><span class="mri26-stat-label"><?php esc_html_e( 'Images', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( number_format_i18n( $families['image'] ) ); ?></strong><small><?php esc_html_e( 'Image attachments', 'media-reference-inspector' ); ?></small></div>
				<div class="mri26-stat mri26-stat-teal"><div class="mri26-stat-top"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><span class="mri26-stat-corner" aria-hidden="true">03</span></div><span class="mri26-stat-label"><?php esc_html_e( 'Added in 30 days', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( number_format_i18n( $recent_count ) ); ?></strong><small><?php esc_html_e( 'Recent uploads', 'media-reference-inspector' ); ?></small></div>
				<div class="mri26-stat mri26-stat-orange"><div class="mri26-stat-top"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span class="mri26-stat-corner" aria-hidden="true">04</span></div><span class="mri26-stat-label"><?php esc_html_e( 'Recently inspected', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( number_format_i18n( $checked_count ) ); ?><span class="mri26-stat-total">/<?php echo esc_html( count( $latest ) ); ?></span></strong><small><?php echo esc_html( sprintf( /* translators: %d: Media items requiring review in the recent sample. */ __( '%d in sample need review', 'media-reference-inspector' ), $review_count ) ); ?></small></div>
			</div>
			<div class="mri26-workspace">
				<div class="mri26-main-column">
					<section class="mri26-surface mri26-gallery-card" aria-labelledby="mri26-recent-title">
						<div class="mri26-card-heading"><div><span class="mri26-section-kicker"><?php esc_html_e( 'YOUR CONTENT', 'media-reference-inspector' ); ?></span><h3 id="mri26-recent-title"><?php esc_html_e( 'Recent media', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Preview your latest 8 uploads. Statuses reflect only recent cached scans.', 'media-reference-inspector' ); ?></p></div><a class="mri26-text-link" href="<?php echo esc_url( $links['explorer'] ); ?>"><?php esc_html_e( 'View all media', 'media-reference-inspector' ); ?><span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span></a></div>
						<div class="mri26-gallery-controls" data-mri26-filter-root>
							<label class="mri26-mini-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Filter recent media by filename', 'media-reference-inspector' ); ?></span><input data-mri26-search type="search" placeholder="<?php echo esc_attr__( 'Find recent media…', 'media-reference-inspector' ); ?>" /></label>
							<div class="mri26-filter-tabs" role="group" aria-label="<?php echo esc_attr__( 'Filter recent media by scan status', 'media-reference-inspector' ); ?>"><button type="button" data-mri26-filter="all" aria-pressed="true" class="is-active"><?php esc_html_e( 'All', 'media-reference-inspector' ); ?></button><button type="button" data-mri26-filter="referenced" aria-pressed="false"><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></button><button type="button" data-mri26-filter="review" aria-pressed="false"><?php esc_html_e( 'Review', 'media-reference-inspector' ); ?></button><button type="button" data-mri26-filter="pending" aria-pressed="false"><?php esc_html_e( 'Not scanned', 'media-reference-inspector' ); ?></button></div>
						</div>
						<?php if ( $latest ) : ?><div class="mri26-media-gallery" id="mri26-recent-gallery">
							<?php foreach ( $latest as $attachment ) : $this->render_explorer_tile( $attachment ); endforeach; ?>
						</div><p class="mri26-empty-filter" id="mri26-filter-empty" hidden><?php esc_html_e( 'No recent media matches this filter. Try another search or status.', 'media-reference-inspector' ); ?></p><p class="mri26-gallery-caption" id="mri26-filter-count" role="status" aria-live="polite"></p>
						<?php else : ?><div class="mri26-empty-gallery"><span class="dashicons dashicons-format-gallery" aria-hidden="true"></span><p><?php esc_html_e( 'No media has been uploaded yet.', 'media-reference-inspector' ); ?></p></div><?php endif; ?>
					</section>
				</div>
				<div class="mri26-side-column">
					<section class="mri26-surface mri26-health-panel" aria-label="<?php echo esc_attr__( 'Recent scan coverage', 'media-reference-inspector' ); ?>"><div class="mri26-side-heading"><span class="mri26-small-icon"><span class="dashicons dashicons-chart-pie" aria-hidden="true"></span></span><span class="mri26-section-kicker"><?php esc_html_e( 'RECENT SAMPLE', 'media-reference-inspector' ); ?></span></div><div class="mri26-coverage-row"><div><strong><?php esc_html_e( 'Scan coverage', 'media-reference-inspector' ); ?></strong><p><?php esc_html_e( 'Of the last 8 uploads shown', 'media-reference-inspector' ); ?></p></div><span class="mri26-coverage-number"><?php echo esc_html( $checked_percent ); ?>%</span></div><div class="mri26-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $checked_percent ); ?>" aria-label="<?php echo esc_attr__( 'Cached scan coverage in recent media sample', 'media-reference-inspector' ); ?>"><span style="width:<?php echo esc_attr( $checked_percent ); ?>%"></span></div><p class="mri26-helper"><?php esc_html_e( 'This is a small recent-media sample, not a full-site usage score.', 'media-reference-inspector' ); ?></p></section>
					<section class="mri26-surface mri26-mix-panel" aria-labelledby="mri26-mix-heading"><div class="mri26-side-heading"><span class="mri26-small-icon mri26-small-icon-purple"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span></span><span class="mri26-section-kicker"><?php esc_html_e( 'FILE BREAKDOWN', 'media-reference-inspector' ); ?></span></div><h3 id="mri26-mix-heading"><?php esc_html_e( 'Media mix', 'media-reference-inspector' ); ?></h3><div class="mri26-distribution" aria-label="<?php echo esc_attr__( 'Attachment counts by file family', 'media-reference-inspector' ); ?>">
						<?php foreach ( array( 'image' => __( 'Images', 'media-reference-inspector' ), 'video' => __( 'Videos', 'media-reference-inspector' ), 'audio' => __( 'Audio', 'media-reference-inspector' ), 'other' => __( 'Other files', 'media-reference-inspector' ) ) as $family => $label ) : $percent = $total ? round( 100 * $families[ $family ] / $total, 1 ) : 0; ?><div class="mri26-mix-line"><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( number_format_i18n( $families[ $family ] ) ); ?></strong><div class="mri26-mix-track"><span class="mri26-mix-<?php echo esc_attr( $family ); ?>" style="width:<?php echo esc_attr( $percent ); ?>%"></span></div></div><?php endforeach; ?>
						</div></section>
					<section class="mri26-surface mri26-actions-panel" aria-labelledby="mri26-actions-heading"><div class="mri26-side-heading"><span class="mri26-small-icon mri26-small-icon-teal"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span></span><span class="mri26-section-kicker"><?php esc_html_e( 'TOOLS & ACTIONS', 'media-reference-inspector' ); ?></span></div><h3 id="mri26-actions-heading"><?php esc_html_e( 'Quick actions', 'media-reference-inspector' ); ?></h3>
						<a href="<?php echo esc_url( $links['scanner'] ); ?>"><span class="dashicons dashicons-search" aria-hidden="true"></span><span><strong><?php esc_html_e( 'Inspect one file', 'media-reference-inspector' ); ?></strong><small><?php esc_html_e( 'Find supported references', 'media-reference-inspector' ); ?></small></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
						<a href="<?php echo esc_url( $links['site-audit'] ); ?>"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span><span><strong><?php esc_html_e( 'Run a site audit', 'media-reference-inspector' ); ?></strong><small><?php esc_html_e( 'Progressive review', 'media-reference-inspector' ); ?></small></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
						<a href="<?php echo esc_url( $links['broken'] ); ?>"><span class="dashicons dashicons-warning" aria-hidden="true"></span><span><strong><?php esc_html_e( 'Check broken URLs', 'media-reference-inspector' ); ?></strong><small><?php esc_html_e( 'Find missing local files', 'media-reference-inspector' ); ?></small></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
						<a href="<?php echo esc_url( $links['duplicates'] ); ?>"><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span><span><strong><?php esc_html_e( 'Find exact duplicates', 'media-reference-inspector' ); ?></strong><small><?php esc_html_e( 'Read-only file review', 'media-reference-inspector' ); ?></small></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a></section>
				</div>
			</div>
			<div class="mri26-safety-note"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span><p><strong><?php esc_html_e( 'A safer way to inspect.', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'No supported references found does not prove a file is unused. Check theme code, integrations and external use before changing media.', 'media-reference-inspector' ); ?></p></div>
		</section>
		<?php
	}

	/**
	 * Gets a tab URL without any untrusted query parameters.
	 *
	 * @param string $tab Known tab key.
	 * @return string
	 */
	private function get_tab_url( $tab ) {
		return add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => sanitize_key( $tab ) ), admin_url( 'upload.php' ) );
	}

	/**
	 * Reads an optional recent scan status. Missing cache is never interpreted as unused.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{class:string,label:string,filter:string}
	 */
	private function get_cached_reference_status( $attachment_id ) {
		$cache = get_transient( 'mediarefinspector_scan_status_' . absint( $attachment_id ) );
		if ( is_array( $cache ) && isset( $cache['status'] ) ) {
			if ( 'referenced' === $cache['status'] ) {
				return array( 'class' => 'is-referenced', 'label' => __( 'Referenced', 'media-reference-inspector' ), 'filter' => 'referenced' );
			}
			if ( 'unreferenced' === $cache['status'] ) {
				return array( 'class' => 'is-review', 'label' => __( 'Review needed', 'media-reference-inspector' ), 'filter' => 'review' );
			}
		}
		return array( 'class' => 'is-pending', 'label' => __( 'Not scanned', 'media-reference-inspector' ), 'filter' => 'pending' );
	}

	/**
	 * Draws a reusable media tile, with real image, recent cached status, and scan link.
	 *
	 * @param WP_Post $attachment Attachment record.
	 * @return void
	 */
	private function render_explorer_tile( $attachment, $selectable = false ) {
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return;
		}
		$id     = absint( $attachment->ID );
		$title  = get_the_title( $id );
		$title  = $title ? $title : sprintf( /* translators: %d: Media attachment ID. */ __( 'Media item #%d', 'media-reference-inspector' ), $id );
		$mime   = (string) get_post_mime_type( $id );
		$status = $this->get_cached_reference_status( $id );
		$image  = 0 === strpos( $mime, 'image/' ) && 'image/svg+xml' !== $mime ? wp_get_attachment_image( $id, 'medium', true, array( 'loading' => 'lazy', 'decoding' => 'async' ) ) : '';
		$edit_url = get_edit_post_link( $id, 'raw' );
		$can_delete = $selectable && current_user_can( 'delete_post', $id );
		?>
		<article class="mri26-media-tile<?php echo $can_delete ? ' mri27-selectable-tile' : ''; ?>" data-mri26-status="<?php echo esc_attr( $status['filter'] ); ?>" data-mri26-name="<?php echo esc_attr( strtolower( $title . ' ' . $mime ) ); ?>" <?php if ( $can_delete ) : ?>data-mri27-id="<?php echo esc_attr( $id ); ?>"<?php endif; ?>>
			<?php if ( $can_delete ) : ?><label class="mri27-tile-select"><input type="checkbox" class="mri27-item-check" value="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Media title */ __( 'Select %s', 'media-reference-inspector' ), $title ) ); ?>" /><span class="dashicons dashicons-yes" aria-hidden="true"></span></label><?php endif; ?>
			<div class="mri26-tile-preview"><?php if ( $image ) : echo wp_kses_post( $image ); else : ?><span class="dashicons <?php echo esc_attr( 0 === strpos( $mime, 'video/' ) ? 'dashicons-format-video' : ( 0 === strpos( $mime, 'audio/' ) ? 'dashicons-format-audio' : 'dashicons-media-default' ) ); ?>" aria-hidden="true"></span><?php endif; ?><span class="mri26-tile-type"><?php echo esc_html( strtoupper( $mime ? explode( '/', $mime )[0] : 'FILE' ) ); ?></span></div>
			<div class="mri26-tile-info"><h4 title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $title ); ?></h4><p><?php echo esc_html( $mime ? $mime : __( 'Unknown format', 'media-reference-inspector' ) ); ?></p><span class="mediarefinspector-row-status <?php echo esc_attr( $status['class'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span></div>
			<div class="mri26-tile-actions"><a href="<?php echo esc_url( $this->get_scan_url( $id, '', 1 ) ); ?>"><?php esc_html_e( 'Inspect', 'media-reference-inspector' ); ?><span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span></a><?php if ( $edit_url ) : ?><a class="mri26-tile-edit" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Media title */ __( 'Edit %s in Media Library', 'media-reference-inspector' ), $title ) ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a><?php endif; ?><?php if ( $can_delete ) : ?><button type="button" class="mri27-tile-delete" data-mri27-single="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Media title */ __( 'Review deletion of %s', 'media-reference-inspector' ), $title ) ); ?>" title="<?php esc_attr_e( 'Review deletion', 'media-reference-inspector' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button><?php endif; ?></div>
		</article>
		<?php
	}

	/**
	 * Paged searchable media explorer, without automatic reference scans.
	 *
	 * @return void
	 */
	private function render_explorer_tab() {
		$search = $this->get_search_value();
		$type   = $this->get_type_filter();
		$paged  = $this->get_page_number();
		$args = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 18, 'paged' => $paged, 'orderby' => 'date', 'order' => 'DESC' );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( '' !== $type ) {
			$args['post_mime_type'] = $type;
		}
		$media = new WP_Query( $args );
		?>
		<section class="mri26-explorer" aria-labelledby="mri26-explorer-heading">
			<div class="mri26-explorer-banner"><div><span class="mri26-eyebrow"><?php esc_html_e( 'YOUR MEDIA WORKSPACE', 'media-reference-inspector' ); ?></span><h2 id="mri26-explorer-heading"><?php esc_html_e( 'Explore & manage media.', 'media-reference-inspector' ); ?></h2><p><?php esc_html_e( 'Search, inspect, and select media. Nothing is removed without a review and explicit confirmation.', 'media-reference-inspector' ); ?></p></div><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span></div>
			<?php $this->render_filter_form( 'explorer', $search, $type ); ?>
			<?php $this->render_cleanup_notice(); ?>
			<div class="mri26-explorer-meta"><strong><?php echo esc_html( sprintf( /* translators: %s: Total matching attachments */ __( '%s matching media items', 'media-reference-inspector' ), number_format_i18n( $media->found_posts ) ) ); ?></strong><span><?php esc_html_e( 'Cached scan status only • No automatic removal', 'media-reference-inspector' ); ?></span></div>
			<?php if ( $media->have_posts() ) : ?>
			<div class="mri27-selection-toolbar" data-mri27-toolbar>
				<div class="mri27-selection-left"><label class="mri27-select-all"><input type="checkbox" id="mri27-select-all" /><span><?php esc_html_e( 'Select this page', 'media-reference-inspector' ); ?></span></label><span class="mri27-selected-count" id="mri27-selected-count" role="status" aria-live="polite"><?php esc_html_e( '0 selected', 'media-reference-inspector' ); ?></span><button type="button" class="mri27-clear" id="mri27-clear-selection" disabled><?php esc_html_e( 'Clear', 'media-reference-inspector' ); ?></button></div>
				<button type="button" class="mri27-delete-button" id="mri27-review-selection" disabled><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Review deletion', 'media-reference-inspector' ); ?></button>
			</div>
			<?php endif; ?>
			<?php if ( $media->have_posts() ) : ?><div class="mri26-media-gallery mri26-explorer-grid">
				<?php while ( $media->have_posts() ) : $media->the_post(); $this->render_explorer_tile( get_post(), true ); endwhile; ?>
			</div><?php else : ?><div class="mri26-empty-gallery"><span class="dashicons dashicons-search" aria-hidden="true"></span><h3><?php esc_html_e( 'No matching media', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Try a different title search or file type.', 'media-reference-inspector' ); ?></p></div><?php endif; ?>
			<?php wp_reset_postdata(); $this->render_pagination( $media, $search, $type, $paged, 'explorer' ); ?>
			<p class="mri26-explorer-disclaimer"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'A missing scan cache is not proof of an unused file. Deleting or trashing media can break posts, products and other integrations.', 'media-reference-inspector' ); ?></p>
		<?php $this->render_cleanup_dialog(); ?>
		</section>
		<?php
	}

	/**
	 * Whether WordPress's built-in Media Trash is available.
	 *
	 * Sites without MEDIA_TRASH permanently remove attachments by default.
	 *
	 * @return bool
	 */
	private function media_trash_enabled() {
		return defined( 'MEDIA_TRASH' ) && MEDIA_TRASH && defined( 'EMPTY_TRASH_DAYS' ) && EMPTY_TRASH_DAYS > 0;
	}

	/**
	 * Unique, current-user-scoped one-time confirmation token key.
	 *
	 * @param string $token Random review token.
	 * @return string
	 */
	private function cleanup_token_key( $token ) {
		return 'mediarefinspector_cleanup_' . get_current_user_id() . '_' . hash( 'sha256', $token );
	}

	/**
	 * Inspect selected attachment records without changing any data.
	 *
	 * @return void
	 */
	public function ajax_delete_preview() {
		check_ajax_referer( 'mediarefinspector_delete_media', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage media.', 'media-reference-inspector' ) ), 403 );
		}
		// Sanitize each submitted element (including malicious nested input) before validating attachment IDs.
		$raw = isset( $_POST['ids'] ) && is_array( $_POST['ids'] )
			? map_deep( wp_unslash( $_POST['ids'] ), 'sanitize_text_field' )
			: array();
		if ( ! $raw || count( $raw ) > 18 || count( array_filter( $raw, 'is_scalar' ) ) !== count( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose 1 to 18 media items from the current page.', 'media-reference-inspector' ) ), 400 );
		}
		$ids = array();
		foreach ( $raw as $raw_id ) {
			// Reject negatives, decimals, zero, and mixed text instead of coercing them to another attachment ID.
			if ( ! preg_match( '/^[1-9][0-9]*$/D', (string) $raw_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid media ID. Reload Media Explorer and try again.', 'media-reference-inspector' ) ), 400 );
			}
			$id = absint( $raw_id );
			if ( ! $id || (string) $id !== (string) $raw_id ) {
				wp_send_json_error( array( 'message' => __( 'Invalid media ID. Reload Media Explorer and try again.', 'media-reference-inspector' ) ), 400 );
			}
			$ids[ $id ] = $id;
		}
		$ids = array_values( $ids );
		/* translators: %d: WordPress attachment ID of a media file without a title. */
		$untitled_media_label = __( 'Media #%d', 'media-reference-inspector' );
		$items = array();
		foreach ( $ids as $id ) {
			$attachment = get_post( $id );
			if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || 'trash' === $attachment->post_status || ! current_user_can( 'delete_post', $id ) ) {
				wp_send_json_error( array( 'message' => __( 'One or more files are missing or you cannot delete them. Reload the explorer.', 'media-reference-inspector' ) ), 403 );
			}
			$cached = get_transient( 'mediarefinspector_scan_status_' . $id );
			$scan_status = 'not_scanned';
			if ( is_array( $cached ) && isset( $cached['status'] ) && in_array( $cached['status'], array( 'referenced', 'unreferenced' ), true ) ) {
				$scan_status = $cached['status'];
			}
			$title = get_the_title( $id );
			$items[] = array(
				'id' => $id,
				'title' => $title ? wp_strip_all_tags( $title ) : sprintf( $untitled_media_label, $id ),
				'type' => (string) get_post_mime_type( $id ),
				'status' => $scan_status,
				'knownReferences' => 'referenced' === $scan_status ? absint( isset( $cached['count'] ) ? $cached['count'] : 0 ) : 0,
			);
		}
		if ( ! $items ) {
			wp_send_json_error( array( 'message' => __( 'No valid attachments selected.', 'media-reference-inspector' ) ), 400 );
		}
		$token = wp_generate_password( 32, false, false );
		$trash = $this->media_trash_enabled();
		set_transient( $this->cleanup_token_key( $token ), array( 'ids' => $ids, 'trash' => $trash ), 5 * MINUTE_IN_SECONDS );
		wp_send_json_success( array( 'token' => $token, 'items' => $items, 'trash' => $trash, 'count' => count( $items ) ) );
	}

	/**
	 * Perform only the attachments approved by an explicit, one-time review.
	 *
	 * @return void
	 */
	public function ajax_delete_confirm() {
		check_ajax_referer( 'mediarefinspector_delete_media', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage media.', 'media-reference-inspector' ) ), 403 );
		}
		$token = isset( $_POST['token'] ) && is_string( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$word = isset( $_POST['confirmation'] ) && is_string( $_POST['confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation'] ) ) : '';
		$ack = isset( $_POST['acknowledge'] ) && '1' === $_POST['acknowledge'];
		if ( ! $ack || 'DELETE' !== $word || ! preg_match( '/^[a-zA-Z0-9]{32}$/D', $token ) ) {
			wp_send_json_error( array( 'message' => __( 'You must acknowledge the risk and type DELETE to confirm.', 'media-reference-inspector' ) ), 400 );
		}
		$key = $this->cleanup_token_key( $token );
		$review = get_transient( $key );
		if ( ! is_array( $review ) || empty( $review['ids'] ) || ! is_array( $review['ids'] ) || count( $review['ids'] ) > 18 ) {
			wp_send_json_error( array( 'message' => __( 'This review expired. Please review the selected files again.', 'media-reference-inspector' ) ), 400 );
		}
		if ( ! isset( $review['trash'] ) || (bool) $review['trash'] !== $this->media_trash_enabled() ) {
			delete_transient( $key );
			wp_send_json_error( array( 'message' => __( 'Media Trash settings changed. Review the selection again.', 'media-reference-inspector' ) ), 409 );
		}
		// One-time token is consumed before any destructive operations.
		delete_transient( $key );
		$removed = 0;
		$failed = array();
		foreach ( $review['ids'] as $item ) {
			$id = absint( $item );
			$post = $id ? get_post( $id ) : null;
			if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || 'trash' === $post->post_status || ! current_user_can( 'delete_post', $id ) ) {
				$failed[] = $id;
				continue;
			}
			// false respects WordPress Media Trash if the site enables it; otherwise this is permanent.
			$result = wp_delete_attachment( $id, false );
			$after = get_post( $id );
			$success = $result && ( $review['trash'] ? ( $after instanceof WP_Post && 'trash' === $after->post_status ) : ! $after );
			if ( $success ) {
				++$removed;
				delete_transient( 'mediarefinspector_scan_status_' . $id );
			} else {
				$failed[] = $id;
			}
		}
		set_transient( 'mediarefinspector_cleanup_result_' . get_current_user_id(), array( 'removed' => $removed, 'failed' => $failed, 'trash' => (bool) $review['trash'] ), 2 * MINUTE_IN_SECONDS );
		wp_send_json_success( array( 'removed' => $removed, 'failed' => $failed, 'trash' => (bool) $review['trash'] ) );
	}

	/**
	 * One-time server-side result banner displayed after a successful AJAX request.
	 *
	 * @return void
	 */
	private function render_cleanup_notice() {
		$key = 'mediarefinspector_cleanup_result_' . get_current_user_id();
		$result = get_transient( $key );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( $key );
		$count = absint( isset( $result['removed'] ) ? $result['removed'] : 0 );
		$failed = isset( $result['failed'] ) && is_array( $result['failed'] ) ? array_map( 'absint', $result['failed'] ) : array();
		?>
		<div class="mri27-result-notice" role="status">
			<?php if ( $count ) : ?><p><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><strong><?php echo esc_html( sprintf( /* translators: %d: Number of affected attachments. */ $result['trash'] ? _n( '%d file moved to Media Trash.', '%d files moved to Media Trash.', $count, 'media-reference-inspector' ) : _n( '%d file permanently deleted.', '%d files permanently deleted.', $count, 'media-reference-inspector' ), $count ) ); ?></strong></p><?php endif; ?>
			<?php if ( $failed ) : ?><p class="mri27-result-failure"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php echo esc_html( sprintf( /* translators: %s: Comma-separated attachment IDs that could not be removed. */ __( 'Could not remove attachment ID(s): %s. Check permissions or conflicting plugins.', 'media-reference-inspector' ), implode( ', ', $failed ) ) ); ?></p><?php endif; ?>
			<?php if ( ! empty( $result['trash'] ) && $count ) : ?><p><a href="<?php echo esc_url( add_query_arg( array( 'post_status' => 'trash', 'post_type' => 'attachment', 'mode' => 'list' ), admin_url( 'upload.php' ) ) ); ?>"><?php esc_html_e( 'View Media Trash and restore files', 'media-reference-inspector' ); ?></a></p><?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Accessible confirmation dialog markup. JS requests a one-time preview first.
	 *
	 * @return void
	 */
	private function render_cleanup_dialog() {
		?>
		<div class="mri27-dialog-backdrop" id="mri27-dialog-backdrop" hidden>
			<section class="mri27-dialog" role="dialog" aria-modal="true" aria-labelledby="mri27-dialog-heading" aria-describedby="mri27-dialog-description" tabindex="-1">
				<header class="mri27-dialog-header"><span class="mri27-dialog-icon"><span class="dashicons dashicons-warning" aria-hidden="true"></span></span><div><span class="mri27-dialog-kicker"><?php esc_html_e( 'FINAL SAFETY REVIEW', 'media-reference-inspector' ); ?></span><h2 id="mri27-dialog-heading"><?php esc_html_e( 'Review selected media', 'media-reference-inspector' ); ?></h2></div><button type="button" id="mri27-dialog-close" class="mri27-dialog-close" aria-label="<?php esc_attr_e( 'Close review', 'media-reference-inspector' ); ?>">&times;</button></header>
				<p class="mri27-dialog-description" id="mri27-dialog-description"><?php esc_html_e( 'Review these files carefully. A scan cannot guarantee a file is unused in templates, products, themes or custom code.', 'media-reference-inspector' ); ?></p>
				<div class="mri27-dialog-mode" id="mri27-dialog-mode"></div>
				<div class="mri27-dialog-items" id="mri27-dialog-items" role="list"></div>
				<div class="mri27-dialog-confirmation" id="mri27-dialog-confirmation" hidden>
					<label class="mri27-ack"><input type="checkbox" id="mri27-ack" /><span><?php esc_html_e( 'I understand that removing these files may break pages, products, galleries or theme elements, even if no references were found.', 'media-reference-inspector' ); ?></span></label>
					<label class="mri27-type-label" for="mri27-confirm-word"><?php esc_html_e( 'Type DELETE to authorize exactly the files listed above:', 'media-reference-inspector' ); ?></label>
					<input type="text" id="mri27-confirm-word" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="DELETE" />
				</div>
				<p class="mri27-dialog-feedback" id="mri27-dialog-feedback" role="alert" aria-live="assertive"></p>
				<footer class="mri27-dialog-footer"><button type="button" id="mri27-dialog-cancel" class="button"><?php esc_html_e( 'Cancel', 'media-reference-inspector' ); ?></button><button type="button" id="mri27-dialog-approve" class="mri27-approve" disabled><?php esc_html_e( 'Confirm removal', 'media-reference-inspector' ); ?></button></footer>
			</section>
		</div>
		<?php
	}

	/**
	 * Renders the single-item scanner.
	 *
	 * @return void
	 */
	private function render_scanner_tab() {
		$search = $this->get_search_value();
		$type   = $this->get_type_filter();
		$paged  = $this->get_page_number();
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-scanner-heading">
			<div class="mediarefinspector-section-heading">
				<div>
					<h2 id="mediarefinspector-scanner-heading"><?php esc_html_e( 'Inspect one media item', 'media-reference-inspector' ); ?></h2>
					<p><?php esc_html_e( 'Search your Media Library, choose an item, and inspect supported WordPress references without changing the site.', 'media-reference-inspector' ); ?></p>
				</div>
			</div>

			<div class="mediarefinspector-coverage-strip" aria-label="<?php echo esc_attr__( 'Reference coverage', 'media-reference-inspector' ); ?>">
				<span><strong><?php esc_html_e( 'Core', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Content, blocks, featured media, menus and theme settings', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'Widgets', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Core media widgets and block widgets', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'WooCommerce', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Product galleries and category thumbnails', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'Elementor', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Validated saved media controls', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'ACF', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Confirmed Image, File and Gallery field references', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'Metadata', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Validated media-like post, term and option metadata', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'Builders', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Bricks, Divi and Beaver Builder saved media data', 'media-reference-inspector' ); ?></span>
			</div>

			<?php $this->render_integration_coverage(); ?>
			<?php $this->render_whats_new_panel(); ?>
			<?php $this->maybe_render_scan_results(); ?>
			<?php $this->render_filter_form( 'scanner', $search, $type ); ?>
			<?php $this->render_media_table( $search, $type, $paged ); ?>
		</section>
		<?php
	}

	/**
	 * Renders the bulk-scan workspace.
	 *
	 * @return void
	 */
	private function render_bulk_tab() {
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-bulk-heading">
			<div class="mediarefinspector-section-heading mediarefinspector-section-heading-split">
				<div>
					<h2 id="mediarefinspector-bulk-heading"><?php esc_html_e( 'Bulk reference scan', 'media-reference-inspector' ); ?></h2>
					<p><?php esc_html_e( 'Scan a filtered group of media items one at a time to avoid long-running requests. Nothing is deleted or modified.', 'media-reference-inspector' ); ?></p>
				</div>
				<div class="mediarefinspector-section-actions">
					<button type="button" class="button" id="mediarefinspector-export-csv" disabled><?php esc_html_e( 'Export CSV', 'media-reference-inspector' ); ?></button>
					<button type="button" class="button" id="mediarefinspector-export-html" disabled><?php esc_html_e( 'Printable HTML report', 'media-reference-inspector' ); ?></button>
					<button type="button" class="button" id="mediarefinspector-export-json" disabled><?php esc_html_e( 'Export JSON', 'media-reference-inspector' ); ?></button>
				</div>
			</div>

			<div class="mediarefinspector-panel mediarefinspector-bulk-controls">
				<div class="mediarefinspector-field">
					<label for="mediarefinspector-bulk-search"><?php esc_html_e( 'Search media', 'media-reference-inspector' ); ?></label>
					<input type="search" id="mediarefinspector-bulk-search" placeholder="<?php echo esc_attr__( 'Title or description', 'media-reference-inspector' ); ?>" />
				</div>
				<div class="mediarefinspector-field">
					<label for="mediarefinspector-bulk-type"><?php esc_html_e( 'Media type', 'media-reference-inspector' ); ?></label>
					<select id="mediarefinspector-bulk-type">
						<?php $this->render_type_options( '' ); ?>
					</select>
				</div>
				<div class="mediarefinspector-field">
					<label for="mediarefinspector-bulk-age"><?php esc_html_e( 'Uploaded', 'media-reference-inspector' ); ?></label>
					<select id="mediarefinspector-bulk-age">
						<option value="0"><?php esc_html_e( 'Any time', 'media-reference-inspector' ); ?></option>
						<option value="30"><?php esc_html_e( 'Last 30 days', 'media-reference-inspector' ); ?></option>
						<option value="90"><?php esc_html_e( 'Last 90 days', 'media-reference-inspector' ); ?></option>
						<option value="365"><?php esc_html_e( 'Last year', 'media-reference-inspector' ); ?></option>
					</select>
				</div>
				<div class="mediarefinspector-field">
					<label for="mediarefinspector-bulk-limit"><?php esc_html_e( 'Maximum items', 'media-reference-inspector' ); ?></label>
					<select id="mediarefinspector-bulk-limit">
						<option value="25">25</option>
						<option value="50">50</option>
						<option value="100" selected>100</option>
						<option value="250">250</option>
					</select>
				</div>
				<div class="mediarefinspector-field mediarefinspector-field-grow">
					<label for="mediarefinspector-selected-ids"><?php esc_html_e( 'Specific media IDs', 'media-reference-inspector' ); ?></label>
					<input type="text" id="mediarefinspector-selected-ids" inputmode="numeric" placeholder="<?php echo esc_attr__( 'Optional: 12, 34, 56', 'media-reference-inspector' ); ?>" />
					<span class="description"><?php esc_html_e( 'Enter attachment IDs to scan only those items. Other search filters are ignored when IDs are supplied.', 'media-reference-inspector' ); ?></span>
				</div>
				<div class="mediarefinspector-field mediarefinspector-field-action">
					<button type="button" class="button button-primary" id="mediarefinspector-start-bulk"><?php esc_html_e( 'Start bulk scan', 'media-reference-inspector' ); ?></button>
					<button type="button" class="button" id="mediarefinspector-stop-bulk" hidden><?php esc_html_e( 'Stop', 'media-reference-inspector' ); ?></button>
				</div>
			</div>

			<div class="mediarefinspector-progress" id="mediarefinspector-bulk-progress" hidden>
				<div class="mediarefinspector-progress-header">
					<strong id="mediarefinspector-progress-status"><?php esc_html_e( 'Preparing media scan…', 'media-reference-inspector' ); ?></strong>
					<span id="mediarefinspector-progress-count">0 / 0</span>
				</div>
				<progress id="mediarefinspector-progress-bar" value="0" max="100">0%</progress>
			</div>

			<div class="mediarefinspector-summary-grid" id="mediarefinspector-bulk-summary" hidden aria-live="polite">
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Scanned', 'media-reference-inspector' ); ?></span><strong data-summary="scanned">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></span><strong data-summary="referenced">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'No supported references', 'media-reference-inspector' ); ?></span><strong data-summary="unreferenced">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></span><strong data-summary="errors">0</strong></div>
			</div>

			<div class="mediarefinspector-bulk-filter-row" id="mediarefinspector-bulk-filter-row" hidden>
				<div><label for="mediarefinspector-result-filter"><?php esc_html_e( 'Show results', 'media-reference-inspector' ); ?></label>
				<select id="mediarefinspector-result-filter"><option value="all"><?php esc_html_e( 'All results', 'media-reference-inspector' ); ?></option><option value="referenced"><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></option><option value="unreferenced"><?php esc_html_e( 'Potential unused review', 'media-reference-inspector' ); ?></option><option value="error"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option></select></div>
				<div><label for="mediarefinspector-result-sort"><?php esc_html_e( 'Sort results', 'media-reference-inspector' ); ?></label>
				<select id="mediarefinspector-result-sort"><option value="scan"><?php esc_html_e( 'Scan order', 'media-reference-inspector' ); ?></option><option value="references-desc"><?php esc_html_e( 'Most references', 'media-reference-inspector' ); ?></option><option value="references-asc"><?php esc_html_e( 'Fewest references', 'media-reference-inspector' ); ?></option><option value="title"><?php esc_html_e( 'Title A–Z', 'media-reference-inspector' ); ?></option></select></div>
		
				<div><label for="mediarefinspector-source-filter"><?php esc_html_e( 'Evidence source', 'media-reference-inspector' ); ?></label><select id="mediarefinspector-source-filter"><option value="all"><?php esc_html_e( 'All sources', 'media-reference-inspector' ); ?></option><option value="core-id"><?php esc_html_e( 'Exact ID / block', 'media-reference-inspector' ); ?></option><option value="core-url"><?php esc_html_e( 'URL / content marker', 'media-reference-inspector' ); ?></option><option value="integration"><?php esc_html_e( 'Integration metadata', 'media-reference-inspector' ); ?></option><option value="widget"><?php esc_html_e( 'Widget data', 'media-reference-inspector' ); ?></option><option value="setting"><?php esc_html_e( 'Site/theme setting', 'media-reference-inspector' ); ?></option></select></div>
				<div><label for="mediarefinspector-health-filter"><?php esc_html_e( 'File health', 'media-reference-inspector' ); ?></label><select id="mediarefinspector-health-filter"><option value="all"><?php esc_html_e( 'Any health', 'media-reference-inspector' ); ?></option><option value="healthy"><?php esc_html_e( 'Healthy', 'media-reference-inspector' ); ?></option><option value="review"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option></select></div>
			</div>

			<div class="mediarefinspector-empty-state" id="mediarefinspector-bulk-empty">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'Ready for a read-only audit', 'media-reference-inspector' ); ?></h3>
				<p><?php esc_html_e( 'Choose optional filters and start a scan. Results will appear here as each media item is checked.', 'media-reference-inspector' ); ?></p>
			</div>

			<div class="mediarefinspector-table-wrap" id="mediarefinspector-bulk-table-wrap" hidden>
				<table class="widefat striped mediarefinspector-table mediarefinspector-bulk-table">
					<thead><tr><th><?php esc_html_e( 'Media', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'Type', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'Result', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'References', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'Actions', 'media-reference-inspector' ); ?></th></tr></thead>
					<tbody id="mediarefinspector-bulk-results"></tbody>
				</table>
			</div>

			<p class="description mediarefinspector-safety-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'A result of “No supported references found” is advisory and does not prove that a file is unused.', 'media-reference-inspector' ); ?></p>
		</section>
		<?php
	}



	/**
	 * Shows a compact What's New card until new feature tabs have been visited.
	 *
	 * @return void
	 */
	private function render_whats_new_panel() {
		if ( ! $this->is_new_feature( 'bulk' ) && ! $this->is_new_feature( 'site-audit' ) ) {
			return;
		}
		$bulk_url = add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'bulk' ), admin_url( 'upload.php' ) );
		$site_audit_url = add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'site-audit' ), admin_url( 'upload.php' ) );
		?>
		<div class="mediarefinspector-whats-new mediarefinspector-panel">
			<div><span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span><strong><?php esc_html_e( 'What’s new in Media Reference Inspector 2.7.1', 'media-reference-inspector' ); ?></strong></div>
			<ul class="mediarefinspector-check-list">
				<li><strong><?php esc_html_e( 'Scanner:', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'New reference footprint visualization for supported scan results.', 'media-reference-inspector' ); ?></li>
				<li><strong><?php esc_html_e( 'Bulk Scan:', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'Safer CSV export, selected IDs, evidence filters, JSON and printable HTML reports.', 'media-reference-inspector' ); ?></li>
				<li><strong><?php esc_html_e( 'Dashboard:', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'Premium overview, media mix and a searchable recent-media gallery.', 'media-reference-inspector' ); ?></li>
				<li><strong><?php esc_html_e( 'Media Explorer:', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'Select individual or multiple media files, review risks, and explicitly confirm removal.', 'media-reference-inspector' ); ?></li>
			</ul>
			<p><a class="button" href="<?php echo esc_url( $bulk_url ); ?>"><?php esc_html_e( 'Open Bulk Scan', 'media-reference-inspector' ); ?></a> <a class="button button-primary" href="<?php echo esc_url( $site_audit_url ); ?>"><?php esc_html_e( 'Open Site Audit', 'media-reference-inspector' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Renders page/post media audit results.
	 *
	 * @return void
	 */
	private function render_audit_tab() {
		$search  = isset( $_GET['audit_s'] ) ? sanitize_text_field( wp_unslash( $_GET['audit_s'] ) ) : '';
		$post_id = isset( $_GET['audit_post_id'] ) ? absint( $_GET['audit_post_id'] ) : 0;
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-audit-heading">
			<div class="mediarefinspector-section-heading"><div><h2 id="mediarefinspector-audit-heading"><?php esc_html_e( 'Page & post media audit', 'media-reference-inspector' ); ?></h2><p><?php esc_html_e( 'Choose a post or page to see supported media references, broken attachment IDs, and local file-health information. Nothing is modified.', 'media-reference-inspector' ); ?></p></div></div>
			<form method="get" class="mediarefinspector-filter-form"><input type="hidden" name="page" value="media-reference-inspector" /><input type="hidden" name="tab" value="audit" /><div class="mediarefinspector-field mediarefinspector-field-grow"><label for="mediarefinspector-audit-search"><?php esc_html_e( 'Search posts/pages', 'media-reference-inspector' ); ?></label><input id="mediarefinspector-audit-search" type="search" name="audit_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search title', 'media-reference-inspector' ); ?>" /></div><div class="mediarefinspector-field mediarefinspector-field-action"><button class="button button-secondary" type="submit"><?php esc_html_e( 'Search', 'media-reference-inspector' ); ?></button></div></form>
			<?php
			if ( $post_id ) {
				$nonce = isset( $_GET['mediarefinspector_audit_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_audit_nonce'] ) ) : '';
				if ( wp_verify_nonce( $nonce, 'mediarefinspector_audit_post_' . $post_id ) ) {
					$this->render_post_audit_result( $post_id );
				} else {
					$this->render_notice( __( 'The audit request could not be verified. Please choose the post again.', 'media-reference-inspector' ), 'error' );
				}
			}
			$post_types = get_post_types( array( 'public' => true ), 'names' );
			unset( $post_types['attachment'] );
			$args = array( 'post_type' => array_values( $post_types ), 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 30, 'orderby' => 'modified', 'order' => 'DESC' );
			if ( $search ) { $args['s'] = $search; }
			$query = new WP_Query( $args );
			?>
			<div class="mediarefinspector-audit-list">
			<?php foreach ( $query->posts as $post ) : $url = wp_nonce_url( add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'audit', 'audit_post_id' => $post->ID, 'audit_s' => $search ), admin_url( 'upload.php' ) ), 'mediarefinspector_audit_post_' . $post->ID, 'mediarefinspector_audit_nonce' ); ?>
				<article class="mediarefinspector-panel mediarefinspector-audit-item"><div><strong><?php echo esc_html( get_the_title( $post ) ? get_the_title( $post ) : sprintf( /* translators: %d: Post ID. */ __( 'Post #%d', 'media-reference-inspector' ), $post->ID ) ); ?></strong><p><?php echo esc_html( $post->post_type . ' · ' . $post->post_status . ' · #' . $post->ID ); ?></p></div><a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Audit media', 'media-reference-inspector' ); ?></a></article>
			<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Renders one post audit result.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_post_audit_result( $post_id ) {
		$service = new MediaRefInspector_Audit_Service();
		$result  = $service->audit_post( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post ) { return; }
		?>
		<div class="mediarefinspector-panel mediarefinspector-audit-result"><div class="mediarefinspector-section-heading mediarefinspector-section-heading-split"><div><span class="mediarefinspector-eyebrow"><?php esc_html_e( 'Audit result', 'media-reference-inspector' ); ?></span><h3><?php echo esc_html( get_the_title( $post ) ? get_the_title( $post ) : '#' . $post_id ); ?></h3></div><div class="mediarefinspector-audit-counts"><span><strong><?php echo esc_html( (string) count( $result['media'] ) ); ?></strong> <?php esc_html_e( 'media found', 'media-reference-inspector' ); ?></span><span class="<?php echo empty( $result['broken'] ) ? 'is-success' : 'is-warning'; ?>"><strong><?php echo esc_html( (string) count( $result['broken'] ) ); ?></strong> <?php esc_html_e( 'broken IDs', 'media-reference-inspector' ); ?></span></div></div>
		<?php if ( ! empty( $result['broken'] ) ) : ?><div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Broken media references need review.', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'These attachment IDs are referenced in supported content but no longer resolve to Media Library attachments:', 'media-reference-inspector' ); ?> <?php echo esc_html( implode( ', ', wp_list_pluck( $result['broken'], 'id' ) ) ); ?></p></div><?php endif; ?>
		<div class="mediarefinspector-audit-media-grid">
		<?php foreach ( $result['media'] as $item ) : ?>
			<article class="mediarefinspector-audit-media"><div><strong><?php echo esc_html( $item['title'] ? $item['title'] : sprintf( /* translators: %d: Media attachment ID. */ __( 'Media #%d', 'media-reference-inspector' ), $item['id'] ) ); ?></strong><p><?php echo esc_html( implode( ' · ', $item['sources'] ) ); ?></p></div><span class="mediarefinspector-health-pill is-<?php echo esc_attr( $item['health']['status'] ); ?>"><?php echo esc_html( 'healthy' === $item['health']['status'] ? __( 'File healthy', 'media-reference-inspector' ) : __( 'Needs review', 'media-reference-inspector' ) ); ?></span><a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $item['id'], 'raw' ) ); ?>"><?php esc_html_e( 'Edit media', 'media-reference-inspector' ); ?></a></article>
		<?php endforeach; ?>
		</div></div>
		<?php
	}

	/**
	 * Renders the exact duplicate-file finder.
	 *
	 * @return void
	 */
	private function render_duplicates_tab() {
		$run = isset( $_GET['run_duplicates'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['run_duplicates'] ) );
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-duplicates-heading"><div class="mediarefinspector-section-heading mediarefinspector-section-heading-split"><div><h2 id="mediarefinspector-duplicates-heading"><?php esc_html_e( 'Potential duplicate media', 'media-reference-inspector' ); ?></h2><p><?php esc_html_e( 'Compare a bounded set of local media files by exact file hash. This tool reports matches only and never deletes anything.', 'media-reference-inspector' ); ?></p></div><?php $run_url = wp_nonce_url( add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'duplicates', 'run_duplicates' => '1' ), admin_url( 'upload.php' ) ), 'mediarefinspector_run_duplicates', 'mediarefinspector_duplicates_nonce' ); ?><a class="button button-primary" href="<?php echo esc_url( $run_url ); ?>"><?php esc_html_e( 'Scan recent 150 files', 'media-reference-inspector' ); ?></a></div>
		<?php
		if ( $run ) {
			$nonce = isset( $_GET['mediarefinspector_duplicates_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_duplicates_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'mediarefinspector_run_duplicates' ) ) { $this->render_notice( __( 'The duplicate scan request could not be verified.', 'media-reference-inspector' ), 'error' ); return; }
			$groups = ( new MediaRefInspector_Audit_Service() )->find_exact_duplicates( 150 );
			if ( empty( $groups ) ) { $this->render_notice( __( 'No exact duplicate files were found in the bounded scan.', 'media-reference-inspector' ), 'success' ); }
			foreach ( $groups as $index => $group ) : ?>
				<div class="mediarefinspector-panel mediarefinspector-duplicate-group"><h3><?php echo esc_html( sprintf( /* translators: 1: Duplicate group number, 2: File size. */ __( 'Duplicate group %1$d · %2$s each', 'media-reference-inspector' ), $index + 1, size_format( $group['size'], 1 ) ) ); ?></h3><?php foreach ( $group['items'] as $item ) : ?><div class="mediarefinspector-duplicate-item"><div><strong><?php echo esc_html( $item['title'] ? $item['title'] : $item['filename'] ); ?></strong><code><?php echo esc_html( $item['filename'] ); ?></code></div><?php if ( $item['edit_url'] ) : ?><a class="button button-small" href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php esc_html_e( 'Review media', 'media-reference-inspector' ); ?></a><?php endif; ?></div><?php endforeach; ?></div>
			<?php endforeach;
		} else { ?>
			<div class="mediarefinspector-empty-state"><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span><h3><?php esc_html_e( 'Ready for an exact duplicate scan', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'For performance, the scan is limited to the most recent 150 readable files and skips files larger than 25 MB.', 'media-reference-inspector' ); ?></p></div>
		<?php } ?>
		</section>
		<?php
	}

	/**
	 * Renders local file-health details for one attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function render_file_health( $attachment_id ) {
		$health = ( new MediaRefInspector_Audit_Service() )->get_file_health( $attachment_id );
		?>
		<div class="mediarefinspector-panel mediarefinspector-file-health"><div class="mediarefinspector-file-health-title"><h3><?php esc_html_e( 'Media file health', 'media-reference-inspector' ); ?></h3><span class="mediarefinspector-health-pill is-<?php echo esc_attr( $health['status'] ); ?>"><?php echo esc_html( 'healthy' === $health['status'] ? __( 'Healthy', 'media-reference-inspector' ) : __( 'Needs review', 'media-reference-inspector' ) ); ?></span></div><div class="mediarefinspector-health-grid"><span><strong><?php esc_html_e( 'Local file', 'media-reference-inspector' ); ?></strong><?php echo esc_html( $health['file_exists'] ? __( 'Found', 'media-reference-inspector' ) : __( 'Missing', 'media-reference-inspector' ) ); ?></span><span><strong><?php esc_html_e( 'Original image', 'media-reference-inspector' ); ?></strong><?php echo esc_html( $health['original_exists'] ? __( 'Available / not required', 'media-reference-inspector' ) : __( 'Missing', 'media-reference-inspector' ) ); ?></span><span><strong><?php esc_html_e( 'Metadata', 'media-reference-inspector' ); ?></strong><?php echo esc_html( $health['metadata_ok'] ? __( 'Looks valid', 'media-reference-inspector' ) : __( 'Incomplete', 'media-reference-inspector' ) ); ?></span><span><strong><?php esc_html_e( 'File details', 'media-reference-inspector' ); ?></strong><?php echo esc_html( trim( ( $health['width'] && $health['height'] ? $health['width'] . '×' . $health['height'] . ' · ' : '' ) . ( $health['file_size'] ? size_format( $health['file_size'], 1 ) : __( 'Size unavailable', 'media-reference-inspector' ) ) ) ); ?></span></div></div>
		<?php
	}

	/**
	 * Returns whether a feature should still display its per-user NEW badge.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	private function is_new_feature( $feature ) {
		if ( ! in_array( $feature, array( 'dashboard', 'explorer', 'bulk', 'site-audit' ), true ) ) {
			return false;
		}
		$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_260', true );
		$seen = is_array( $seen ) ? $seen : array();
		return empty( $seen[ $feature ] );
	}

	/**
	 * Marks a 2.6 feature tab as seen after rendering.
	 *
	 * @param string $feature Feature key.
	 * @return void
	 */
	private function mark_feature_seen( $feature ) {
		if ( ! in_array( $feature, array( 'dashboard', 'explorer', 'bulk', 'site-audit' ), true ) ) {
			return;
		}
		$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_260', true );
		$seen = is_array( $seen ) ? $seen : array();
		$seen[ $feature ] = 1;
		update_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_260', $seen );
	}


	/**
	 * Renders active/not-installed coverage for supported integrations.
	 *
	 * @return void
	 */
	private function render_integration_coverage() {
		if ( ! method_exists( $this->scanner, 'get_integration_coverage' ) ) {
			return;
		}
		$coverage = $this->scanner->get_integration_coverage();
		?>
		<div class="mediarefinspector-panel mediarefinspector-integration-coverage">
			<div class="mediarefinspector-section-heading"><div><h3><?php esc_html_e( 'Integration coverage', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'See which supported reference sources are available on this site. No external service is contacted.', 'media-reference-inspector' ); ?></p></div></div>
			<div class="mediarefinspector-coverage-grid">
				<?php foreach ( $coverage as $item ) : ?>
					<div class="mediarefinspector-coverage-card"><div><strong><?php echo esc_html( $item['name'] ); ?></strong><span class="mediarefinspector-health-pill <?php echo ! empty( $item['active'] ) ? 'is-healthy' : 'is-review'; ?>"><?php echo esc_html( ! empty( $item['active'] ) ? __( 'Active', 'media-reference-inspector' ) : __( 'Not installed / inactive', 'media-reference-inspector' ) ); ?></span></div><p><?php echo esc_html( $item['detail'] ); ?></p></div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a bounded read-only scan for broken local uploads URLs in content.
	 *
	 * @return void
	 */
	private function render_broken_tab() {
		$run = isset( $_GET['run_broken'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['run_broken'] ) );
		$run_url = wp_nonce_url( add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'broken', 'run_broken' => '1' ), admin_url( 'upload.php' ) ), 'mediarefinspector_run_broken', 'mediarefinspector_broken_nonce' );
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-broken-heading">
			<div class="mediarefinspector-section-heading mediarefinspector-section-heading-split"><div><h2 id="mediarefinspector-broken-heading"><?php esc_html_e( 'Broken local media URLs', 'media-reference-inspector' ); ?></h2><p><?php esc_html_e( 'Check recent supported post content for URLs inside this site’s uploads directory whose local files no longer exist. The scan is read-only and makes no external requests.', 'media-reference-inspector' ); ?></p></div><a class="button button-primary" href="<?php echo esc_url( $run_url ); ?>"><?php esc_html_e( 'Scan for broken URLs', 'media-reference-inspector' ); ?></a></div>
			<?php if ( $run ) : ?>
				<?php $nonce = isset( $_GET['mediarefinspector_broken_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_broken_nonce'] ) ) : ''; ?>
				<?php if ( ! wp_verify_nonce( $nonce, 'mediarefinspector_run_broken' ) ) : ?>
					<?php $this->render_notice( __( 'The broken URL scan request could not be verified.', 'media-reference-inspector' ), 'error' ); ?>
				<?php elseif ( ! method_exists( $this->scanner, 'find_broken_local_upload_urls' ) ) : ?>
					<?php $this->render_notice( __( 'Broken URL scanning is not available in this build.', 'media-reference-inspector' ), 'error' ); ?>
				<?php else : ?>
					<?php $items = $this->scanner->find_broken_local_upload_urls( 100 ); ?>
					<?php if ( empty( $items ) ) : ?>
						<?php $this->render_notice( __( 'No broken local uploads URLs were found in the bounded scan.', 'media-reference-inspector' ), 'success' ); ?>
					<?php else : ?>
						<div class="mediarefinspector-reference-list mediarefinspector-broken-list">
						<?php foreach ( $items as $item ) : ?>
							<article class="mediarefinspector-reference-item"><div><strong><?php echo esc_html( $item['title'] ); ?></strong><span class="mediarefinspector-status-pill"><?php esc_html_e( 'Local file missing', 'media-reference-inspector' ); ?></span><p class="mediarefinspector-evidence"><code><?php echo esc_html( $item['url'] ); ?></code></p></div><div class="mediarefinspector-reference-actions"><?php if ( ! empty( $item['edit_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'media-reference-inspector' ); ?></a><?php endif; ?><?php if ( ! empty( $item['view_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $item['view_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'media-reference-inspector' ); ?></a><?php endif; ?></div></article>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			<?php else : ?>
				<div class="mediarefinspector-empty-state"><span class="dashicons dashicons-warning" aria-hidden="true"></span><h3><?php esc_html_e( 'Ready for a local broken-link audit', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Only URLs under this site’s WordPress uploads directory are checked. Remote URLs are ignored.', 'media-reference-inspector' ); ?></p></div>
			<?php endif; ?>
		</section>
		<?php
	}


	/**
	 * Renders contextual help and plugin support.
	 *
	 * @return void
	 */
	private function render_help_tab() {
		$current_user     = wp_get_current_user();
		$support_state_raw = filter_input( INPUT_GET, 'support_status', FILTER_UNSAFE_RAW );
		$support_state     = is_string( $support_state_raw ) ? sanitize_key( $support_state_raw ) : '';
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-help-heading">
			<div class="mediarefinspector-section-heading">
				<div>
					<h2 id="mediarefinspector-help-heading"><?php esc_html_e( 'How the inspector works', 'media-reference-inspector' ); ?></h2>
					<p><?php esc_html_e( 'The scanner checks supported WordPress and integration data locally. It does not send media or site data to an external service.', 'media-reference-inspector' ); ?></p>
				</div>
			</div>

			<?php if ( 'sent' === $support_state ) : ?>
				<div class="notice notice-success inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'Your support message was sent successfully.', 'media-reference-inspector' ); ?></p></div>
			<?php elseif ( 'invalid' === $support_state ) : ?>
				<div class="notice notice-error inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'Please enter a valid email address, subject, and message.', 'media-reference-inspector' ); ?></p></div>
			<?php elseif ( 'rate_limited' === $support_state ) : ?>
				<div class="notice notice-warning inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'Please wait a minute before sending another support message.', 'media-reference-inspector' ); ?></p></div>
			<?php elseif ( 'failed' === $support_state ) : ?>
				<div class="notice notice-error inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'WordPress could not send the email. Your hosting mail configuration may need attention.', 'media-reference-inspector' ); ?></p></div>
			<?php endif; ?>

			<div class="mediarefinspector-help-grid">
				<div class="mediarefinspector-panel">
					<h3><?php esc_html_e( 'Standard WordPress checks', 'media-reference-inspector' ); ?></h3>
					<ul class="mediarefinspector-check-list">
						<li><?php esc_html_e( 'Post, page, and custom post type content and excerpts', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Generated image-size URLs and WordPress media blocks', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Featured images and navigation menu URLs', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Core media widgets and block widgets', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Site Icon, Site Logo, Custom Logo, Header Image, and Background Image', 'media-reference-inspector' ); ?></li>
					</ul>
				</div>
				<div class="mediarefinspector-panel">
					<h3><?php esc_html_e( 'Integration-aware checks', 'media-reference-inspector' ); ?></h3>
					<ul class="mediarefinspector-check-list">
						<li><?php esc_html_e( 'WooCommerce product gallery and product-category thumbnail attachment IDs', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Elementor media-control data saved in Elementor JSON', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Validated media-like post, term, and option metadata', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Known Yoast SEO and Rank Math social-image metadata', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Validated Bricks, Divi, and Beaver Builder media data', 'media-reference-inspector' ); ?></li>
					</ul>
					<p class="description"><?php esc_html_e( 'These checks are passive: if matching plugin data is not present, no extra work is performed beyond the focused lookups.', 'media-reference-inspector' ); ?></p>
				</div>
				<div class="mediarefinspector-panel mediarefinspector-panel-warning">
					<h3><?php esc_html_e( 'Important limitation', 'media-reference-inspector' ); ?></h3>
					<p><?php esc_html_e( 'No scanner can prove that media is unused across every custom table, external service, theme, builder, shortcode, cache, or custom code path. Treat results as evidence for review, not as automatic deletion instructions.', 'media-reference-inspector' ); ?></p>
				</div>
				<div class="mediarefinspector-panel mediarefinspector-support-panel">
					<div class="mediarefinspector-support-heading">
						<div><h3><?php esc_html_e( 'Contact plugin support', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Report a bug or request a feature directly from WordPress admin.', 'media-reference-inspector' ); ?></p></div>
						<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
					</div>
					<form class="mediarefinspector-support-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="mediarefinspector_send_support_email" />
						<?php wp_nonce_field( 'mediarefinspector_send_support_email' ); ?>
						<div class="mediarefinspector-support-field"><label for="mediarefinspector-support-type"><?php esc_html_e( 'Request type', 'media-reference-inspector' ); ?></label><select id="mediarefinspector-support-type" name="support_type"><option value="bug"><?php esc_html_e( 'Bug report', 'media-reference-inspector' ); ?></option><option value="feature"><?php esc_html_e( 'Feature request', 'media-reference-inspector' ); ?></option><option value="question"><?php esc_html_e( 'General question', 'media-reference-inspector' ); ?></option></select></div>
						<div class="mediarefinspector-support-field"><label for="mediarefinspector-support-email"><?php esc_html_e( 'Your email', 'media-reference-inspector' ); ?></label><input id="mediarefinspector-support-email" name="support_email" type="email" required value="<?php echo esc_attr( $current_user->user_email ); ?>" autocomplete="email" /></div>
						<div class="mediarefinspector-support-field mediarefinspector-support-field-wide"><label for="mediarefinspector-support-subject"><?php esc_html_e( 'Subject', 'media-reference-inspector' ); ?></label><input id="mediarefinspector-support-subject" name="support_subject" type="text" maxlength="120" required placeholder="<?php echo esc_attr__( 'Briefly describe the issue or feature', 'media-reference-inspector' ); ?>" /></div>
						<div class="mediarefinspector-support-field mediarefinspector-support-field-wide"><label for="mediarefinspector-support-message"><?php esc_html_e( 'Message', 'media-reference-inspector' ); ?></label><textarea id="mediarefinspector-support-message" name="support_message" rows="7" maxlength="4000" required placeholder="<?php echo esc_attr__( 'Tell us what happened, what you expected, or what feature you would like.', 'media-reference-inspector' ); ?>"></textarea></div>
						<div class="mediarefinspector-support-actions mediarefinspector-support-field-wide"><button type="submit" class="button button-primary"><?php esc_html_e( 'Send support email', 'media-reference-inspector' ); ?></button><p class="description"><?php esc_html_e( 'Nothing is sent until you press this button. Delivery uses this WordPress site’s configured mail system.', 'media-reference-inspector' ); ?></p></div>
					</form>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Sends an explicitly submitted support email.
	 *
	 * @return void
	 */
	public function handle_support_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to send plugin support messages.', 'media-reference-inspector' ) );
		}
		check_admin_referer( 'mediarefinspector_send_support_email' );
		$type    = isset( $_POST['support_type'] ) ? sanitize_key( wp_unslash( $_POST['support_type'] ) ) : 'question';
		$email   = isset( $_POST['support_email'] ) ? sanitize_email( wp_unslash( $_POST['support_email'] ) ) : '';
		$subject = isset( $_POST['support_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['support_subject'] ) ) : '';
		$message = isset( $_POST['support_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['support_message'] ) ) : '';
		if ( ! in_array( $type, array( 'bug', 'feature', 'question' ), true ) ) { $type = 'question'; }
		if ( ! is_email( $email ) || '' === $subject || '' === $message ) { $this->redirect_support_result( 'invalid' ); }
		$user_id  = get_current_user_id();
		$rate_key = 'mediarefinspector_support_' . $user_id;
		if ( get_transient( $rate_key ) ) { $this->redirect_support_result( 'rate_limited' ); }
		$labels = array( 'bug' => __( 'Bug report', 'media-reference-inspector' ), 'feature' => __( 'Feature request', 'media-reference-inspector' ), 'question' => __( 'General question', 'media-reference-inspector' ) );
		$support_email = sanitize_email( apply_filters( 'mediarefinspector_support_email', 'rejoyanislam9009@gmail.com' ) );
		if ( ! is_email( $support_email ) ) { $this->redirect_support_result( 'failed' ); }
		$mail_subject = sprintf( '[Media Reference Inspector] %s: %s', $labels[ $type ], $subject );
		$mail_body = sprintf( "Request type: %s
Reply email: %s
Plugin version: %s
WordPress version: %s

Message:
%s", $labels[ $type ], $email, MEDIAREFINSPECTOR_VERSION, get_bloginfo( 'version' ), $message );
		$sent = wp_mail( $support_email, $mail_subject, $mail_body, array( 'Reply-To: ' . $email ) );
		if ( $sent ) { set_transient( $rate_key, 1, MINUTE_IN_SECONDS ); $this->redirect_support_result( 'sent' ); }
		$this->redirect_support_result( 'failed' );
	}

	/**
	 * Redirects back to Help with a support form status.
	 *
	 * @param string $status Support status key.
	 * @return void
	 */
	private function redirect_support_result( $status ) {
		$url = add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'help', 'support_status' => sanitize_key( $status ) ), admin_url( 'upload.php' ) );
		wp_safe_redirect( $url );
		exit;
	}


	/**
	 * Renders single-scan results when a nonce-protected attachment is requested.
	 *
	 * @return void
	 */
	private function maybe_render_scan_results() {
		if ( ! isset( $_GET['attachment_id'] ) ) {
			return;
		}

		$attachment_id = absint( wp_unslash( $_GET['attachment_id'] ) );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			$this->render_notice( esc_html__( 'The requested media item is invalid.', 'media-reference-inspector' ), 'error' );
			return;
		}

		$nonce = isset( $_GET['mediarefinspector_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mediarefinspector_scan_attachment_' . $attachment_id ) ) {
			$this->render_notice( esc_html__( 'The scan request could not be verified. Please try again from this page.', 'media-reference-inspector' ), 'error' );
			return;
		}

		$usages = $this->scanner->find_usages( $attachment_id );
		$media  = $this->get_attachment_summary( $attachment_id );
		?>
		<div class="mediarefinspector-result-panel">
			<div class="mediarefinspector-result-media">
				<div class="mediarefinspector-result-preview"><?php echo wp_kses_post( $media['preview'] ); ?></div>
				<div>
					<span class="mediarefinspector-eyebrow"><?php esc_html_e( 'Scan result', 'media-reference-inspector' ); ?></span>
					<h3><?php echo esc_html( $media['title'] ); ?></h3>
					<p><code><?php echo esc_html( $media['filename'] ); ?></code></p>
					<p class="description"><?php echo esc_html( $media['meta'] ); ?></p>
				</div>
			</div>
			<div class="mediarefinspector-result-count <?php echo empty( $usages ) ? 'is-warning' : 'is-success'; ?>">
				<strong><?php echo esc_html( (string) count( $usages ) ); ?></strong>
				<span><?php echo esc_html( _n( 'reference found', 'references found', count( $usages ), 'media-reference-inspector' ) ); ?></span>
			</div>
		</div>

		<?php if ( empty( $usages ) ) : ?>
			<div class="notice notice-warning inline mediarefinspector-inline-notice"><p><strong><?php esc_html_e( 'No supported references found.', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'This does not prove that the file is unused. Themes, page builders, custom tables, external systems, or custom code may reference media elsewhere.', 'media-reference-inspector' ); ?></p></div>
		<?php else : ?>
			<?php $this->render_grouped_usages( $usages ); ?>
		<?php endif; ?>

		<?php $this->render_media_impact_preview( $attachment_id, $usages ); ?>
		<?php $this->render_file_health( $attachment_id ); ?>
		<?php $this->render_attachment_parent_note( $attachment_id ); ?>
		<?php
	}

	/**
	 * Renders grouped reference cards.
	 *
	 * @param array<int, array<string, mixed>> $usages Usage records.
	 * @return void
	 */
	private function render_grouped_usages( $usages ) {
		$groups = array();
		foreach ( $usages as $usage ) {
			$type = ! empty( $usage['type'] ) ? (string) $usage['type'] : __( 'Other reference', 'media-reference-inspector' );
			if ( ! isset( $groups[ $type ] ) ) {
				$groups[ $type ] = array();
			}
			$groups[ $type ][] = $usage;
		}
		?>
		<div class="mediarefinspector-reference-groups">
			<?php foreach ( $groups as $type => $items ) : ?>
				<section class="mediarefinspector-reference-group">
					<div class="mediarefinspector-reference-group-heading"><h3><?php echo esc_html( $type ); ?></h3><span><?php echo esc_html( (string) count( $items ) ); ?></span></div>
					<div class="mediarefinspector-reference-list">
						<?php foreach ( $items as $usage ) : ?>
							<article class="mediarefinspector-reference-item">
								<div><strong><?php echo esc_html( $usage['label'] ); ?></strong><span class="mediarefinspector-status-pill"><?php echo esc_html( $usage['status'] ); ?></span></div>
								<?php if ( ! empty( $usage['source'] ) || ! empty( $usage['confidence'] ) ) : ?>
									<p class="mediarefinspector-evidence"><strong><?php echo esc_html( isset( $usage['confidence'] ) ? $usage['confidence'] : __( 'High', 'media-reference-inspector' ) ); ?></strong><?php if ( ! empty( $usage['source'] ) ) : ?> · <?php echo esc_html( $usage['source'] ); ?><?php endif; ?><?php if ( ! empty( $usage['context'] ) && $usage['context'] !== $usage['label'] ) : ?><span><?php echo esc_html( $usage['context'] ); ?></span><?php endif; ?></p>
								<?php endif; ?>
								<div class="mediarefinspector-reference-actions">
									<?php if ( ! empty( $usage['edit_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $usage['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'media-reference-inspector' ); ?></a><?php endif; ?>
									<?php if ( ! empty( $usage['view_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $usage['view_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'media-reference-inspector' ); ?></a><?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Renders the searchable media list.
	 *
	 * @param string $search Search query.
	 * @param string $type   Media type filter.
	 * @param int    $paged  Current page number.
	 * @return void
	 */
	private function render_media_table( $search, $type, $paged ) {
		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}
		if ( '' !== $type ) {
			$query_args['post_mime_type'] = $type;
		}

		$media_query = new WP_Query( $query_args );
		?>
		<div class="mediarefinspector-media-grid">
			<?php if ( $media_query->have_posts() ) : ?>
				<?php while ( $media_query->have_posts() ) : ?>
					<?php
					$media_query->the_post();
					$attachment_id = get_the_ID();
					$media         = $this->get_attachment_summary( $attachment_id );
					$scan_url      = $this->get_scan_url( $attachment_id, $search, $paged, $type );
					?>
					<article class="mediarefinspector-media-card">
						<div class="mediarefinspector-media-preview"><?php echo wp_kses_post( $media['preview'] ); ?></div>
						<div class="mediarefinspector-media-copy">
							<h3><?php echo esc_html( $media['title'] ); ?></h3>
							<code><?php echo esc_html( $media['filename'] ); ?></code>
							<p><?php echo esc_html( $media['meta'] ); ?></p>
							<?php $cached_status = get_transient( 'mediarefinspector_scan_status_' . absint( $attachment_id ) ); ?>
							<?php if ( is_array( $cached_status ) && ! empty( $cached_status['status'] ) ) : ?>
								<?php
								if ( 'referenced' === $cached_status['status'] ) {
									$recent_count = isset( $cached_status['count'] ) ? absint( $cached_status['count'] ) : 0;
									/* translators: %d: Number of references in the recent cached scan. */
									$recent_status = sprintf( __( 'Recent scan: %d references', 'media-reference-inspector' ), $recent_count );
								} else {
									$recent_status = __( 'Recent scan: no supported references', 'media-reference-inspector' );
								}
								?>
								<p class="mediarefinspector-cached-status"><span class="dashicons dashicons-clock" aria-hidden="true"></span><?php echo esc_html( $recent_status ); ?></p>
							<?php else : ?><p class="mediarefinspector-cached-status"><?php esc_html_e( 'Not scanned yet', 'media-reference-inspector' ); ?></p><?php endif; ?>
						</div>
						<div class="mediarefinspector-media-action"><a class="button button-primary" href="<?php echo esc_url( $scan_url ); ?>"><?php esc_html_e( 'Scan references', 'media-reference-inspector' ); ?></a></div>
					</article>
				<?php endwhile; ?>
			<?php else : ?>
				<div class="mediarefinspector-empty-state mediarefinspector-empty-state-inline"><span class="dashicons dashicons-format-image" aria-hidden="true"></span><h3><?php esc_html_e( 'No media items found', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Try a different search or media type filter.', 'media-reference-inspector' ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
		$this->render_pagination( $media_query, $search, $type, $paged );
	}

	/**
	 * Renders search and type controls.
	 *
	 * @param string $tab    Current tab.
	 * @param string $search Search value.
	 * @param string $type   Type value.
	 * @return void
	 */
	private function render_filter_form( $tab, $search, $type ) {
		?>
		<form class="mediarefinspector-panel mediarefinspector-filter-form" method="get" action="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">
			<input type="hidden" name="page" value="media-reference-inspector" />
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
			<div class="mediarefinspector-field mediarefinspector-field-grow">
				<label for="mediarefinspector-search-input"><?php esc_html_e( 'Search media', 'media-reference-inspector' ); ?></label>
				<input type="search" id="mediarefinspector-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search title or description', 'media-reference-inspector' ); ?>" />
			</div>
			<div class="mediarefinspector-field">
				<label for="mediarefinspector-type-filter"><?php esc_html_e( 'Media type', 'media-reference-inspector' ); ?></label>
				<select id="mediarefinspector-type-filter" name="media_type"><?php $this->render_type_options( $type ); ?></select>
			</div>
			<div class="mediarefinspector-field mediarefinspector-field-action"><button class="button button-secondary" type="submit"><?php esc_html_e( 'Apply filters', 'media-reference-inspector' ); ?></button></div>
		</form>
		<?php
	}

	/**
	 * Renders shared media type options.
	 *
	 * @param string $selected Selected value.
	 * @return void
	 */
	private function render_type_options( $selected ) {
		$options = array(
			''            => __( 'All media', 'media-reference-inspector' ),
			'image'       => __( 'Images', 'media-reference-inspector' ),
			'video'       => __( 'Video', 'media-reference-inspector' ),
			'audio'       => __( 'Audio', 'media-reference-inspector' ),
			'application' => __( 'Documents / files', 'media-reference-inspector' ),
		);

		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
	}

	/**
	 * AJAX: returns attachment IDs matching bulk filters.
	 *
	 * @return void
	 */
	public function ajax_get_bulk_ids() {
		check_ajax_referer( 'mediarefinspector_bulk_scan', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to run this scan.', 'media-reference-inspector' ) ), 403 );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$type   = isset( $_POST['media_type'] ) ? sanitize_key( wp_unslash( $_POST['media_type'] ) ) : '';
		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 100;
		$limit  = min( 250, max( 1, $limit ) );
		$age    = isset( $_POST['age'] ) ? absint( $_POST['age'] ) : 0;
		if ( ! in_array( $age, array( 0, 30, 90, 365 ), true ) ) {
			$age = 0;
		}

		if ( ! in_array( $type, array( '', 'image', 'video', 'audio', 'application' ), true ) ) {
			$type = '';
		}
		$selected_raw = isset( $_POST['selected_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_ids'] ) ) : '';
		if ( '' !== $selected_raw ) {
			$selected = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[^0-9]+/', $selected_raw ) ) ) ) );
			$selected = array_slice( $selected, 0, $limit );
			$valid = array();
			foreach ( $selected as $candidate_id ) {
				if ( 'attachment' === get_post_type( $candidate_id ) ) { $valid[] = $candidate_id; }
			}
			wp_send_json_success( array( 'ids' => $valid ) );
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( '' !== $type ) {
			$args['post_mime_type'] = $type;
		}
		if ( $age ) {
			$args['date_query'] = array(
				array(
					'after'     => $age . ' days ago',
					'inclusive' => true,
				),
			);
		}

		$query = new WP_Query( $args );
		wp_send_json_success( array( 'ids' => array_map( 'absint', $query->posts ) ) );
	}

	/**
	 * AJAX: scans one attachment and returns a normalized bulk result.
	 *
	 * @return void
	 */
	public function ajax_bulk_scan_item() {
		check_ajax_referer( 'mediarefinspector_bulk_scan', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to run this scan.', 'media-reference-inspector' ) ), 403 );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid media item.', 'media-reference-inspector' ) ), 400 );
		}

		$usages = $this->scanner->find_usages( $attachment_id );
		$media  = $this->get_attachment_summary( $attachment_id );
		$types      = array();
		$sources    = array();
		$confidence = array();
		foreach ( $usages as $usage ) {
			if ( ! empty( $usage['type'] ) ) { $types[] = (string) $usage['type']; }
			if ( ! empty( $usage['source_category'] ) ) { $sources[] = sanitize_key( $usage['source_category'] ); }
			if ( ! empty( $usage['confidence'] ) ) { $confidence[] = (string) $usage['confidence']; }
		}
		$health = ( new MediaRefInspector_Audit_Service() )->get_file_health( $attachment_id );

		$edit_attachment = get_edit_post_link( $attachment_id, 'raw' );

		wp_send_json_success(
			array(
				'id'             => $attachment_id,
				'title'          => $media['title'],
				'filename'       => $media['filename'],
				'mimeType'       => $media['mime_type'],
				'url'            => $media['url'],
				'fileSize'       => $media['file_size'],
				'uploadedDate'   => $media['date'],
				'referenceCount' => count( $usages ),
				'referenceTypes'   => array_values( array_unique( $types ) ),
				'sourceCategories' => array_values( array_unique( $sources ) ),
				'confidence'       => array_values( array_unique( $confidence ) ),
				'healthStatus'     => isset( $health['status'] ) ? sanitize_key( $health['status'] ) : 'review',
				'status'           => empty( $usages ) ? 'unreferenced' : 'referenced',
				'inspectUrl'     => $this->get_scan_url( $attachment_id, '', 1 ),
				'editAttachment' => $edit_attachment ? $edit_attachment : '',
			)
		);
	}


	/**
	 * Builds summary data for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, string>
	 */
	private function get_attachment_summary( $attachment_id ) {
		$title     = get_the_title( $attachment_id );
		$file      = get_attached_file( $attachment_id );
		$mime_type = (string) get_post_mime_type( $attachment_id );
		if ( $file ) {
			$filename = wp_basename( $file );
		} else {
			/* translators: %d: Media attachment ID. */
			$filename = sprintf( __( 'Media item #%d', 'media-reference-inspector' ), $attachment_id );
		}

		if ( '' === $title ) {
			$title = $filename;
		}

		$preview = 'image/svg+xml' === $mime_type ? '' : wp_get_attachment_image( $attachment_id, array( 96, 96 ), true, array( 'loading' => 'lazy' ) );
		if ( ! $preview ) {
			$extension = $file ? strtoupper( (string) pathinfo( $file, PATHINFO_EXTENSION ) ) : __( 'FILE', 'media-reference-inspector' );
			$preview   = '<span class="mediarefinspector-file-preview" aria-hidden="true"><span class="dashicons dashicons-media-default"></span><span class="mediarefinspector-file-extension">' . esc_html( $extension ) . '</span></span>';
		}

		$meta_parts = array();
		if ( $mime_type ) {
			$meta_parts[] = $mime_type;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
			$meta_parts[] = absint( $metadata['width'] ) . '×' . absint( $metadata['height'] );
		}

		$file_size = 0;
		if ( $file && file_exists( $file ) ) {
			$raw_size = filesize( $file );
			if ( false !== $raw_size ) {
				$file_size    = (int) $raw_size;
				$meta_parts[] = size_format( $file_size, 1 );
			}
		}

		$date = get_the_date( '', $attachment_id );
		if ( $date ) {
			$meta_parts[] = $date;
		}

		return array(
			'title'     => $title,
			'filename'  => $filename,
			'mime_type' => $mime_type,
			'preview'   => $preview,
			'meta'      => implode( ' · ', $meta_parts ),
			'url'       => (string) wp_get_attachment_url( $attachment_id ),
			'file_size' => $file_size,
			'date'      => (string) $date,
		);
	}

	/**
	 * Renders pagination while retaining filters.
	 *
	 * @param WP_Query $query  Media query.
	 * @param string   $search Search value.
	 * @param string   $type   Type value.
	 * @param int      $paged  Current page.
	 * @return void
	 */
	private function render_pagination( $query, $search, $type, $paged, $tab = 'scanner' ) {
		if ( $query->max_num_pages <= 1 ) {
			return;
		}

		$base = add_query_arg(
			array(
				'page'  => 'media-reference-inspector',
				'tab'   => $tab,
				'paged' => 999999999,
			),
			admin_url( 'upload.php' )
		);
		if ( '' !== $search ) {
			$base = add_query_arg( 's', $search, $base );
		}
		if ( '' !== $type ) {
			$base = add_query_arg( 'media_type', $type, $base );
		}
		$base = str_replace( '999999999', '%#%', $base );

		$links = paginate_links(
			array(
				'base'      => $base,
				'current'   => $paged,
				'total'     => (int) $query->max_num_pages,
				'end_size'  => 1,
				'mid_size'  => 1,
				'prev_text' => __( 'Previous', 'media-reference-inspector' ),
				'next_text' => __( 'Next', 'media-reference-inspector' ),
			)
		);

		if ( $links ) {
			?><nav class="mediarefinspector-pagination" aria-label="<?php echo esc_attr__( 'Media pagination', 'media-reference-inspector' ); ?>"><?php echo wp_kses_post( $links ); ?></nav><?php
		}
	}

	/**
	 * Renders an attachment-parent note.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function render_attachment_parent_note( $attachment_id ) {
		$parent_id = wp_get_post_parent_id( $attachment_id );
		if ( ! $parent_id ) {
			return;
		}
		$title = get_the_title( $parent_id );
		if ( '' === $title ) {
			/* translators: %d: Parent post ID. */
			$title = sprintf( __( 'Post #%d', 'media-reference-inspector' ), $parent_id );
		}
		/* translators: %s: Parent post title. */
		$relationship_note = sprintf( __( 'Attachment relationship: uploaded to %s. This relationship alone does not prove the media item is displayed there.', 'media-reference-inspector' ), $title );
		?><p class="description mediarefinspector-safety-note"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><?php echo esc_html( $relationship_note ); ?></p><?php
	}

	/**
	 * Renders a standard inline notice.
	 *
	 * @param string $message Notice text.
	 * @param string $type    Notice type.
	 * @return void
	 */
	private function render_notice( $message, $type = 'info' ) {
		if ( ! in_array( $type, array( 'info', 'warning', 'error', 'success' ), true ) ) {
			$type = 'info';
		}
		?><div class="notice notice-<?php echo esc_attr( $type ); ?> inline"><p><?php echo esc_html( $message ); ?></p></div><?php
	}

	/**
	 * Builds a nonce-protected single scan URL.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $search        Search filter.
	 * @param int    $paged         Page number.
	 * @param string $type          Type filter.
	 * @return string
	 */
	private function get_scan_url( $attachment_id, $search, $paged, $type = '' ) {
		$args = array(
			'page'          => 'media-reference-inspector',
			'tab'           => 'scanner',
			'attachment_id' => absint( $attachment_id ),
			'paged'         => max( 1, absint( $paged ) ),
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( '' !== $type ) {
			$args['media_type'] = $type;
		}
		$url = add_query_arg( $args, admin_url( 'upload.php' ) );
		return wp_nonce_url( $url, 'mediarefinspector_scan_attachment_' . absint( $attachment_id ), 'mediarefinspector_nonce' );
	}


	/**
	 * Draws a simple, evidence-based reference footprint after an explicit scan.
	 * Each link in a footprint leads to a confirmed supported usage; no edits occur.
	 *
	 * @param int                              $attachment_id Attachment ID.
	 * @param array<int, array<string, mixed>> $usages        Scan usages.
	 * @return void
	 */
	private function render_media_impact_preview( $attachment_id, $usages ) {
		$labels = array(
			'core-id'     => __( 'Exact ID / blocks', 'media-reference-inspector' ),
			'core-url'    => __( 'URLs / content', 'media-reference-inspector' ),
			'integration' => __( 'Integrations / builders', 'media-reference-inspector' ),
			'metadata'    => __( 'Metadata / settings', 'media-reference-inspector' ),
			'widget'      => __( 'Widgets', 'media-reference-inspector' ),
			'setting'     => __( 'WordPress settings', 'media-reference-inspector' ),
			'other'       => __( 'Other supported evidence', 'media-reference-inspector' ),
		);
		$groups = array();
		foreach ( is_array( $usages ) ? $usages : array() as $usage ) {
			$key = ! empty( $usage['source_category'] ) ? sanitize_key( $usage['source_category'] ) : 'other';
			if ( ! isset( $labels[ $key ] ) ) {
				$key = 'other';
			}
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array( 'count' => 0, 'samples' => array() );
			}
			$groups[ $key ]['count']++;
			if ( count( $groups[ $key ]['samples'] ) < 2 && ! empty( $usage['label'] ) ) {
				$label = (string) $usage['label'];
				if ( ! in_array( $label, $groups[ $key ]['samples'], true ) ) {
					$groups[ $key ]['samples'][] = $label;
				}
			}
		}
		$total = count( $usages );
		?>
		<div class="mediarefinspector-panel mediarefinspector-impact-preview mri26-dependencies">
			<div class="mri26-dependency-head"><div><span class="mri26-section-kicker"><?php esc_html_e( 'AFTER SCAN', 'media-reference-inspector' ); ?></span><h3><?php esc_html_e( 'Reference footprint', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Supported places linked to this attachment, grouped by reference source. This is not a complete dependency graph.', 'media-reference-inspector' ); ?></p></div><span class="mri26-dependency-pill"><?php echo esc_html( sprintf( /* translators: %d: Count of supported references. */ _n( '%d reference', '%d references', $total, 'media-reference-inspector' ), $total ) ); ?></span></div>
			<?php if ( empty( $groups ) ) : ?><p class="description"><?php esc_html_e( 'No supported references were found. This is not proof that the file is safe to delete.', 'media-reference-inspector' ); ?></p>
			<?php else : ?><div class="mri26-dependency-map">
				<?php foreach ( $groups as $key => $group ) : ?>
					<div class="mri26-dependency-source"><div><strong><?php echo esc_html( $labels[ $key ] ); ?></strong><?php if ( $group['samples'] ) : ?><p><?php echo esc_html( implode( ' · ', $group['samples'] ) ); ?></p><?php endif; ?></div><div class="mri26-dependency-meter" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: source name, 2: number of references. */ __( '%1$s: %2$d references', 'media-reference-inspector' ), $labels[ $key ], $group['count'] ) ); ?>"><span style="width:<?php echo esc_attr( round( 100 * $group['count'] / max( 1, $total ), 1 ) ); ?>%"></span></div><span><?php echo esc_html( (string) $group['count'] ); ?></span></div>
				<?php endforeach; ?>
			</div><?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the explicit bounded site-audit summary.
	 *
	 * @return void
	 */
	private function render_site_audit_tab() {
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-site-audit-heading">
			<div class="mediarefinspector-section-heading"><div><h2 id="mediarefinspector-site-audit-heading"><?php esc_html_e( 'Progressive Site Audit', 'media-reference-inspector' ); ?></h2><p><?php esc_html_e( 'Examine a recent group of attachments one at a time, with progress, pause, and resume. This audit is advisory and read-only.', 'media-reference-inspector' ); ?></p></div></div>
			<div class="mediarefinspector-panel mediarefinspector-audit-controls" id="mediarefinspector-site-audit-app">
				<label for="mediarefinspector-site-audit-limit"><?php esc_html_e( 'Recent media to inspect', 'media-reference-inspector' ); ?></label>
				<select id="mediarefinspector-site-audit-limit"><option value="25">25</option><option value="50" selected>50</option><option value="100">100</option></select>
				<button type="button" class="button button-primary" id="mediarefinspector-site-audit-start"><?php esc_html_e( 'Run site audit', 'media-reference-inspector' ); ?></button>
				<button type="button" class="button" id="mediarefinspector-site-audit-pause" hidden><?php esc_html_e( 'Pause', 'media-reference-inspector' ); ?></button>
				<button type="button" class="button" id="mediarefinspector-site-audit-resume" hidden><?php esc_html_e( 'Resume', 'media-reference-inspector' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Each attachment is checked through a separate verified request to help avoid long timeouts. An interrupted audit can be resumed while this page stays open. The results are not stored as a full audit history.', 'media-reference-inspector' ); ?></p>
			<div class="mediarefinspector-progress" id="mediarefinspector-site-audit-progress" hidden aria-live="polite"><div class="mediarefinspector-progress-header"><strong id="mediarefinspector-site-audit-status"><?php esc_html_e( 'Ready', 'media-reference-inspector' ); ?></strong><span id="mediarefinspector-site-audit-count">0 / 0</span></div><progress id="mediarefinspector-site-audit-bar" value="0" max="100">0%</progress></div>
			<div class="mediarefinspector-summary-grid mediarefinspector-site-audit-summary" id="mediarefinspector-site-audit-summary" hidden>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Scanned', 'media-reference-inspector' ); ?></span><strong data-site-audit="scanned">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></span><strong data-site-audit="referenced">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Potential unused review', 'media-reference-inspector' ); ?></span><strong data-site-audit="unreferenced">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'File health review', 'media-reference-inspector' ); ?></span><strong data-site-audit="review">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Errors', 'media-reference-inspector' ); ?></span><strong data-site-audit="errors">0</strong></div>
			</div>
			<div class="mediarefinspector-table-wrap" id="mediarefinspector-site-audit-table" hidden><table class="widefat striped mediarefinspector-table"><thead><tr><th><?php esc_html_e( 'Media', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'References', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'File health', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'Inspect', 'media-reference-inspector' ); ?></th></tr></thead><tbody id="mediarefinspector-site-audit-rows"></tbody></table></div>
			<p class="mediarefinspector-safety-note"><?php esc_html_e( 'For separate file duplicate checks and local broken URL checks, use the Duplicates and Broken URLs tabs. They are not counted in this progressive media audit.', 'media-reference-inspector' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Gets the active tab.
	 *
	 * @return string
	 */
	private function get_current_tab() {
		$tab = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
		$tab = is_string( $tab ) ? sanitize_key( $tab ) : 'dashboard';
		return in_array( $tab, array( 'dashboard', 'explorer', 'scanner', 'bulk', 'audit', 'duplicates', 'broken', 'site-audit', 'help' ), true ) ? $tab : 'dashboard';
	}

	/**
	 * Gets the current search filter.
	 *
	 * @return string
	 */
	private function get_search_value() {
		$value = filter_input( INPUT_GET, 's', FILTER_UNSAFE_RAW );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Gets the current media type filter.
	 *
	 * @return string
	 */
	private function get_type_filter() {
		$value = filter_input( INPUT_GET, 'media_type', FILTER_UNSAFE_RAW );
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		return in_array( $value, array( '', 'image', 'video', 'audio', 'application' ), true ) ? $value : '';
	}

	/**
	 * Gets the current page number.
	 *
	 * @return int
	 */
	private function get_page_number() {
		$value = filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT );
		return false !== $value && null !== $value ? max( 1, absint( $value ) ) : 1;
	}
}
