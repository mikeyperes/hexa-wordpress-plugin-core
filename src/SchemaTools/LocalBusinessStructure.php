<?php
/** Exposes Local Business as an optional shared Core schema structure. */

namespace Hexa\PluginCore\SchemaTools;

/** Builds mapped Local Business records through SchemaStructures and SchemaPage. */
final class LocalBusinessStructure {
    public const ID = 'local-business';

    /**
     * Builds a default-off structure with host-owned page assignments.
     *
     * @param callable(int):list<array<string,mixed>> $records_for_page Returns business records assigned to a WordPress page ID.
     * @param callable():?SchemaContext|null $sample Returns a mapped page for previews.
     */
    public static function definition( callable $records_for_page, ?callable $sample = null ): array {
        return [
            'id' => self::ID,
            'label' => 'Local Business',
            'where' => 'WordPress pages mapped to saved business records.',
            'types' => 'LocalBusiness and its subtypes',
            'default' => SchemaStructures::OFF,
            'applies' => [ self::class, 'applies' ],
            'build' => static fn( SchemaContext $context ): array => self::build( $records_for_page( $context->post_id() ) ),
            'sample' => $sample,
        ];
    }

    /** Protects private, preview, and password-protected content from unintended output. */
    public static function applies( SchemaContext $context ): bool {
        $post = $context->post();
        return null !== $post
            && 'page' === $post->post_type
            && ! is_preview()
            && is_post_publicly_viewable( $post )
            && ! post_password_required( $post );
    }

    /** Converts mapped host records to cleaned Core schema nodes. */
    private static function build( array $records ): array {
        $nodes = [];
        foreach ( $records as $record ) {
            $node = SchemaNodes::local_business( $record, (string) ( $record['type'] ?? 'LocalBusiness' ) );
            if ( [] !== $node ) {
                $nodes[] = $node;
            }
        }
        return $nodes;
    }
}
