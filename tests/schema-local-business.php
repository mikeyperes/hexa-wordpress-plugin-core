<?php
/** Exercises the reusable Local Business builder and field definitions. */

use Hexa\PluginCore\FieldStructures\LocalBusinessFields;
use Hexa\PluginCore\SchemaTools\LocalBusinessStructure;
use Hexa\PluginCore\SchemaTools\SchemaNodes;
use Hexa\PluginCore\SchemaTools\SchemaStructures;

$core = dirname( __DIR__ );
require $core . '/bootstrap.php';
hexa_plugin_core_register_package( 'local-business-fixture', $core );
HexaPluginCorePackageRegistry::resolve();

/** Supports the schema link normalizer without booting WordPress. */
function untrailingslashit( string $value ): string { return rtrim( $value, '/\\' ); }

/** Fails immediately when a builder or definition breaks its contract. */
function check_business( bool $ok, string $message ): void {
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
}

$fields = new LocalBusinessFields( 'example_plugin_' );
$choices = SchemaNodes::local_business_types();
check_business( isset( $choices['FoodEstablishment'], $choices['Restaurant'], $choices['Store'], $choices['AutoRepair'] ), 'Common direct and nested business types are available.' );
check_business( count( $fields->group( false )['fields'] ) === 1, 'Disabled consumers get only the switch.' );
check_business( count( $fields->group( true )['fields'] ) === 2, 'Enabled consumers get the shared business field group.' );
check_business( SchemaStructures::OFF === LocalBusinessStructure::definition( static fn(): array => [] )['default'], 'The shared structure ships off.' );

$node = SchemaNodes::local_business( [
    '@id' => 'https://example.test/#restaurant',
    'name' => 'Example Bakery',
    'email' => 'store@example.test',
    'address_locality' => 'Miami',
    'areas_served' => [ 'Miami', 'Miami', [ 'type' => 'City', 'name' => 'Doral' ] ],
    'latitude' => '0',
    'longitude' => '-81.2',
    'price_range' => '$$',
    'hours' => [
        [ 'days' => [ 'Monday', 'https://schema.org/Monday', 'Friday' ], 'opens' => '18:00', 'closes' => '02:00' ],
        [ 'days' => [ 'Tuesday' ], 'opens' => '24:00', 'closes' => '09:00' ],
    ],
    'same_as' => [ 'https://example.test/business' ],
], 'Bakery' );

check_business( 'Bakery' === $node['@type'] && 'https://example.test/#restaurant' === $node['@id'], 'Subtype and stable ID are preserved.' );
check_business( 'Miami' === $node['address']['addressLocality'] && ! isset( $node['address']['streetAddress'] ), 'Street is optional.' );
check_business( count( $node['areaServed'] ) === 2 && '$$' === $node['priceRange'], 'Areas deduplicate and price range is included.' );
check_business( 0.0 === $node['geo']['latitude'] && -81.2 === $node['geo']['longitude'], 'Zero coordinates remain valid.' );
check_business( 1 === count( $node['openingHoursSpecification'] ), 'Invalid hours do not print.' );
$hours = $node['openingHoursSpecification'][0];
check_business( [ 'https://schema.org/Monday', 'https://schema.org/Friday' ] === $hours['dayOfWeek'], 'Days are normalized and deduplicated.' );
check_business( '18:00:00' === $hours['opens'] && '02:00:00' === $hours['closes'], 'Overnight ranges stay intact.' );
$minimal = SchemaNodes::local_business( [ 'name' => 'Basic location', 'latitude' => '1e400', 'longitude' => 1 ], 'Unknown' );
check_business( 'LocalBusiness' === $minimal['@type'] && ! isset( $minimal['geo'], $minimal['priceRange'], $minimal['openingHoursSpecification'] ), 'Invalid coordinates and unspecified optional values are omitted.' );
check_business( 'Organization' === SchemaNodes::organization( [ 'name' => 'Existing consumer' ], 'Bakery' )['@type'], 'The existing organization contract remains unchanged.' );

$without_hours = [ $fields->key( 'name' ) => 'No hours', $fields->key( 'type' ) => 'LocalBusiness' ];
$fields->validate( $without_hours );
check_business( [] === $fields->schema_record( $without_hours, 'https://example.test/#no-hours' )['hours'], 'Omitted hours produce no entries.' );
$without_hours[ $fields->key( 'hours' ) ] = [];
$fields->validate( $without_hours );
check_business( [] === $fields->schema_record( $without_hours, 'https://example.test/#no-hours' )['hours'], 'Empty hours produce no entries.' );

echo "PASS: Local Business field definitions and schema builders\n";
