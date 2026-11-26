# Schema structures

Namespace: `Hexa\PluginCore\SchemaTools`

A plugin declares the schema structures it owns; Core does the rest. A structure is one kind of page the plugin describes (a homepage, a book, an FAQ): which pages it covers, the schema.org types it prints, the modes a site can choose and how it is built. Every structure ships working: its default mode applies until a site chooses another, so turning the plugin on is enough for its schema to print.

```php
use Hexa\PluginCore\SchemaTools\SchemaContext;
use Hexa\PluginCore\SchemaTools\SchemaNodes;
use Hexa\PluginCore\SchemaTools\SchemaStructures;

SchemaStructures::register( 'example-plugin', [
    'plugin'       => 'Example Plugin',
    'settings_url' => admin_url( 'options-general.php?page=example&tab=schema' ),
    'priority'     => 10,
    'structures'   => [
        [
            'id'      => 'homepage',
            'label'   => 'Homepage',
            'where'   => 'The front page.',
            'modes'   => [ 'profile_page' => [ 'label' => 'Profile page', 'types' => 'ProfilePage, Person' ], 'person' => [ 'label' => 'Person only', 'types' => 'Person' ] ],
            'default' => 'profile_page',
            'option'  => 'example_homepage_schema',   // optional: the plugin's own option for the mode
            'applies' => static fn( SchemaContext $context ): bool => $context->is_front_page(),
            'build'   => static fn( SchemaContext $context, string $mode ): array => [ SchemaNodes::person( [ '@id' => SchemaIds::person(), 'name' => 'Ada' ] ) ],
            'sample'  => static fn(): SchemaContext => SchemaContext::for_front_page(),
        ],
    ],
] );
```

- Without `modes`, a structure has the single mode `on`. Every structure can be `off`.
- `default => 'off'` ships a structure off.
- Without `option`, modes are kept in `hexa_plugin_core_schema_structure_modes` (owner → id → mode). A stored mode that is no longer declared falls back to the default.
- `build` receives the page as a `SchemaContext` (kind, WordPress object, address), never the global query, so `preview()` can build it for the `sample` page.

## Showing it

`SchemaStructuresPanel::render( $owner )` in the plugin's Schema tab lists each structure with its pages, mode select, Preview (the merged document on its sample page, with validation issues) and links to the page, the Schema.org validator and Google's Rich Results test. Its AJAX actions (`hexa_schema_structures_list`, `_save`, `_preview`, nonce `hexa_schema_structures`) are registered automatically in the admin.

## Reading it

- `SchemaStructures::mode( $owner, $id )`, `enabled()`, `set_mode()`.
- `SchemaStructures::nodes( $owner, $context )` and `document( $owner, $context )` give what a page carries from the owner, merged as printed, without changing the current page's schema.
- `wp hexa schema list [--owner=…]`, `wp hexa schema preview --owner=… --id=…`, `wp hexa schema set --owner=… --id=… --mode=…`.

## Building nodes

Builders stay generic: they take plain values. `SchemaNodes::person_profile( $record )` builds a complete Person from one record (names, alternate names, honorifics, title, description, contact, gender, birth date and place, nationality as Country nodes, languages, education as `alumniOf` and `hasCredential`, occupations, awards, images, employers, `sameAs`, the Knowledge Graph ID as an `identifier`, `mainEntityOfPage`, and raw `properties` that override). Plugins read their own fields into that record.

Test with `php tests/schema-structures.php` and `php tests/schema-person-profile.php`.

## Local Business

Build a default-off Local Business structure by adding `LocalBusinessStructure::definition( $records_for_page, $sample )` to an owner's `SchemaStructures::register()` call. Core passes the current WordPress page ID to the host callback. The callback returns the mapped business records, each with a stable site-wide `@id`, a `type` and business details. The optional sample supplies a mapped `SchemaContext` for the Core preview. Static front pages mapped to a business are also supported.

Use `new Hexa\PluginCore\FieldStructures\LocalBusinessFields( $option_prefix )` to build the native Core field group. Pass the current Core mode to `group()` so the business fields appear when the owner enables the structure. Use `records()` for the editor, `validate()` after Core sanitization, `pages()` for mapping, and `schema_record()` to turn a stored row into shared builder input. The host owns storage and page assignments.

Use `SchemaNodes::local_business( $record, $type )` to build the node directly. It recognizes LocalBusiness and the current published descendants from [Schema.org](https://schema.org/LocalBusiness). An unrecognized type uses LocalBusiness. An address can consist of a city without a street. Weekly opening hours accept `days` or `dayOfWeek`, `opens` and `closes` (24-hour time). The [OpeningHoursSpecification](https://schema.org/OpeningHoursSpecification) builder preserves overnight ranges and omits incomplete entries.

Test the builder and fields with `php tests/schema-local-business.php`.
