<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly
/**
 * Class Disciple_Tools_Mobile_API_Endpoints
 */
class Disciple_Tools_Mobile_API_Endpoints
{
    /**
     * Disciple_Tools_Mobile_API_Endpoints The single instance of Disciple_Tools_Mobile_API_Endpoints.
     *
     * @var    object
     * @access private
     * @since  0.1.0
     */
    private static $_instance = null;

    /**
     * Main Disciple_Tools_Mobile_API_Endpoints Instance
     * Ensures only one instance of Disciple_Tools_Mobile_API_Endpoints is loaded or can be loaded.
     *
     * @since  0.1.0
     * @static
     * @return Disciple_Tools_Mobile_API_Endpoints instance
     */
    public static function instance()
    {
        if (is_null( self::$_instance )) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    private $version = 1.0;
    private $context = "dt-mobile-api";
    private $namespace;

    /**
     * Constructor function.
     *
     * @access public
     * @since  0.1.0
     */
    public function __construct()
    {
        $this->namespace = $this->context . "/v" . intval( $this->version );
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    } // End __construct()

    /**
     * Setup the api routes for the plugin
     *
     * @since  0.1.0
     */
    public function add_api_routes()
    {
        register_rest_route(
            $this->namespace, 'contacts', [
                'methods' => 'GET',
                'callback' => [ $this, 'get_contacts' ],
            ]
        );
    }

    /**
     * Get Contacts viewable by a user
     *
     * @param  WP_REST_Request $request
     *
     * @access public
     * @since  0.1.0
     * @return array|WP_Error return the user's contacts
     */
    public function get_contacts( WP_REST_Request $request)
    {
        $current_user = wp_get_current_user();
        $connected = new WP_Query( array(
            'connected_type' => 'team_member_locations',
            'connected_items' => $current_user,
        ) );
        $location_meta = get_post_meta( $connected->post->ID, 'raw', true );
        $user_location = $location_meta['results'][0]['geometry']['location'];

        // Get contacts assigned to current user and in seeker_path "Contact Attempt Needed"
        $result = Disciple_Tools_Contacts::search_viewable_contacts([
            assigned_to => [ 'me' ],
            seeker_path => [ 'none' ]
        ]);

        if (is_wp_error( $result )) {
            return $result;
        }
        return [
            "contacts" => $this->add_related_info_to_contacts( $result["contacts"], $user_location ),
            "total" => $result["total"],
            "deleted" => $result["deleted"],
            "user_location" => $user_location
        ];
    }

    /**
     * @param array $contacts
     *
     * @return array
     */
    private function add_related_info_to_contacts( array $contacts, $user_location ): array
    {
        p2p_type( 'contacts_to_locations' )->each_connected( $contacts, [], 'locations' );
        p2p_type( 'contacts_to_groups' )->each_connected( $contacts, [], 'groups' );
        $rv = [];
        foreach ( $contacts as $contact ) {
            $meta_fields = get_post_custom( $contact->ID );
            $contact_array = [];
            $contact_array["ID"] = $contact->ID;
            $contact_array["post_title"] = $contact->post_title;
            $contact_array["is_team_contact"] = $contact->is_team_contact ?? false;
            $contact_array['permalink'] = get_post_permalink( $contact->ID );
            $contact_array['overall_status'] = get_post_meta( $contact->ID, 'overall_status', true );
            $contact_array['locations'] = [];
            $contact_array['locations_geometry'] = [];
            $contact_array['distance'] = [];
            foreach ( $contact->locations as $location ) {
                $contact_array['locations'][] = $location->post_title;

                $raw = get_post_meta( $location->ID, 'raw', true );
                $geometry = $raw['results'][0]['geometry'];
                $contact_location = $geometry['location'];
                $contact_array['locations_geometry'][] = $geometry;

                if ( !empty( $geometry )) {
                    $distance_miles = $this->calculate_distance(
                        $user_location['lat'],
                        $user_location['lng'],
                        $contact_location['lat'],
                        $contact_location['lng'],
                        'M'
                    );
                    $contact_array['distance'][] = [
                        'miles' => $distance_miles,
                        'kilometers' => $distance_miles * 1.609344
                    ];
                } else {
                    $contact_array['distance'][] = [
                        'miles' => null,
                        'kilometers' => null
                    ];
                }
            }
            $contact_array['groups'] = [];
            foreach ( $contact->groups as $group ) {
                $contact_array['groups'][] = [
                    'id'         => $group->ID,
                    'post_title' => $group->post_title,
                    'permalink'  => get_permalink( $group->ID ),
                ];
            }
            $contact_array['phone_numbers'] = [];
            $contact_array['requires_update'] = false;
            foreach ( $meta_fields as $meta_key => $meta_value ) {
                if ( strpos( $meta_key, "contact_phone" ) === 0 && strpos( $meta_key, "details" ) === false ) {
                    $contact_array['phone_numbers'] = array_merge( $contact_array['phone_numbers'], $meta_value );
                } elseif ( strpos( $meta_key, "milestone_" ) === 0 ) {
                    $contact_array[ $meta_key ] = $this->yes_no_to_boolean( $meta_value[0] );
                } elseif ( $meta_key === "seeker_path" ) {
                    $contact_array[ $meta_key ] = $meta_value[0] ? $meta_value[0] : "none";
                } elseif ( $meta_key == "assigned_to" ) {
                    $type_and_id = explode( '-', $meta_value[0] );
                    if ( $type_and_id[0] == 'user' && isset( $type_and_id[1] ) ) {
                        $user = get_user_by( 'id', (int) $type_and_id[1] );
                        $contact_array["assigned_to"] = [
                            "id" => $type_and_id[1],
                            "type" => $type_and_id[0],
                            "name" => ( $user ? $user->display_name : "Nobody" ),
                            'user_login' => ( $user ? $user->user_login : "nobody" )
                        ];
                    }
                } elseif ( $meta_key == "requires_update" ) {
                    $contact_array[ $meta_key ] = $this->yes_no_to_boolean( $meta_value[0] );
                } elseif ( $meta_key == 'last_modified' ) {
                    $contact_array[ $meta_key ] = (int) $meta_value[0];
                }
            }
            $user_id = get_current_user_id();
            if ( isset( $contact_array["overall_status"] ) && isset( $contact_array["assigned_to"]["id"] ) &&
                $contact_array["overall_status"] === "assigned" && $contact_array["assigned_to"]["id"] == $user_id){
                $contact_array["requires_update"] = true;
            }
            $rv[] = $contact_array;
        }
        if (get_current_user_id()) {
            $contacts_shared_with_user = Disciple_Tools_Contacts::get_posts_shared_with_user(
                "contacts", get_current_user_id()
            );
            $ids_shared_with_user = [];
            foreach ( $contacts_shared_with_user as $contact ) {
                $ids_shared_with_user[$contact->ID] = true;
            }
            foreach ($rv as $index => $_) {
                $rv[$index]["shared_with_user"] = isset( $ids_shared_with_user[$rv[$index]["ID"]] );
            }
        }
        return $rv;
    }

    /**
     * @param string $yes_no
     *
     * @return bool
     * @throws \Error|bool 'Expected yes or no'.
     */
    private static function yes_no_to_boolean( string $yes_no ) {
        if ( $yes_no === 'yes' ) {
            return true;
        } elseif ( $yes_no === 'no' ) {
            return false;
        } else {
            return false;
//            @todo move error to saving
//            throw new Error( "Expected yes or no, instead got $yes_no" );
        }
    }

    /**
     * Calculate distance between two points given by latitude and longitude coordinates.
     * Copied from https://www.geodatasource.com/developers/php
     *
     * @param $lat1
     * @param $lng1
     * @param $lat2
     * @param $lng2
     * @param string $unit - Unit of measure desired. M=miles (default), K=kilometers, N=nautical miles
     * @return float
     */
    private static function calculate_distance( $lat1, $lng1, $lat2, $lng2, $unit ) {
        $theta = $lng1 - $lng2;
        $dist = sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $theta ) );
        $dist = acos( $dist );
        $dist = rad2deg( $dist );
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper( $unit );

        if ($unit == "K") {
            return ( $miles * 1.609344 );
        } else if ($unit == "N") {
            return ( $miles * 0.8684 );
        } else {
            return $miles;
        }
    }
}

Disciple_Tools_Mobile_API_Endpoints::instance();
