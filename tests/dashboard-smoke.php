<?php
/** Standalone render regression tests for v2.6 UI (does not require WordPress). */
define( 'ABSPATH', '/tmp/' );
define( 'DAY_IN_SECONDS', 86400 );
function __( $v, $domain = '' ) { return $v; }
function esc_html_e( $v, $d = '' ) { echo esc_html( $v ); }
function esc_attr__( $v, $d = '' ) { return $v; }
function esc_html__( $v, $d = '' ) { return $v; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_attr_e( $v, $d = '' ) { echo esc_attr( $v ); }
function current_user_can( $cap, $id = 0 ) { return true; }
function get_current_user_id() { return 1; }
function selected( $selected, $current, $echo = true ) { $v = $selected === $current ? " selected" : ""; if ( $echo ) echo $v; return $v; }
function esc_url( $v ) { return esc_html( $v ); }
function wp_kses_post( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function number_format_i18n( $v ) { return number_format( $v ); }
function _n( $singular, $plural, $count, $domain = '' ) { return $count === 1 ? $singular : $plural; }
function wp_count_posts( $type ) { return (object) array( 'inherit' => 155 ); }
function get_posts( $args ) { return array_map( function ( $i ) { return new WP_Post( $i ); }, range( 1, 8 ) ); }
function get_transient( $key ) { if ( strpos($key, 'mediarefinspector_cleanup_') === 0 ) return false; if ( strpos( $key, '_1' ) !== false ) return array( 'status' => 'referenced', 'count' => 2 ); if ( strpos( $key, '_2' ) !== false ) return array( 'status' => 'unreferenced', 'count' => 0 ); return false; }
function get_the_title( $id ) { return $id === 3 ? '<Invalid & title>' : 'Example media ' . $id; }
function get_post_mime_type( $id ) { return $id % 3 === 0 ? 'image/svg+xml' : 'image/png'; }
function get_edit_post_link( $id, $context = '' ) { return 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit'; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_url( $url, $key, $name ) { return $url . '&' . $name . '=mock_nonce'; }
function wp_get_attachment_image( $id, $size, $icon, $args ) { return '<img alt="" width="96" height="96" src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect width=%22100%22 height=%22100%22 rx=%2212%22 fill=%22%23bbc7f1%22/%3E%3C/svg%3E" />'; }
function wp_reset_postdata() { }
function paginate_links( $args ) { return '<a href="' . esc_attr( str_replace( '%#%', '2', $args['base'] ) ) . '">2</a>'; }
class WP_Post { public $ID; public $post_type = 'attachment'; public $post_status = 'inherit'; public function __construct( $id ) { $this->ID = $id; } }
class WP_Query {
    public $found_posts = 155;
    public $max_num_pages = 9;
    private $index = 0;
    public function __construct( $args ) { }
    public function have_posts() { return $this->index < 8; }
    public function the_post() { $GLOBALS['test_current_post'] = new WP_Post( ++$this->index ); }
}
function get_post() { return $GLOBALS['test_current_post']; }
class FakeDB {
    public $posts = 'wp_posts';
    public function prepare( $query, ...$args ) { return $query; }
    public function get_results( $query ) { return array( (object) array('family'=>'image','quantity'=>154), (object) array('family'=>'other','quantity'=>1) ); }
    public function get_var( $query ) { return 4; }
}
$wpdb = new FakeDB();
require dirname( __DIR__ ) . '/includes/class-mediarefinspector-plugin.php';
$plugin = ( new ReflectionClass( 'MediaRefInspector_Plugin' ) )->newInstanceWithoutConstructor();
function render_ui( $plugin, $method, $args = array() ) {
    $ref = new ReflectionMethod( $plugin, $method );
    $ref->setAccessible( true );
    ob_start();
    $ref->invokeArgs( $plugin, $args );
    return ob_get_clean();
}
function must( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( 'FAILED: ' . $message ); }
$dashboard = render_ui( $plugin, 'render_dashboard_tab' );
$explorer = render_ui( $plugin, 'render_explorer_tab' );
$dependency = render_ui( $plugin, 'render_media_impact_preview', array( 2, array( array( 'source_category' => 'core-id', 'label' => 'Homepage' ), array( 'source_category' => 'core-id', 'label' => 'Contact' ), array( 'source_category' => 'widget', 'label' => 'Footer' ) ) ) );
$checks = array(
    array( substr_count( $dashboard, 'class="mri26-media-tile"' ) === 8, 'dashboard renders exactly 8 media tiles' ),
    array( substr_count( $explorer, 'mri27-selectable-tile' ) === 8, 'explorer renders records and does not scan automatically' ),
    array( strpos( $dashboard, 'data-mri26-filter="review"' ) !== false, 'dashboard filters present' ),
    array( strpos( $dashboard, '154' ) !== false, 'real media family count rendered' ),
    array( strpos( $dashboard, 'Reference footprint' ) === false, 'footprint is scan only' ),
    array( strpos( $explorer, 'tab=explorer' ) !== false, 'explorer pagination uses correct tab' ),
    array( strpos( $dashboard, '&lt;Invalid &amp; title&gt;' ) !== false, 'media title HTML escaped' ),
    array( strpos( $dependency, 'Homepage' ) !== false && strpos( $dependency, 'Footer' ) !== false, 'footprint groups evidence' ),
    array( strpos( $dependency, '66.7%' ) !== false, 'footprint derives relative bar width' ),
    array( strpos( $dashboard, '8 recent' ) === false, 'no fabricated overall scan count'
    ),
    array( substr_count($explorer, 'mri27-item-check') === 8, 'eight explorer checkboxes' ),
    array( substr_count($explorer, 'data-mri27-single=') === 8, 'eight single delete controls' ),
    array( strpos($explorer, 'mri27-dialog-backdrop') !== false, 'accessible delete review dialog present' ),
    array( strpos($explorer, 'mri27-select-all') !== false && strpos($explorer, 'mri27-review-selection') !== false, 'page selection toolbar present' ),
    array( strpos($dashboard, 'mri27-item-check') === false, 'no deletion controls in dashboard' ),
);
foreach ( $checks as $check ) { must( $check[0], $check[1] ); }
echo 'PASS: ' . count( $checks ) . " v2.7 dashboard/explorer checks\n";
