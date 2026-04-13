<?php
/**
 * Auto-split from functions-front.php — do not load directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build subcategories bar HTML for current term.
 *
 * Logic:
 * - If current term has children with products => show its children
 * - Else if current term is a child => show siblings under parent, current child = active
 * - Else => no bar
 */
function ed_rest_get_subcats_html( $term ) {
	if ( ! $term || is_wp_error( $term ) || empty( $term->term_id ) ) {
		return '';
	}

	$wrapper_term_id = (int) $term->term_id;
	$active_slug     = '';
	$children        = get_terms([
		'taxonomy'   => 'product_cat',
		'parent'     => $wrapper_term_id,
		'hide_empty' => true,
		'orderby'    => 'menu_order',
		'order'      => 'ASC',
	]);

	// If current term has children => show them
	if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
		$items = [];

		foreach ( $children as $child ) {
			$url = trailingslashit( home_url( 'cat/' . $child->slug ) );

			$items[] = sprintf(
				'<a class="ed-mp__subcat-link" href="%s" data-ed-subcat="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $child->slug ),
				esc_html( $child->name )
			);
		}

		if ( ! empty( $items ) ) {
			return '<div class="ed-mp__subcats" data-ed-products-subcats="1">' . implode( '', $items ) . '</div>';
		}

		return '';
	}

	// If current term is a child => show siblings under parent, current = active
	if ( ! empty( $term->parent ) ) {
		$parent = get_term( (int) $term->parent, 'product_cat' );
		if ( $parent && ! is_wp_error( $parent ) ) {
			$siblings = get_terms([
				'taxonomy'   => 'product_cat',
				'parent'     => (int) $parent->term_id,
				'hide_empty' => true,
				'orderby'    => 'menu_order',
				'order'      => 'ASC',
			]);

			if ( ! is_wp_error( $siblings ) && ! empty( $siblings ) ) {
				$items       = [];
				$active_slug = $term->slug;

				foreach ( $siblings as $sibling ) {
					$url = trailingslashit( home_url( 'cat/' . $sibling->slug ) );

					$classes = [ 'ed-mp__subcat-link' ];
					if ( $sibling->slug === $active_slug ) {
						$classes[] = 'active';
					}

					$items[] = sprintf(
						'<a class="%s" href="%s" data-ed-subcat="%s">%s</a>',
						esc_attr( implode( ' ', $classes ) ),
						esc_url( $url ),
						esc_attr( $sibling->slug ),
						esc_html( $sibling->name )
					);
				}

				if ( ! empty( $items ) ) {
					return '<div class="ed-mp__subcats" data-ed-products-subcats="1" data-ed-parent-term="' . esc_attr( $parent->slug ) . '">' . implode( '', $items ) . '</div>';
				}
			}
		}
	}

	return '';
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'ed/v1', '/products', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'ed_rest_get_products_html',
		'permission_callback' => '__return_true',
		'args'                => [
			'term'     => [ 'required' => true ],
			'per_page' => [ 'default' => 12 ],
			'paged'    => [ 'default' => 1 ],
		],
	] );
} );

function ed_rest_get_products_html( \WP_REST_Request $req ) {
	$slug     = sanitize_title( $req->get_param( 'term' ) );
	$per_page = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ) );
	$paged    = max( 1, (int) $req->get_param( 'paged' ) );

	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		return new \WP_REST_Response([
			'html'         => '<p>' . esc_html__( 'קטגוריה לא נמצאה', 'deliz-short' ) . '</p>',
			'subcats_html' => '',
		], 404 );
	}

	$shortcode = sprintf(
		'[products category="%s" limit="%d" paginate="false" columns="2"]',
		esc_attr( $slug ),
		(int) $per_page
	);

	$response_data = [
		'term' => [
			'slug'   => $slug,
			'name'   => $term->name,
			'parent' => (int) $term->parent,
		],
		'html'         => do_shortcode( $shortcode ),
		'subcats_html' => ed_rest_get_subcats_html( $term ),
	];

	// Include cart fragments in AJAX response for sync
	if ( function_exists( 'WC' ) && WC()->cart ) {
		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', [] );
		if ( ! empty( $fragments ) ) {
			$response_data['fragments']     = $fragments;
			$response_data['fragment_hash'] = function_exists( 'wc_get_cart_hash' ) ? wc_get_cart_hash() : '';
		}
	}

	return new \WP_REST_Response( $response_data, 200 );
}

// קניה חוזרת
add_action( 'rest_api_init', function () {
	register_rest_route( 'ed/v1', '/rebuy-view', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => function () {
			if ( ! is_user_logged_in() ) {
				return new WP_REST_Response([
					'html'         => '<p>' . esc_html__( 'יש להתחבר כדי לצפות בהיסטוריית רכישה.', 'deliz-short' ) . '</p>',
					'subcats_html' => '',
				], 401 );
			}

			ob_start();

			$file = WP_CONTENT_DIR . '/themes/deliz-short/template-parts/product-history.php';
			if ( file_exists( $file ) ) {
				include $file;
			} else {
				echo '<p>' . esc_html__( 'קובץ תצוגה לא נמצא.', 'deliz-short' ) . '</p>';
			}

			$html = ob_get_clean();

			return new WP_REST_Response([
				'html'         => $html,
				'subcats_html' => '',
			], 200 );
		},
		'permission_callback' => '__return_true',
	] );
} );

