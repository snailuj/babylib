<?php
namespace Babylcraft\GraphQL\TypeDef\Calendar;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;
use Babylcraft\GraphQL\TypeDef\BabylonTypes;

class VCalendarQuery {
    const SINGLE_NAME = "vcalendar";

	/**
	 * root_query
	 * @return array
	 */
	public static function root_query() {
		return [
			'type' => BabylonTypes::vcalendar(),
			'description' => __( 'Returns a calendar with the given owner and name', 'babylon-babylib' ),
			'args' => [
                'owner' => Types::non_null( Types::string() ),
                'name' => Types::non_null( Types::string() )
			],
			'resolve' => function(
                $root,    // The object/array being passed down the tree from the previous resolver
                $args,    // The arguments input for the field
                $context, // The context of the request. This typically includes info about the current_user, but can contain more info
                $info     /* Some information about where in the resolve tree the resolver is. It’s possible for resolvers to be called 
                            many different times within a single request, and sometimes having information about where in the resolve 
                            tree the resolver is being called can be helpful. */
            ) {
                try {
                    $calendar = $this->plugin->getFlightsCalendar('BA');
                    $data = @$calendar->jsonSerialize();
                    
                    return [
                        'uri' => $calendar->URI->getValue(),
                        'principalURI' => $calendar->PRINCIPALURI->getValue(),
                        'data' => json_encode($data)
                    ];
                } catch (\Exception $ex) {
                    $this->plugin->error($ex->getMessage());
                    throw $ex;
                } catch (\Error $err) {
                    $this->plugin->error($err->getMessage());
                }
            }
        ];
	}
}
