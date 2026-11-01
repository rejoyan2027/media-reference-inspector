<?php
/** Small regression tests for methods that can run without a WordPress instance. */
define( 'ABSPATH', '/tmp/' );
function absint( $x ) { return abs( (int) $x ); }
function sanitize_key( $x ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $x ) ); }
function __( $x, $domain = '' ) { return $x; }
function maybe_unserialize( $v ) { return $v; }
function get_post_type_object( $type ) { return (object) array( 'labels' => (object) array( 'singular_name' => 'Post' ) ); }
function get_post_status_object( $status ) { return (object) array( 'label' => 'Published' ); }
function get_post( $id ) { return (object) array( 'ID' => $id ); }
function is_post_publicly_viewable( $post ) { return false; }
function get_edit_post_link( $id, $context = '' ) { return 'https://example.test/edit/' . $id; }
function get_post_type( $id ) { return 'attachment'; }
class FakeDB {
    public $posts = 'wp_posts';
    public $rows = array();
    public function esc_like( $x ) { return $x; }
    public function prepare( $query, ...$args ) { return $query; }
    public function get_results( $query ) { return $this->rows; }
}
$wpdb = new FakeDB();
$root = dirname( __DIR__ ) . '/includes/';
if ( ! is_dir( $root ) ) { $root = dirname( __DIR__ ) . '/media-reference-inspector/includes/'; }
foreach ( array( 'scanner', 'enhanced-scanner', 'integration-scanner', 'advanced-scanner', 'insights-scanner', 'extended-scanner', 'audit-service' ) as $part ) {
    require_once $root . 'class-mediarefinspector-' . $part . '.php';
}
$asserts = 0;
function check( $condition, $message ) {
    global $asserts;
    $asserts++;
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
}
function private_call( $obj, $name, ...$args ) {
    $r = new ReflectionMethod( $obj, $name );
    $r->setAccessible( true );
    return $r->invokeArgs( $obj, $args );
}
$scanner = new MediaRefInspector_Scanner();
$url = 'https://example.test/uploads/photo.jpg';
check( private_call( $scanner, 'has_exact_media_url', '<img src="' . $url . '">', $url ), 'full URL' );
check( private_call( $scanner, 'has_exact_media_url', $url . '?ver=2', $url ), 'query string' );
check( ! private_call( $scanner, 'has_exact_media_url', $url . '.backup', $url ), 'reject prefix-suffix URL' );
check( ! private_call( $scanner, 'has_exact_media_url', $url . '2', $url ), 'reject adjacent digit' );
$wpdb->rows = array(
    (object) array( 'ID'=>1, 'post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'Wrong ID', 'post_content'=>'wp-image-123', 'post_excerpt'=>'' ),
    (object) array( 'ID'=>2, 'post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'Right ID', 'post_content'=>'wp-image-12', 'post_excerpt'=>'' ),
    (object) array( 'ID'=>3, 'post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'Wrong URL', 'post_content'=>$url.'.backup', 'post_excerpt'=>'' ),
    (object) array( 'ID'=>4, 'post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'Right URL', 'post_content'=>'<img src="'.$url.'">', 'post_excerpt'=>'' ),
);
$rows = private_call( $scanner, 'find_content_usages', 12, $url );
check( count( $rows ) === 2, 'content candidates filtered to exactly 2' );
check( $rows[0]['label'] === 'Post: Right ID', 'image ID matches proper row' );
$auditor = new MediaRefInspector_Audit_Service();
$refs = array();
$blocks = array(
    array('blockName'=>'core/paragraph', 'attrs'=>array('id'=>12)),
    array('blockName'=>'core/image', 'attrs'=>array('id'=>42)),
    array('blockName'=>'core/gallery', 'attrs'=>array('ids'=>array(4,5))),
    array('blockName'=>'core/group', 'attrs'=>array(), 'innerBlocks'=>array(array('blockName'=>'core/media-text','attrs'=>array('mediaId'=>9)))),
);
$method = new ReflectionMethod( $auditor, 'collect_block_ids' );
$method->setAccessible( true );
$method->invokeArgs( $auditor, array( $blocks, &$refs ) );
check( ! isset( $refs[12] ), 'unrelated block id ignored' );
check( isset( $refs[42] ) && isset( $refs[4] ) && isset( $refs[5] ), 'known media IDs included' );
check( isset( $refs[9] ), 'nested media-text included' );
$extended = new MediaRefInspector_Extended_Scanner();
check( private_call( $extended, 'value_references_media', '12,34,56', 34, '', 'gallery_image' ), 'id CSV list' );
check( private_call( $extended, 'value_references_media', '12 | 34', 34, '', 'gallery_ids' ), 'pipe id list' );
check( ! private_call( $extended, 'value_references_media', '1920x1080', 1920, '', 'image_size' ), 'dimensions not IDs' );
check( ! private_call( $extended, 'value_references_media', '2026-11-01', 11, '', 'media_date' ), 'date not ID' );
check( ! private_call( $extended, 'value_references_media', 'photo.jpg.backup', 34, 'photo.jpg', 'image_url' ), 'metadata url boundary' );
echo "PASS: $asserts targeted checks\n";
