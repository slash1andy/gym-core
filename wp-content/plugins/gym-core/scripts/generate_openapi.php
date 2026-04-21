<?php
/**
 * generate_openapi.php — M6.10 OpenAPI 3.1 spec generator for gym/v1.
 *
 * Runs under WP-CLI and introspects the live REST server's registered routes
 * for the gym/v1 namespace, emitting an OpenAPI document at
 * wp-content/plugins/gym-core/docs/gym-v1-openapi.json.
 *
 * Why a live-introspection script rather than parsing PHP: several controllers
 * compose route paths at runtime (e.g., `$this->rest_base`), and only the WP
 * REST server knows the final registered path, methods, args, and schema.
 *
 * Usage (from a WP-CLI context on the target site):
 *
 *   wp eval-file wp-content/plugins/gym-core/scripts/generate_openapi.php
 *   wp eval-file wp-content/plugins/gym-core/scripts/generate_openapi.php --out=/tmp/gym.json
 *
 * Exits with non-zero status on failure; prints the output path on success.
 *
 * @package Gym_Core
 */

declare(strict_types=1);

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "Run under WP-CLI: wp eval-file wp-content/plugins/gym-core/scripts/generate_openapi.php\n" );
	exit( 1 );
}

// Allow --out=/path to override the default output file.
$out_path = GYM_CORE_PATH . 'docs/gym-v1-openapi.json';
if ( defined( 'GYM_CORE_PATH' ) ) {
	foreach ( $args ?? array() as $a ) {
		if ( is_string( $a ) && str_starts_with( $a, '--out=' ) ) {
			$out_path = substr( $a, 6 );
		}
	}
}

// Ensure the target dir exists.
$out_dir = dirname( $out_path );
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0755, true );
}

// Force route registration (rest_api_init normally only fires on REST requests).
do_action( 'rest_api_init' );

$server = rest_get_server();
$routes = $server->get_routes();

$namespace       = 'gym/v1';
$namespace_slash = '/' . $namespace;

$paths       = array();
$route_count = 0;

foreach ( $routes as $route => $handlers ) {
	if ( ! str_starts_with( $route, $namespace_slash ) ) {
		continue;
	}
	// Skip the namespace index itself — it's a WP artifact, not a gym contract endpoint.
	if ( $route === $namespace_slash ) {
		continue;
	}

	$route_count++;

	// Convert WP regex path params to OpenAPI {name} placeholders.
	$oa_path = preg_replace( '/\(\?P<([a-z_]+)>[^)]+\)/', '{$1}', $route );

	// Extract path params with simple types (int if regex contains \d, else string).
	$path_params = array();
	if ( preg_match_all( '/\(\?P<([a-z_]+)>([^)]+)\)/', $route, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $m ) {
			$type          = ( str_contains( $m[2], '\\d' ) ) ? 'integer' : 'string';
			$path_params[] = array(
				'name'     => $m[1],
				'in'       => 'path',
				'required' => true,
				'schema'   => array( 'type' => $type ),
			);
		}
	}

	$operations = array();

	foreach ( $handlers as $handler ) {
		if ( empty( $handler['methods'] ) || ! is_array( $handler['methods'] ) ) {
			continue;
		}

		foreach ( $handler['methods'] as $method => $enabled ) {
			if ( ! $enabled ) {
				continue;
			}

			$method_lower = strtolower( (string) $method );

			$parameters = $path_params;

			// Query args for GET (path params already added above).
			if ( 'get' === $method_lower && ! empty( $handler['args'] ) && is_array( $handler['args'] ) ) {
				foreach ( $handler['args'] as $arg_name => $arg_spec ) {
					if ( ! is_string( $arg_name ) || ! is_array( $arg_spec ) ) {
						continue;
					}
					// Don't double-add params that are already in the path.
					foreach ( $path_params as $pp ) {
						if ( $pp['name'] === $arg_name ) {
							continue 2;
						}
					}
					$parameters[] = array(
						'name'        => $arg_name,
						'in'          => 'query',
						'required'    => ! empty( $arg_spec['required'] ),
						'description' => (string) ( $arg_spec['description'] ?? '' ),
						'schema'      => array(
							'type'    => (string) ( $arg_spec['type'] ?? 'string' ),
							'default' => $arg_spec['default'] ?? null,
						),
					);
				}
			}

			$request_body = null;
			if ( in_array( $method_lower, array( 'post', 'put', 'patch', 'delete' ), true ) && ! empty( $handler['args'] ) ) {
				$body_props = array();
				$required   = array();
				foreach ( $handler['args'] as $arg_name => $arg_spec ) {
					if ( ! is_string( $arg_name ) || ! is_array( $arg_spec ) ) {
						continue;
					}
					// Path params belong in parameters, not body.
					foreach ( $path_params as $pp ) {
						if ( $pp['name'] === $arg_name ) {
							continue 2;
						}
					}
					$body_props[ $arg_name ] = array(
						'type'        => (string) ( $arg_spec['type'] ?? 'string' ),
						'description' => (string) ( $arg_spec['description'] ?? '' ),
					);
					if ( ! empty( $arg_spec['required'] ) ) {
						$required[] = $arg_name;
					}
				}
				if ( ! empty( $body_props ) ) {
					$body_schema = array(
						'type'       => 'object',
						'properties' => $body_props,
					);
					if ( ! empty( $required ) ) {
						$body_schema['required'] = $required;
					}
					$request_body = array(
						'required' => true,
						'content'  => array(
							'application/json' => array( 'schema' => $body_schema ),
						),
					);
				}
			}

			$operation = array(
				'operationId' => sprintf( '%s_%s', $method_lower, preg_replace( '/[^a-z0-9]+/i', '_', trim( $oa_path, '/' ) ) ),
				'parameters'  => $parameters,
				'responses'   => array(
					'200' => array(
						'description' => 'Success',
						'content'     => array(
							'application/json' => array(
								'schema' => array(
									'$ref' => '#/components/schemas/SuccessEnvelope',
								),
							),
						),
					),
					'401' => array(
						'description' => 'Authentication required',
						'content'     => array(
							'application/json' => array(
								'schema' => array( '$ref' => '#/components/schemas/ErrorResponse' ),
							),
						),
					),
					'403' => array(
						'description' => 'Forbidden',
						'content'     => array(
							'application/json' => array(
								'schema' => array( '$ref' => '#/components/schemas/ErrorResponse' ),
							),
						),
					),
					'400' => array(
						'description' => 'Bad request',
						'content'     => array(
							'application/json' => array(
								'schema' => array( '$ref' => '#/components/schemas/ErrorResponse' ),
							),
						),
					),
				),
			);

			if ( null !== $request_body ) {
				$operation['requestBody'] = $request_body;
			}

			$operations[ $method_lower ] = $operation;
		}
	}

	if ( ! empty( $operations ) ) {
		$paths[ $oa_path ] = $operations;
	}
}