function ed_rest_rebuy( \WP_REST_Request $req ) {
	$mode     = $req->get_param( 'mode' ) === 'last' ? 'last' : 'all';
	$per_page = max( 1, min( 48, (int) $req->get_param( 'per_page' ) ) );

	$user_id   = get_current_user_id();
	$cache_key = 'ed_rebuy_' . get_locale() . '_' . $user_id . '_' . $mode . '_' . $per_page;

	$fragments = [];
	if ( function_exists( 'WC' ) && WC()->cart ) {
		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', [] );
	}

	$cached = get_transient( $cache_key );
	if ( $cached && is_array( $cached ) ) {
		$cached['fragments']     = $fragments;
		$cached['fragment_hash'] = function_exists( 'wc_get_cart_hash' ) ? wc_get_cart_hash() : '';
		$cached['subcats_html']  = '';
		return new \WP_REST_Response( $cached, 200 );
	}

	$orders = wc_get_orders([
		'customer_id' => $user_id,
		'status'      => [ 'completed' ],
		'orderby'     => 'date',
		'order'       => 'DESC',
		'limit'       => ( $mode === 'all' ) ? 50 : 1,
		'return'      => 'objects',
	]);

	if ( empty( $orders ) ) {
		$payload = [
			'title'         => ( $mode === 'last' ) ? __( 'שחזור הזמנה קודמת', 'deliz-short' ) : __( 'מוצרים שקניתי', 'deliz-short' ),
			'html'          => '<p>' . esc_html__( 'לא נמצאו רכישות קודמות.', 'deliz-short' ) . '</p>',
			'count'         => 0,
			'subcats_html'  => '',
			'fragments'     => $fragments,
			'fragment_hash' => function_exists( 'wc_get_cart_hash' ) ? wc_get_cart_hash() : '',
		];
		$cache_payload = $payload;
		unset( $cache_payload['fragments'], $cache_payload['fragment_hash'] );
		set_transient( $cache_key, $cache_payload, 60 );
		return new \WP_REST_Response( $payload, 200 );
	}

	$ids = [];

	if ( $mode === 'last' ) {
		$last = $orders[0];
		foreach ( $last->get_items( 'line_item' ) as $item ) {
			$pid = (int) $item->get_product_id();
			if ( $pid ) {
				$ids[] = $pid;
			}
		}
		$ids = array_values( array_unique( $ids ) );
	} else {
		$seen = [];
		foreach ( $orders as $order ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$pid = (int) $item->get_product_id();
				if ( ! $pid || isset( $seen[ $pid ] ) ) {
					continue;
				}
				$seen[ $pid ] = true;
				$ids[]        = $pid;
			}
		}
	}

	if ( empty( $ids ) ) {
		$payload = [
			'title'         => ( $mode === 'last' ) ? __( 'שחזור הזמנה קודמת', 'deliz-short' ) : __( 'מוצרים שקניתי', 'deliz-short' ),
			'html'          => '<p>' . esc_html__( 'לא נמצאו מוצרים להצגה.', 'deliz-short' ) . '</p>',
			'count'         => 0,
			'subcats_html'  => '',
			'fragments'     => $fragments,
			'fragment_hash' => function_exists( 'wc_get_cart_hash' ) ? wc_get_cart_hash() : '',
		];
		$cache_payload = $payload;
		unset( $cache_payload['fragments'], $cache_payload['fragment_hash'] );
		set_transient( $cache_key, $cache_payload, 60 );
		return new \WP_REST_Response( $payload, 200 );
	}

	$ids = array_slice( $ids, 0, $per_page );

	$shortcode = sprintf(
		'[products ids="%s" orderby="post__in" columns="2" paginate="false"]',
		esc_attr( implode( ',', $ids ) )
	);

	$payload = [
		'title'         => ( $mode === 'last' ) ? __( 'שחזור הזמנה קודמת', 'deliz-short' ) : __( 'מוצרים שקניתי', 'deliz-short' ),
		'html'          => do_shortcode( $shortcode ),
		'count'         => count( $ids ),
		'subcats_html'  => '',
		'fragments'     => $fragments,
		'fragment_hash' => function_exists( 'wc_get_cart_hash' ) ? wc_get_cart_hash() : '',
	];

	$cache_payload = $payload;
	unset( $cache_payload['fragments'], $cache_payload['fragment_hash'] );
	set_transient( $cache_key, $cache_payload, 60 );

	return new \WP_REST_Response( $payload, 200 );
}

