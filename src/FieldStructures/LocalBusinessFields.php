<?php
/** Describes reusable Local Business inputs in the Core field engine. */

namespace Hexa\PluginCore\FieldStructures;

use Hexa\PluginCore\SchemaTools\SchemaNodes;
use Hexa\PluginCore\SchemaTools\SchemaSources;

/** Supplies field definitions and converts their values to plain business records. */
final class LocalBusinessFields {
    /** Namespaces this shared field structure to its consumer's options and form. */
    public function __construct( private readonly string $prefix ) {}

    /** Returns the stable key used in Core Fields forms and sanitized rows. */
    public function key( string $name ): string {
        return 'field_' . $this->name( $name );
    }

    /** Returns the default-off switch as a shared field definition. */
    public function toggle(): array {
        return $this->field( 'enabled', 'Enable Local Business schema', 'true_false', [ 'default_value' => 0 ] );
    }

    /** Provides the registered field group, with record controls only if enabled. */
    public function group( bool $enabled ): array {
        $fields = [ $this->toggle() ];
        if ( $enabled ) {
            $fields[] = $this->records();
        }
        return [
            'key' => 'group_' . $this->name( 'settings' ),
            'title' => 'Local Business',
            'fields' => $fields,
            'location' => [],
        ];
    }

    /** Defines the repeatable business, hours, and page assignment fields. */
    public function records(): array {
        return $this->field( 'records', 'Businesses', 'repeater', [
            'button_label' => 'Add business',
            'sub_fields' => [
                $this->field( 'type', 'Business type', 'select', [
                    'choices' => SchemaNodes::local_business_types(),
                    'default_value' => 'LocalBusiness',
                    'required' => 1,
                ] ),
                $this->field( 'name', 'Business name', 'text', [ 'required' => 1 ] ),
                $this->field( 'telephone', 'Phone', 'text' ),
                $this->field( 'email', 'Email', 'email' ),
                $this->field( 'logo', 'Logo', 'image' ),
                $this->field( 'image', 'Business image', 'image' ),
                $this->field( 'street_address', 'Street address (optional)', 'text' ),
                $this->field( 'address_locality', 'City', 'text' ),
                $this->field( 'address_region', 'State or region', 'text' ),
                $this->field( 'postal_code', 'Postal code', 'text' ),
                $this->field( 'address_country', 'Country', 'text' ),
                $this->field( 'areas_served', 'Areas served', 'textarea', [ 'instructions' => 'One city or area per line.' ] ),
                $this->field( 'geo', 'Coordinates (optional)', 'google_map' ),
                $this->field( 'price_range', 'Price range', 'text', [ 'placeholder' => '$$' ] ),
                $this->field( 'hours', 'Opening hours (optional)', 'repeater', [
                    'button_label' => 'Add hours',
                    'sub_fields' => [
                        $this->field( 'days', 'Days', 'checkbox', [ 'choices' => SchemaNodes::opening_hour_days() ] ),
                        $this->field( 'opens', 'Opens', 'text', [ 'placeholder' => '09:00' ] ),
                        $this->field( 'closes', 'Closes', 'text', [ 'placeholder' => '17:00' ] ),
                    ],
                ] ),
                $this->field( 'same_as', 'Official profile links', 'textarea', [ 'instructions' => 'One URL per line.' ] ),
                $this->field( 'pages', 'Pages that show this business in schema', 'relationship', [
                    'post_type' => [ 'page' ],
                    'post_choice_limit' => -1,
                    'return_format' => 'id',
                ] ),
            ],
        ] );
    }

    /** Returns assigned page IDs from a raw Core Fields row. */
    public function pages( array $row ): array {
        return array_values( array_unique( array_filter( array_map( 'intval', (array) $this->value( $row, 'pages' ) ) ) ) );
    }

    /** Validates a sanitized business record before a consumer saves it. */
    public function validate( array $row ): void {
        if ( '' === $this->value( $row, 'name' ) ) {
            throw new \InvalidArgumentException( 'Every business needs a name.' );
        }
        if ( ! isset( SchemaNodes::local_business_types()[ $this->value( $row, 'type' ) ] ) ) {
            throw new \InvalidArgumentException( 'Choose an available business type for each business.' );
        }
        foreach ( $this->hours( $row ) as $hours ) {
            if ( [] === SchemaNodes::opening_hours_specification( $hours ) ) {
                throw new \InvalidArgumentException( 'Each opening-hours row needs at least one day and two 24-hour times, such as 09:00 and 17:00.' );
            }
        }
    }

    /** Translates one stored field row into the shared schema builder input. */
    public function schema_record( array $row, string $id ): array {
        $record = [ '@id' => $id ];
        foreach ( [
            'name', 'type', 'telephone', 'email', 'street_address', 'address_locality',
            'address_region', 'postal_code', 'address_country', 'price_range',
        ] as $name ) {
            $record[ $name ] = $this->value( $row, $name );
        }
        $geo = (array) $this->value( $row, 'geo' );
        $record['latitude'] = $geo['lat'] ?? null;
        $record['longitude'] = $geo['lng'] ?? null;
        foreach ( [ 'logo', 'image' ] as $name ) {
            $attachment = (int) $this->value( $row, $name );
            $record[ $name ] = $attachment > 0 ? SchemaSources::attachment_image( $attachment ) : [];
        }
        $record['areas_served'] = $this->lines( $row, 'areas_served' );
        $record['same_as'] = $this->lines( $row, 'same_as' );
        $record['hours'] = $this->hours( $row );
        return $record;
    }

    /** Translates the nested hours rows into the shared builder's day and time keys. */
    private function hours( array $row ): array {
        $hours = [];
        foreach ( (array) $this->value( $row, 'hours' ) as $entry ) {
            $hours[] = [
                'days' => (array) $this->value( $entry, 'days' ),
                'opens' => $this->value( $entry, 'opens' ),
                'closes' => $this->value( $entry, 'closes' ),
            ];
        }
        return $hours;
    }

    /** Splits one-entry-per-line form controls into builder inputs. */
    private function lines( array $row, string $name ): array {
        return preg_split( '/\R/u', (string) $this->value( $row, $name ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
    }

    /** Reads a value in the raw shape shared by Core renderer and sanitizer. */
    private function value( array $row, string $name ): mixed {
        return $row[ $this->key( $name ) ] ?? '';
    }

    /** Derives a storage name from the consumer's field prefix. */
    private function name( string $name ): string {
        return rtrim( $this->prefix, '_' ) . '_local_business_' . $name;
    }

    /** Creates a consistently named Core field with optional type attributes. */
    private function field( string $name, string $label, string $type, array $options = [] ): array {
        return [
            'key' => $this->key( $name ),
            'name' => $this->name( $name ),
            'label' => $label,
            'type' => $type,
        ] + $options;
    }
}
