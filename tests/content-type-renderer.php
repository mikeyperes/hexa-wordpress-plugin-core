<?php

declare(strict_types=1);

$content_type_renderer_options = [];
$content_type_renderer_registered = [ 'press-release' => true ];

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
        ],
    ]
);

$html = ( new ContentTypeRenderer() )->render( $registry );
$renderer_source = (string) file_get_contents( $root . '/src/ContentTypes/ContentTypeRenderer.php' );

content_type_renderer_assert( str_contains( $html, '<article class="hpc-content-type-card">' ), 'Each content type should render as one plain card.' );
content_type_renderer_assert( str_contains( $html, 'Labels and URL' ), 'Editable labels and URL should remain visible.' );
content_type_renderer_assert( str_contains( $html, '<summary>Technical details</summary>' ), 'Secondary metadata should be hidden behind technical details.' );
content_type_renderer_assert( str_contains( $html, '<summary>View fields</summary>' ), 'Field details should use a short disclosure label.' );
content_type_renderer_assert( str_contains( $html, '>Save changes</button>' ), 'The card should use a plain save label.' );
content_type_renderer_assert( ! str_contains( $renderer_source, 'hpc-content-type-grid' ), 'The content-type renderer must not restore the split dashboard grid.' );
content_type_renderer_assert( ! str_contains( $renderer_source, 'hpc-content-type-facts' ), 'Technical facts must not render as a fact-card grid.' );
content_type_renderer_assert( ! str_contains( $renderer_source, 'grid-template-columns' ), 'Content-type settings must remain single-column.' );

$enable_position = strpos( $html, 'Enable Press Releases' );
$labels_position = strpos( $html, 'Labels and URL' );
$fields_position = strpos( $html, 'Custom fields' );
$technical_position = strpos( $html, 'Technical details' );
$save_position = strpos( $html, 'Save changes' );

content_type_renderer_assert(
    false !== $enable_position
    && false !== $labels_position
    && false !== $fields_position
    && false !== $technical_position
    && false !== $save_position
    && $enable_position < $labels_position
    && $labels_position < $fields_position
    && $fields_position < $technical_position
    && $technical_position < $save_position,
    'The settings should follow a clear top-to-bottom editing order.'
);

echo "PASS: Content type settings render as a clear single-column flow with secondary technical details.\n";
