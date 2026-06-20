<?php

namespace Hexa\PluginCore\SiteStructure;

final class PageStructureManager {
    /**
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct( array $config ) {
        $this->config = array_merge(
            [
                'pages'                => [],
                'menu_structures'      => [],
                'option_prefix'        => '',
                'managed_meta_key'     => '_hexa_managed_page',
                'managed_key_meta_key' => '_hexa_page_key',
                'logger'               => null,
                'menu_guess_terms'     => [
                    'header'     => [ 'header', 'main', 'primary', 'top' ],
                    'footer'     => [ 'footer', 'bottom' ],
                    'sub_footer' => [ 'sub-footer', 'sub footer', 'subfooter', 'legal', 'secondary' ],
                ],
            ],
            $config
        );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function pages(): array {
        return is_array( $this->config['pages'] ) ? $this->config['pages'] : [];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function flat_pages(): array {
        $flat = [];
        $this->flatten_pages( $this->pages(), $flat );

        return $flat;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function menu_structures(): array {
        return is_array( $this->config['menu_structures'] ) ? $this->config['menu_structures'] : [];
    }

    public function option_key( string $page_key ): string {
        return (string) $this->config['option_prefix'] . $page_key;
    }

    public function assigned_page_id( string $page_key ): int {
        return function_exists( 'get_option' ) ? (int) get_option( $this->option_key( $page_key ), 0 ) : 0;
    }

    public function assigned_page( string $page_key ): ?\WP_Post {
        $page_id = $this->assigned_page_id( $page_key );
        if ( $page_id <= 0 || ! function_exists( 'get_post' ) ) {
            return null;
        }

        $page = get_post( $page_id );

        return $page instanceof \WP_Post ? $page : null;
    }

    public function is_assigned_to_published_page( string $page_key ): bool {
        $page = $this->assigned_page( $page_key );

        return $page instanceof \WP_Post && 'publish' === $page->post_status;
    }

    /**
     * @return \WP_Post[]
     */
    public function all_pages(): array {
        if ( ! function_exists( 'get_posts' ) ) {
            return [];
        }

        $pages = get_posts(
            [
                'post_type'      => 'page',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        return is_array( $pages ) ? $pages : [];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function assign_page( string $page_key, int $page_id, string $parent_key = '' ): array|\WP_Error {
        $flat_pages = $this->flat_pages();
        if ( '' === $page_key || ! isset( $flat_pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        if ( $page_id > 0 && ( ! function_exists( 'get_post' ) || ! get_post( $page_id ) ) ) {
            return new \WP_Error( 'page_not_found', 'Selected page was not found.' );
        }

        if ( function_exists( 'update_option' ) ) {
            update_option( $this->option_key( $page_key ), $page_id );
        }

        if ( $page_id > 0 && '' !== $parent_key ) {
            $parent_page_id = $this->assigned_page_id( $parent_key );
            if ( $parent_page_id > 0 && function_exists( 'wp_update_post' ) ) {
                wp_update_post(
                    [
                        'ID'          => $page_id,
                        'post_parent' => $parent_page_id,
                    ]
                );
            }
        }

        $this->log( 'Page assigned: ' . $page_key . ' = ' . $page_id );

        $payload = [
            'page_key' => $page_key,
            'page_id'  => $page_id,
        ];

        if ( $page_id > 0 ) {
            $payload = array_merge( $payload, $this->page_payload( $page_id ) );
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function create_page( string $page_key, string $title = '', string $slug = '', string $parent_key = '' ): array|\WP_Error {
        $flat_pages = $this->flat_pages();
        if ( '' === $page_key || ! isset( $flat_pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        $page_def = $flat_pages[ $page_key ];
        $title    = '' !== $title ? $title : (string) ( $page_def['title'] ?? '' );
        $slug     = '' !== $slug ? $slug : (string) ( $page_def['slug'] ?? $page_key );
        $parent_key = '' !== $parent_key ? $parent_key : (string) ( $page_def['parent'] ?? '' );

        if ( '' === $title ) {
            return new \WP_Error( 'missing_title', 'Page title is required.' );
        }

        $existing_assigned = $this->assigned_page_id( $page_key );
        if ( $existing_assigned > 0 && function_exists( 'get_post' ) && get_post( $existing_assigned ) ) {
            return $this->page_payload( $existing_assigned, true, 'Page already assigned.' );
        }

        $parent_id = '' !== $parent_key ? $this->assigned_page_id( $parent_key ) : 0;

        $existing_pages = function_exists( 'get_posts' )
            ? get_posts(
                array_filter(
                    [
                        'name'           => $slug,
                        'post_type'      => 'page',
                        'post_status'    => 'any',
                        'posts_per_page' => 1,
                        'post_parent'    => $parent_id ?: null,
                    ],
                    static fn( mixed $value ): bool => null !== $value
                )
            )
            : [];

        if ( ! empty( $existing_pages ) ) {
            $existing = $existing_pages[0];
            if ( $existing instanceof \WP_Post && $this->is_managed_page( $existing->ID, $page_key ) ) {
                $this->mark_managed_page( $existing->ID, $page_key );
                update_option( $this->option_key( $page_key ), $existing->ID );

                return $this->page_payload( $existing->ID, true, 'Existing managed page assigned.' );
            }

            return new \WP_Error( 'duplicate_slug', 'A page with this slug already exists. Assign it from the dropdown instead of creating a duplicate.' );
        }

        if ( ! function_exists( 'wp_insert_post' ) ) {
            return new \WP_Error( 'wordpress_unavailable', 'WordPress page creation is unavailable.' );
        }

        $page_id = wp_insert_post(
            [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => $parent_id,
            ]
        );

        if ( is_wp_error( $page_id ) ) {
            return $page_id;
        }

        $page_id = (int) $page_id;
        $this->mark_managed_page( $page_id, $page_key );
        update_option( $this->option_key( $page_key ), $page_id );
        $this->log( 'Page created: ' . $title . ' (ID: ' . $page_id . ', key: ' . $page_key . ')' );

        return $this->page_payload( $page_id );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function delete_page( string $page_key, int $page_id = 0 ): array|\WP_Error {
        if ( '' === $page_key ) {
            return new \WP_Error( 'missing_page_key', 'Page key is required.' );
        }

        $assigned_page_id = $this->assigned_page_id( $page_key );
        if ( $assigned_page_id > 0 ) {
            $page_id = $assigned_page_id;
        }

        if ( $page_id <= 0 ) {
            return new \WP_Error( 'missing_page_id', 'Page ID is required.' );
        }

        if ( function_exists( 'delete_option' ) ) {
            delete_option( $this->option_key( $page_key ) );
        }

        if ( ! $this->is_managed_page( $page_id, $page_key ) ) {
            $this->log( 'Page unassigned without deletion: ' . $page_key . ' (ID: ' . $page_id . ')' );

            return [
                'page_key' => $page_key,
                'page_id'  => $page_id,
                'trashed'  => false,
                'message'  => 'Page unassigned. Existing content was left intact.',
            ];
        }

        if ( ! function_exists( 'wp_trash_post' ) || ! wp_trash_post( $page_id ) ) {
            return new \WP_Error( 'delete_failed', 'Failed to delete page.' );
        }

        $this->log( 'Page deleted: ' . $page_key . ' (ID: ' . $page_id . ')' );

        return [
            'page_key' => $page_key,
            'page_id'  => $page_id,
            'trashed'  => true,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function menu_item_labels( array $items ): array {
        $by_id = [];
        foreach ( $items as $item ) {
            if ( is_object( $item ) && isset( $item->ID ) ) {
                $by_id[ (int) $item->ID ] = $item;
            }
        }

        $rows = [];
        foreach ( $items as $item ) {
            if ( ! is_object( $item ) || ! isset( $item->ID ) ) {
                continue;
            }

            $depth     = 0;
            $parent_id = (int) ( $item->menu_item_parent ?? 0 );
            $seen      = [];
            while ( $parent_id > 0 && isset( $by_id[ $parent_id ] ) && ! isset( $seen[ $parent_id ] ) ) {
                $seen[ $parent_id ] = true;
                $depth++;
                $parent_id = (int) ( $by_id[ $parent_id ]->menu_item_parent ?? 0 );
            }

            $title = trim( (string) ( $item->title ?? '' ) );
            if ( '' === $title ) {
                $title = '(Untitled item #' . (int) $item->ID . ')';
            }

            $rows[] = [
                'id'        => (int) $item->ID,
                'title'     => $title,
                'label'     => str_repeat( '-- ', $depth ) . $title,
                'depth'     => $depth,
                'parent_id' => (int) ( $item->menu_item_parent ?? 0 ),
                'object_id' => (int) ( $item->object_id ?? 0 ),
                'object'    => (string) ( $item->object ?? '' ),
                'type'      => (string) ( $item->type ?? '' ),
                'url'       => (string) ( $item->url ?? '' ),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,object>|null $menus
     */
    public function guess_menu_id_for_structure( string $structure_key, ?array $menus = null ): int {
        $menus = null === $menus && function_exists( 'wp_get_nav_menus' ) ? wp_get_nav_menus() : ( $menus ?? [] );
        $terms = $this->config['menu_guess_terms'][ $structure_key ] ?? [ $structure_key ];

        foreach ( $menus as $menu ) {
            $name = strtolower( (string) ( $menu->name ?? '' ) );
            foreach ( $terms as $needle ) {
                $needle = strtolower( (string) $needle );
                if ( '' !== $needle && false !== strpos( $name, $needle ) ) {
                    return (int) ( $menu->term_id ?? 0 );
                }
            }
        }

        return ! empty( $menus[0]->term_id ) ? (int) $menus[0]->term_id : 0;
    }

    public function get_menu_item_from_menu( int $menu_id, int $menu_item_id ): ?object {
        if ( $menu_id <= 0 || $menu_item_id <= 0 || ! function_exists( 'wp_get_nav_menu_items' ) ) {
            return null;
        }

        $items = wp_get_nav_menu_items( $menu_id ) ?: [];
        foreach ( $items as $item ) {
            if ( (int) $item->ID === $menu_item_id ) {
                return $item;
            }
        }

        return null;
    }

    public function find_page_menu_item( int $menu_id, int $page_id ): ?object {
        $items = function_exists( 'wp_get_nav_menu_items' ) ? ( wp_get_nav_menu_items( $menu_id ) ?: [] ) : [];
        foreach ( $items as $item ) {
            if ( 'post_type' === ( $item->type ?? '' ) && 'page' === ( $item->object ?? '' ) && (int) ( $item->object_id ?? 0 ) === $page_id ) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function upsert_page_menu_item( int $menu_id, int $page_id, int $parent_menu_item_id = 0 ): array|\WP_Error {
        $existing    = $this->find_page_menu_item( $menu_id, $page_id );
        $existing_id = $existing ? (int) $existing->ID : 0;

        $result = function_exists( 'wp_update_nav_menu_item' )
            ? wp_update_nav_menu_item(
                $menu_id,
                $existing_id,
                [
                    'menu-item-object-id' => $page_id,
                    'menu-item-object'    => 'page',
                    'menu-item-type'      => 'post_type',
                    'menu-item-parent-id' => $parent_menu_item_id,
                    'menu-item-status'    => 'publish',
                    'menu-item-title'     => function_exists( 'get_the_title' ) ? get_the_title( $page_id ) : '',
                ]
            )
            : new \WP_Error( 'wordpress_unavailable', 'WordPress menu updates are unavailable.' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'success'      => true,
            'message'      => $existing_id ? 'Menu item updated.' : 'Menu item created.',
            'menu_item_id' => (int) $result,
            'created'      => ! $existing_id,
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function create_navigation_menu( string $menu_name ): array|\WP_Error {
        $menu_name = trim( $menu_name );
        if ( '' === $menu_name ) {
            return new \WP_Error( 'missing_menu_name', 'Menu name is required.' );
        }

        $existing = function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_name ) : null;
        if ( $existing ) {
            return [
                'menu_id' => (int) $existing->term_id,
                'name'    => $existing->name,
                'message' => 'Menu already exists.',
            ];
        }

        $menu_id = function_exists( 'wp_create_nav_menu' ) ? wp_create_nav_menu( $menu_name ) : new \WP_Error( 'wordpress_unavailable', 'WordPress menu creation is unavailable.' );
        if ( is_wp_error( $menu_id ) ) {
            return $menu_id;
        }

        $this->log( 'Navigation menu created: ' . $menu_name . ' (ID: ' . (int) $menu_id . ')' );

        return [
            'menu_id' => (int) $menu_id,
            'name'    => $menu_name,
            'message' => 'Menu created.',
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function delete_navigation_menu( int $menu_id ): array|\WP_Error {
        $menu = $menu_id > 0 && function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_id ) : null;
        if ( ! $menu ) {
            return new \WP_Error( 'menu_not_found', 'Menu not found.' );
        }

        $deleted = function_exists( 'wp_delete_nav_menu' ) ? wp_delete_nav_menu( $menu_id ) : false;
        if ( ! $deleted || is_wp_error( $deleted ) ) {
            return is_wp_error( $deleted ) ? $deleted : new \WP_Error( 'menu_delete_failed', 'Menu deletion failed.' );
        }

        $this->log( 'Navigation menu deleted: ' . $menu->name . ' (ID: ' . $menu_id . ')' );

        return [
            'menu_id' => $menu_id,
            'name'    => $menu->name,
            'message' => 'Menu deleted.',
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function attach_page_to_menu_item( int $menu_id, string $page_key, int $parent_item_id = 0 ): array|\WP_Error {
        $flat_pages = $this->flat_pages();

        if ( $menu_id <= 0 || ! function_exists( 'wp_get_nav_menu_object' ) || ! wp_get_nav_menu_object( $menu_id ) ) {
            return new \WP_Error( 'menu_not_found', 'Menu not found.' );
        }

        if ( '' === $page_key || empty( $flat_pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        if ( $parent_item_id > 0 && ! $this->get_menu_item_from_menu( $menu_id, $parent_item_id ) ) {
            return new \WP_Error( 'invalid_parent_item', 'Parent menu item does not belong to the selected menu.' );
        }

        $page_id = $this->assigned_page_id( $page_key );
        if ( $page_id <= 0 || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $page_id ) ) {
            return new \WP_Error( 'page_not_assigned', (string) $flat_pages[ $page_key ]['title'] . ' is not assigned to a published page.' );
        }

        $result = $this->upsert_page_menu_item( $menu_id, $page_id, $parent_item_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $menu = wp_get_nav_menu_object( $menu_id );
        $this->log( 'Attached page to menu: ' . $page_key . ' -> ' . $menu->name );

        return [
            'message'      => $flat_pages[ $page_key ]['title'] . ' attached to ' . $menu->name . '.',
            'menu_item_id' => (int) $result['menu_item_id'],
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function attach_menu_structure( int $menu_id, string $structure_key, int $parent_item_id = 0 ): array|\WP_Error {
        $structures = $this->menu_structures();
        $flat_pages = $this->flat_pages();

        if ( $menu_id <= 0 || ! function_exists( 'wp_get_nav_menu_object' ) || ! wp_get_nav_menu_object( $menu_id ) ) {
            return new \WP_Error( 'menu_not_found', 'Menu not found.' );
        }

        if ( '' === $structure_key || empty( $structures[ $structure_key ] ) ) {
            return new \WP_Error( 'unknown_menu_structure', 'Unknown menu structure.' );
        }

        if ( $parent_item_id > 0 && ! $this->get_menu_item_from_menu( $menu_id, $parent_item_id ) ) {
            return new \WP_Error( 'invalid_parent_item', 'Parent menu item does not belong to the selected menu.' );
        }

        $added          = 0;
        $updated        = 0;
        $skipped        = 0;
        $created_by_key = [];

        foreach ( $structures[ $structure_key ]['page_keys'] ?? [] as $page_key ) {
            $page_key = (string) $page_key;
            if ( empty( $flat_pages[ $page_key ] ) ) {
                $skipped++;
                continue;
            }

            $page_id = $this->assigned_page_id( $page_key );
            if ( $page_id <= 0 || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $page_id ) ) {
                $skipped++;
                continue;
            }

            $item_parent_id = $parent_item_id;
            $parent_key     = $flat_pages[ $page_key ]['parent'] ?? null;
            if ( $parent_key && isset( $created_by_key[ $parent_key ] ) ) {
                $item_parent_id = (int) $created_by_key[ $parent_key ];
            }

            $result = $this->upsert_page_menu_item( $menu_id, $page_id, $item_parent_id );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $created_by_key[ $page_key ] = (int) $result['menu_item_id'];
            if ( ! empty( $result['created'] ) ) {
                $added++;
            } else {
                $updated++;
            }
        }

        $menu    = wp_get_nav_menu_object( $menu_id );
        $label   = (string) ( $structures[ $structure_key ]['title'] ?? $structure_key );
        $message = $label . ' attached to ' . $menu->name . ': ' . $added . ' added, ' . $updated . ' updated, ' . $skipped . ' skipped.';
        $this->log( 'Navigation structure attached: ' . $message );

        return [
            'message' => $message,
            'added'   => $added,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function add_all_pages_to_menu( int $menu_id ): array|\WP_Error {
        $menu = $menu_id > 0 && function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_id ) : null;
        if ( ! $menu ) {
            return new \WP_Error( 'menu_not_found', 'Menu not found.' );
        }

        $added         = 0;
        $pages         = $this->pages();
        $existing_items = function_exists( 'wp_get_nav_menu_items' ) ? ( wp_get_nav_menu_items( $menu_id ) ?: [] ) : [];
        $existing_ids  = [];
        foreach ( $existing_items as $item ) {
            if ( 'post_type' === ( $item->type ?? '' ) && 'page' === ( $item->object ?? '' ) ) {
                $existing_ids[] = (int) $item->object_id;
            }
        }

        foreach ( $pages as $page_key => $page_data ) {
            $page_id = $this->assigned_page_id( (string) $page_key );
            if ( $page_id <= 0 || ! function_exists( 'get_post' ) || ! get_post( $page_id ) ) {
                continue;
            }

            $parent_menu_item_id = 0;
            if ( in_array( $page_id, $existing_ids, true ) ) {
                foreach ( $existing_items as $item ) {
                    if ( (int) ( $item->object_id ?? 0 ) === $page_id ) {
                        $parent_menu_item_id = (int) $item->ID;
                        break;
                    }
                }
            } else {
                $result = $this->upsert_page_menu_item( $menu_id, $page_id, 0 );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                $parent_menu_item_id = (int) $result['menu_item_id'];
                $added++;
            }

            foreach ( (array) ( $page_data['children'] ?? [] ) as $child_key => $child_data ) {
                $child_id = $this->assigned_page_id( (string) $child_key );
                if ( $child_id <= 0 || ! function_exists( 'get_post' ) || ! get_post( $child_id ) || in_array( $child_id, $existing_ids, true ) ) {
                    continue;
                }

                $result = $this->upsert_page_menu_item( $menu_id, $child_id, $parent_menu_item_id );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                $added++;
            }
        }

        $message = 0 === $added ? 'All pages already in menu (0 added).' : $added . ' pages added to "' . $menu->name . '".';
        $this->log( 'Added ' . $added . ' pages to menu: ' . $menu->name );

        return [ 'message' => $message, 'added' => $added ];
    }

    /**
     * @return array<string,mixed>
     */
    public function page_payload( int $page_id, bool $existing = false, string $message = '' ): array {
        return [
            'page_id'   => $page_id,
            'existing'  => $existing,
            'permalink' => function_exists( 'get_permalink' ) ? get_permalink( $page_id ) : '',
            'edit_url'  => function_exists( 'get_edit_post_link' ) ? ( get_edit_post_link( $page_id, 'raw' ) ?: '' ) : '',
            'title'     => function_exists( 'get_the_title' ) ? get_the_title( $page_id ) : '',
            'message'   => $message,
        ];
    }

    public function mark_managed_page( int $page_id, string $page_key ): void {
        if ( function_exists( 'update_post_meta' ) ) {
            update_post_meta( $page_id, (string) $this->config['managed_meta_key'], 1 );
            update_post_meta( $page_id, (string) $this->config['managed_key_meta_key'], $page_key );
        }
    }

    public function is_managed_page( int $page_id, string $page_key = '' ): bool {
        if ( $page_id <= 0 || ! function_exists( 'get_post_type' ) || 'page' !== get_post_type( $page_id ) ) {
            return false;
        }

        if ( ! function_exists( 'get_post_meta' ) || ! get_post_meta( $page_id, (string) $this->config['managed_meta_key'], true ) ) {
            return false;
        }

        if ( '' === $page_key ) {
            return true;
        }

        $stored_key = (string) get_post_meta( $page_id, (string) $this->config['managed_key_meta_key'], true );

        return '' === $stored_key || $stored_key === $page_key;
    }

    /**
     * @param array<string,array<string,mixed>> $pages
     * @param array<string,array<string,mixed>> $flat
     */
    private function flatten_pages( array $pages, array &$flat, ?string $parent_key = null ): void {
        foreach ( $pages as $page_key => $page_data ) {
            $page_key  = (string) $page_key;
            $page_data = is_array( $page_data ) ? $page_data : [];

            $children = isset( $page_data['children'] ) && is_array( $page_data['children'] ) ? $page_data['children'] : [];
            $page_data['key']      = $page_key;
            $page_data['parent']   = $page_data['parent'] ?? $parent_key;
            $page_data['children'] = $children;
            $flat[ $page_key ]     = $page_data;

            if ( ! empty( $children ) ) {
                $this->flatten_pages( $children, $flat, $page_key );
            }
        }
    }

    private function log( string $message ): void {
        if ( isset( $this->config['logger'] ) && is_callable( $this->config['logger'] ) ) {
            call_user_func( $this->config['logger'], $message );
        }
    }
}