ksort( $paths );

$openapi = array(
	'openapi' => '3.1.0',
	'info'    => array(
		'title'       => 'Gym Core REST API',
		'description' => 'gym/v1 endpoints consumed by the Gandalf AI agents (M6.1) and the staff dashboard. Generated from the live WP REST registry via wp-content/plugins/gym-core/scripts/generate_openapi.php.',
		'version'     => defined( 'GYM_CORE_VERSION' ) ? (string) GYM_CORE_VERSION : '1.0.0',
	),
	'servers' => array(
		array( 'url' => rest_url( 'gym/v1' ), 'description' => 'This site' ),
	),
	'components' => array(
		'schemas' => array(
			'SuccessEnvelope' => array(
				'type'       => 'object',
				'required'   => array( 'success', 'data' ),
				'properties' => array(
					'success' => array( 'type' => 'boolean', 'enum' => array( true ) ),
					'data'    => array(),
					'meta'    => array(
						'type'       => 'object',
						'properties' => array(
							'pagination' => array(
								'type'       => 'object',
								'properties' => array(
									'total'       => array( 'type' => 'integer', 'minimum' => 0 ),
									'total_pages' => array( 'type' => 'integer', 'minimum' => 0 ),
									'page'        => array( 'type' => 'integer', 'minimum' => 1 ),
									'per_page'    => array( 'type' => 'integer', 'minimum' => 1 ),
								),
							),
						),
					),
				),
			),
			'ErrorResponse' => array(
				'type'       => 'object',
				'required'   => array( 'code', 'message', 'data' ),
				'properties' => array(
					'code'    => array( 'type' => 'string' ),
					'message' => array( 'type' => 'string' ),
					'data'    => array(
						'type'       => 'object',
						'properties' => array(
							'status' => array( 'type' => 'integer' ),
						),
					),
				),
			),
		),
	),
	'paths' => (object) $paths,
);

$json = wp_json_encode( $openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
if ( false === $json ) {
	WP_CLI::error( 'Failed to encode OpenAPI document.' );
}

$written = file_put_contents( $out_path, $json );
if ( false === $written ) {
	WP_CLI::error( "Failed to write {$out_path}" );
}

WP_CLI::success( sprintf( 'OpenAPI spec written: %s (%d routes, %d bytes).', $out_path, $route_count, $written ) );
