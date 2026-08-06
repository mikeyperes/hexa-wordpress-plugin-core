<?php

declare(strict_types=1);

$content_type_renderer_options = [];
$content_type_renderer_registered = [ 'press-release' => true ];
$content_type_renderer_acf_registered = [ 'group_press_release' => true ];

function sanitize_key( mixed $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: '';
}
function sanitize_title( mixed $value ): string {
    return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ) ?: '', '-' );
}
function sanitize_text_field( mixed $value ): string {
    return trim( strip_tags( (string) $value ) );
}
function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ) ?: '';
}
function get_option( string $name, mixed $default = false ): mixed {
    global $content_type_renderer_options;
    return $content_type_renderer_options[ $name ] ?? $default;
}
function post_type_exists( string $key ): bool {
    global $content_type_renderer_registered;
    return ! empty( $content_type_renderer_registered[ $key ] );
}
function acf_get_local_field_group( string $key ): array|false {
    global $content_type_renderer_acf_registered;
    return ! empty( $content_type_renderer_acf_registered[ $key ] ) ? [ 'key' => $key ] : false;
}
function admin_url( string $path = '' ): string {
    return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}
function wp_create_nonce( string $action ): string {
    return 'test-nonce-' . $action;
}
function esc_url( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function wp_kses_post( mixed $value ): string {
    return (string) $value;
}

$root = dirname( __DIR__ );
require $root . '/src/CoreContracts/ModuleInterface.php';
require $root . '/src/WpAdminComponents/CoreUi.php';
require $root . '/src/ContentTypes/ContentTypeDefinition.php';
require $root . '/src/ContentTypes/ContentTypeSettingsStore.php';
require $root . '/src/ContentTypes/ContentTypeRegistry.php';
require $root . '/src/ContentTypes/ContentTypeRenderer.php';

use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;
use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;

function content_type_renderer_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$registry = new ContentTypeRegistry(
    [
        'option_name'  => 'test_content_type_renderer',
        'ajax_action'  => 'test_save_content_type',
        'nonce_action' => 'test_content_types',
    ]
);
$registry->add(
    [
        'id'          => 'press-release',
        'owner'       => 'Test host',
        'description' => 'Published press releases.',
        'post_type'   => [
            'key'          => 'press-release',
            'singular'     => 'Press Release',
            'plural'       => 'Press Releases',
            'rewrite_slug' => 'press-release',
            'args'         => [
                'has_archive' => false,
                'supports'    => [ 'title', 'editor', 'thumbnail' ],
            ],
        ],
        'taxonomies'  => [ 'topic' ],
        'field_groups' => [
            [
                'id'          => 'press-release-fields',
                'label'       => 'Press Release Fields',
                'description' => 'Press release details.',
                'group_key'   => 'group_press_release',
                'fields'      => [ 'Source URL', 'Publication date' ],
            ],
            [
                'id'           => 'distribution-fields',
                'label'        => 'Distribution Fields',
                'description'  => 'Distribution metadata.',
                'group_key'    => 'group_distribution',
                'fields'       => [ 'Distribution channel' ],
                'dependencies' => [ 'Advanced Custom Fields Pro' ],
            ],
        ],
    ]
);

$html = ( new ContentTypeRenderer() )->render( $registry );
$renderer_source = (string) file_get_contents( $root . '/src/ContentTypes/ContentTypeRenderer.php' );

content_type_renderer_assert( str_contains( $html, '<details class="hpc-section hpc-content-type-parent">' ), 'Each CPT should render as the parent accordion.' );
content_type_renderer_assert( ! preg_match( '/<details class="hpc-section hpc-content-type-parent"[^>]*\sopen(?:\s|>)/', $html ), 'CPT parent accordions must be collapsed by default.' );
content_type_renderer_assert( str_contains( $html, 'CPT — Press Releases' ), 'The parent heading should explicitly identify the CPT.' );
content_type_renderer_assert( str_contains( $html, 'Parent custom post type' ), 'The CPT configuration should identify itself as the parent.' );
content_type_renderer_assert( str_contains( $html, 'Labels and URL' ), 'Editable labels and URL should remain visible.' );
content_type_renderer_assert( str_contains( $html, '<summary>CPT technical details</summary>' ), 'Secondary CPT metadata should remain inside the parent CPT section.' );
content_type_renderer_assert( str_contains( $html, 'Children of this CPT' ), 'The ACF collection should explicitly identify itself as child configuration.' );
content_type_renderer_assert( str_contains( $html, 'Each section below is one ACF field group attached to' ), 'The CPT-to-ACF relationship should be explained.' );
content_type_renderer_assert( 2 === substr_count( $html, '<details class="hpc-detail-card hpc-content-type-acf-group">' ), 'Every ACF group should render as its own child accordion.' );
content_type_renderer_assert( ! preg_match( '/<details class="hpc-detail-card hpc-content-type-acf-group"[^>]*\sopen(?:\s|>)/', $html ), 'ACF child accordions must be collapsed by default.' );
content_type_renderer_assert( str_contains( $html, 'ACF — Press Release Fields' ) && str_contains( $html, 'ACF — Distribution Fields' ), 'ACF child headings should identify each field group.' );
content_type_renderer_assert( str_contains( $html, 'Enable this ACF field group' ), 'Each ACF child should own its enable control.' );
content_type_renderer_assert( str_contains( $html, 'Group details' ) && str_contains( $html, 'Attached CPT' ), 'Each ACF child should show its group metadata and parent CPT.' );
content_type_renderer_assert( str_contains( $html, 'Imported fields' ) && str_contains( $html, 'Source URL' ), 'Each ACF child should show its field inventory.' );
content_type_renderer_assert( ! str_contains( $html, '<summary>View fields</summary>' ), 'Field inventories should belong directly to their ACF child section.' );
content_type_renderer_assert( str_contains( $html, '>Save CPT and ACF settings</button>' ), 'The parent action should describe the settings it saves.' );
content_type_renderer_assert( ! str_contains( $renderer_source, 'hpc-content-type-grid' ), 'The content-type renderer must not restore the split dashboard grid.' );
content_type_renderer_assert( ! str_contains( $renderer_source, 'hpc-content-type-facts' ), 'Technical facts must not render as a fact-card grid.' );
content_type_renderer_assert( ! str_contains( $renderer_source, 'grid-template-columns' ), 'Content-type settings must remain single-column.' );

$enable_position = strpos( $html, 'Enable Press Releases' );
$labels_position = strpos( $html, 'Labels and URL' );
$technical_position = strpos( $html, 'CPT technical details' );
$acf_collection_position = strpos( $html, '<h4>ACF field groups</h4>' );
$first_acf_position = strpos( $html, 'ACF — Press Release Fields' );
$second_acf_position = strpos( $html, 'ACF — Distribution Fields' );
$save_position = strpos( $html, 'Save CPT and ACF settings' );

content_type_renderer_assert(
    false !== $enable_position
    && false !== $labels_position
    && false !== $technical_position
    && false !== $acf_collection_position
    && false !== $first_acf_position
    && false !== $second_acf_position
    && false !== $save_position
    && $enable_position < $labels_position
    && $labels_position < $technical_position
    && $technical_position < $acf_collection_position
    && $acf_collection_position < $first_acf_position
    && $first_acf_position < $second_acf_position
    && $second_acf_position < $save_position,
    'The parent CPT settings and child ACF groups should follow a clear hierarchy.'
);

echo "PASS: Content types render as collapsed CPT parents with collapsed ACF child sections.\n";