// ajax search
add_action( 'rest_api_init', function () {
	register_rest_route( 'ed/v1', '/product-search', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => [
			'q'        => [ 'required' => true ],
			'per_page' => [ 'required' => false ],
			'columns'  => [ 'required' => false ],
		],
		'callback'            => function ( WP_REST_Request $req ) {
			$q = trim( (string) $req->get_param( 'q' ) );
			if ( $q === '' ) {
				return new WP_REST_Response([
					'html'         => '',
					'count'        => 0,
					'subcats_html' => '',
				], 200 );
			}

			$per_page = max( 1, (int) $req->get_param( 'per_page' ) );
			$columns  = max( 1, (int) $req->get_param( 'columns' ) );
			if ( ! $columns ) {
				$columns = 2;
			}

			$loop = new WP_Query([
				'post_type'      => 'product',
				'post_status'    => 'publish',
				's'              => $q,
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'tax_query'      => [
					[
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => [ 'exclude-from-catalog' ],
						'operator' => 'NOT IN',
					],
				],
			]);

			ob_start();

			echo '<div class="woocommerce columns-' . (int) $columns . '">';

			if ( $loop->have_posts() ) {
				wc_get_template( 'loop/loop-start.php' );
				while ( $loop->have_posts() ) {
					$loop->the_post();
					wc_get_template_part( 'content', 'product' );
				}
				wc_get_template( 'loop/loop-end.php' );
			} else {
				echo '<p class="woocommerce-info">' . esc_html__( 'לא נמצאו מוצרים.', 'deliz-short' ) . '</p>';
			}

			echo '</div>';

			wp_reset_postdata();

			$response_data = [
				'html'         => ob_get_clean(),
				'count'        => (int) $loop->post_count,
				'subcats_html' => '',
			];

			if ( function_exists( 'WC' ) && WC()->cart ) {
				$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', [] );
				if ( ! empty( $fragments ) ) {
					$response_data['fragments']     = $fragments;
					$response_data['fragment_hash'] = function_exists( 'wc_get_cart_hash' ) ? wc_get_cart_hash() : '';
				}
			}

			return new WP_REST_Response( $response_data, 200 );
		}
	] );
} );

// Cart fragments
add_action( 'rest_api_init', function () {
	register_rest_route( 'ed/v1', '/cart-fragments', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
				return new \WP_REST_Response([
					'fragments'     => [],
					'fragment_hash' => '',
				], 200 );
			}

			$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', [] );

			return new \WP_REST_Response([
				'fragments'     => $fragments,
				'fragment_hash' => function_exists( 'wc_get_cart_hash' ) ? wc_get_cart_hash() : '',
			], 200 );
		}
	] );
} );

/**
 * ACF Options: product_note_label (הגדרות אתר › site-settings).
 *
 * @return \WP_REST_Response
 */
function ed_rest_get_product_note_label() {
	if ( function_exists( 'deliz_short_get_product_note_label' ) ) {
		$label = deliz_short_get_product_note_label();
	} else { 
		$label = __( 'הערה לקצב', 'deliz-short' );
	}

	return new \WP_REST_Response(
		[
			'product_note_label' => $label,
		],
		200
	);
}

/**
 * GET — קריאה (ציבורי). POST — עדכון השדה ב־ACF options (ציבורי; ללא אימות).
 *
 * @param \WP_REST_Request $request Request.
 * @return \WP_REST_Response|\WP_Error
 */
function ed_rest_product_note_label_dispatch( $request ) {
	if ( $request->get_method() === 'POST' ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new \WP_Error(
				'ed_rest_invalid_json',
				__( 'Invalid JSON body.', 'deliz-short' ),
				[ 'status' => 400 ]
			);
		}
		if ( ! array_key_exists( 'product_note_label', $params ) ) {
			return new \WP_Error(
				'ed_rest_missing_field',
				__( 'Missing product_note_label in body.', 'deliz-short' ),
				[ 'status' => 400 ]
			);
		}
		$raw = $params['product_note_label'];
		if ( is_array( $raw ) || is_object( $raw ) ) {
			return new \WP_Error(
				'ed_rest_invalid_type',
				__( 'product_note_label must be a string.', 'deliz-short' ),
				[ 'status' => 400 ]
			);
		}
		$label = sanitize_text_field( wp_unslash( (string) $raw ) );

		if ( ! function_exists( 'update_field' ) ) {
			return new \WP_Error(
				'ed_rest_acf_missing',
				__( 'ACF is not available.', 'deliz-short' ),
				[ 'status' => 503 ]
			);
		}

		update_field( 'product_note_label', $label, 'option' );

		$out = function_exists( 'deliz_short_get_product_note_label' )
			? deliz_short_get_product_note_label()
			: $label;

		return new \WP_REST_Response(
			[
				'success'            => true,
				'product_note_label' => $out,
			],
			200
		);
	}

	return ed_rest_get_product_note_label();
}

add_action( 'rest_api_init', function () {
	register_rest_route(
		'ed/v1',
		'/product-note-label',
		[
			'methods'             => [ WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ],
			'permission_callback' => '__return_true',
			'callback'            => 'ed_rest_product_note_label_dispatch',
		]
	);
} );