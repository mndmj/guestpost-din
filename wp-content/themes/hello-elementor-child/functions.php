<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gpm_enqueue_child_styles() {
	$theme_dir = get_stylesheet_directory();
	$theme_uri = get_stylesheet_directory_uri();

	$global_path = $theme_dir . '/style.css';

	wp_enqueue_style(
		'gpm-child-style',
		get_stylesheet_uri(),
		array( 'hello-elementor-theme-style' ),
		filemtime( $global_path )
	);

	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$file = $theme_dir . '/assets/css/my-account.css';

		if ( file_exists( $file ) ) {
			wp_enqueue_style(
				'gpm-my-account-style',
				$theme_uri . '/assets/css/my-account.css',
				array( 'gpm-child-style' ),
				filemtime( $file )
			);
		}
	}

	if ( is_front_page() ) {
		$file = $theme_dir . '/assets/css/front-page.css';

		if ( file_exists( $file ) ) {
			wp_enqueue_style(
				'gpm-front-page-style',
				$theme_uri . '/assets/css/front-page.css',
				array( 'gpm-child-style' ),
				filemtime( $file )
			);
		}
	}

	if ( function_exists( 'is_cart' ) && is_cart() ) {
		$file = $theme_dir . '/assets/css/cart.css';

		if ( file_exists( $file ) ) {
			wp_enqueue_style(
				'gpm-cart-style',
				$theme_uri . '/assets/css/cart.css',
				array( 'gpm-child-style' ),
				filemtime( $file )
			);
		}
	}

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$file = $theme_dir . '/assets/css/shop.css';

		if ( file_exists( $file ) ) {
			wp_enqueue_style(
				'gpm-shop-style',
				$theme_uri . '/assets/css/shop.css',
				array( 'gpm-child-style' ),
				filemtime( $file )
			);
		}

		wp_enqueue_script(
			'gpm-shop-cart',
			$theme_uri . '/assets/js/shop-cart.js',
			array(),
			filemtime( $theme_dir . '/assets/js/shop-cart.js' ),
			true
		);
	}

	if ( function_exists( 'is_product' ) && is_product() ) {
		$file = $theme_dir . '/assets/css/single-product.css';

		if ( file_exists( $file ) ) {
			wp_enqueue_style(
				'gpm-single-product-style',
				$theme_uri . '/assets/css/single-product.css',
				array( 'gpm-child-style' ),
				filemtime( $file )
			);
		}
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		$file = $theme_dir . '/assets/css/checkout.css';

		if ( file_exists( $file ) ) {
			wp_enqueue_style(
				'gpm-checkout-style',
				$theme_uri . '/assets/css/checkout.css',
				array( 'gpm-child-style' ),
				filemtime( $file )
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'gpm_enqueue_child_styles', 20 );

/**
 * Shared authentication styles for WooCommerce and the WordPress login page.
 */
function gpm_enqueue_auth_styles() {
	$file = get_stylesheet_directory() . '/assets/css/auth.css';

	if ( ! file_exists( $file ) ) {
		return;
	}

	wp_enqueue_style(
		'gpm-auth-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Unbounded:wght@700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'gpm-auth-style',
		get_stylesheet_directory_uri() . '/assets/css/auth.css',
		array( 'gpm-auth-fonts' ),
		filemtime( $file )
	);
}
add_action( 'wp_enqueue_scripts', 'gpm_enqueue_auth_styles', 30 );
add_action( 'login_enqueue_scripts', 'gpm_enqueue_auth_styles', 30 );

/**
 * Render kartu harga dari produk WooCommerce yang dipilih.
 */
function gpm_render_pricing_cards_shortcode( $attributes ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}

	$attributes = shortcode_atts(
		array(
			'ids' => '',
		),
		$attributes,
		'gpm_pricing_cards'
	);

	$product_ids = array_filter(
		array_map( 'absint', explode( ',', $attributes['ids'] ) )
	);

	if ( empty( $product_ids ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="gpm-pricing-cards">
		<?php foreach ( $product_ids as $product_id ) : ?>
			<?php
			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}

			$is_available = $product->is_purchasable() && $product->is_in_stock();
			$button_url = $is_available ? $product->add_to_cart_url() : $product->get_permalink();
			$is_shop_add = is_shop() && $is_available && $product->is_type( 'simple' );

			if ( $is_shop_add ) {
				$button_url = add_query_arg(
					array( 'add-to-cart' => $product_id, 'gpm_shop_cart' => '1' ),
					wc_get_page_permalink( 'shop' )
				);
			}

			$billing = $product->get_attribute( 'billing' );

			if ( ! $billing ) {
				$billing = $product->get_attribute( 'pa_billing' );
			}

			$show_featured_style = ! (
				( function_exists( 'is_shop' ) && is_shop() ) ||
				( function_exists( 'is_cart' ) && is_cart() )
			);
			?>
			<article
				class="gpm-pricing-card <?php echo $show_featured_style && $product->is_featured() ? 'gpm-pricing-card--featured' : ''; ?>">
				<?php if ( $show_featured_style && $product->is_featured() ) : ?>
					<div class="gpm-pricing-card__badge">MOST POPULAR</div>
				<?php endif; ?>
				<p class="gpm-pricing-card__name">
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="gpm-pricing-card__detail-link">
						<?php echo esc_html( $product->get_name() ); ?>
					</a>
				</p>
				<div class="gpm-pricing-card__price">
					<?php echo wp_kses_post( $product->get_price_html() ); ?>
					<?php if ( $billing ) : ?>
						<span class="gpm-pricing-card__billing">/<?php echo esc_html( $billing ); ?></span>
					<?php endif; ?>
				</div>
				<div class="gpm-pricing-card__description">
					<?php echo wp_kses_post( wc_format_content( $product->get_short_description() ) ); ?>
				</div>
				<?php
				$button_text = ( function_exists( 'is_shop' ) && is_shop() )
					? 'ADD TO CART'
					: 'GET STARTED';
				?>

				<a href="<?php echo esc_url( $button_url ); ?>" class="gpm-pricing-card__button-link" <?php if ( $is_shop_add ) : ?>data-gpm_shop_cart="1" <?php endif; ?>
					aria-label="<?php echo esc_attr( $button_text . ': ' . $product->get_name() ); ?>">
					<div class="gpm-pricing-card__button">
						<?php echo esc_html( $button_text ); ?>
					</div>
				</a>
			</article>
		<?php endforeach; ?>
	</div>
	<?php

	return ob_get_clean();
}
add_shortcode( 'gpm_pricing_cards', 'gpm_render_pricing_cards_shortcode' );

function gpm_is_shop_cart_request() {
	return '1' === ( $_POST['gpm_shop_cart'] ?? $_GET['gpm_shop_cart'] ?? '' );
}

function gpm_shop_cart_is_pending() {
	return function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty()
		&& ( gpm_is_shop_cart_request() || '1' === ( $_GET['gpm_cart_pending'] ?? '' ) );
}

function gpm_validate_shop_cart_add( $passed ) {
	if ( ! gpm_is_shop_cart_request() || ! gpm_shop_cart_is_pending() ) {
		return $passed;
	}

	wc_add_notice(
		esc_html__( 'Your cart already contains a product. Please complete your current purchase before adding another product.', 'guest-post-child' )
		. ' <a href="' . esc_url( wc_get_cart_url() ) . '">'
		. esc_html__( 'Go to Cart', 'guest-post-child' ) . '</a>',
		'error'
	);

	return false;
}
add_filter( 'woocommerce_add_to_cart_validation', 'gpm_validate_shop_cart_add', 100 );

function gpm_shop_cart_redirect( $url ) {
	return gpm_is_shop_cart_request() ? wc_get_cart_url() : $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'gpm_shop_cart_redirect' );

function gpm_shop_cart_error_redirect( $url ) {
	return gpm_is_shop_cart_request() && gpm_shop_cart_is_pending()
		? add_query_arg( 'gpm_cart_pending', '1', wc_get_page_permalink( 'shop' ) )
		: $url;
}
add_filter( 'woocommerce_cart_redirect_after_error', 'gpm_shop_cart_error_redirect' );

function gpm_shop_cart_script_data( $data, $handle ) {
	if ( 'wc-add-to-cart' === $handle && is_shop() && is_array( $data ) ) {
		$data['cart_redirect_after_add'] = 'yes';
		$data['cart_url'] = wc_get_cart_url();
	}

	return $data;
}
add_filter( 'woocommerce_get_script_data', 'gpm_shop_cart_script_data', 10, 2 );

function gpm_render_shop_cart_modal() {
	if ( ! function_exists( 'is_shop' ) || ! is_shop() || ! gpm_shop_cart_is_pending() ) {
		return;
	}
	?>
	<dialog id="gpm-shop-cart-modal" class="gpm-shop-cart-modal" aria-labelledby="gpm-shop-cart-title"
		aria-describedby="gpm-shop-cart-description">
		<form method="dialog" class="gpm-shop-cart-modal__close-form">
			<button class="gpm-shop-cart-modal__close"
				aria-label="<?php esc_attr_e( 'Close reminder', 'guest-post-child' ); ?>">&times;</button>
		</form>
		<h2 id="gpm-shop-cart-title"><?php esc_html_e( 'Complete your current purchase', 'guest-post-child' ); ?></h2>
		<p id="gpm-shop-cart-description">
			<?php esc_html_e( 'Your cart already contains a product. Please complete your current purchase before adding another product.', 'guest-post-child' ); ?>
		</p>
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="gpm-shop-cart-modal__link" autofocus>
			<div class="gpm-shop-cart-modal__button"><?php esc_html_e( 'Go to Cart', 'guest-post-child' ); ?></div>
		</a>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'gpm_render_shop_cart_modal', 10 );

/**
 * Gunakan pricing card yang ada, dengan AJAX Add to Cart
 * untuk produk yang mendukungnya.
 */
function gpm_catalog_pricing_html( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$html = gpm_render_pricing_cards_shortcode(
		array( 'ids' => $product->get_id() )
	);

	if (
		! $product->is_purchasable() ||
		! $product->is_in_stock() ||
		! $product->supports( 'ajax_add_to_cart' )
	) {
		return $html;
	}

	$tags = new WP_HTML_Tag_Processor( $html );

	if (
		$tags->next_tag(
			array(
				'tag_name' => 'A',
				'class_name' => 'gpm-pricing-card__button-link',
			)
		)
	) {
		$tags->add_class( 'add_to_cart_button' );
		$tags->add_class( 'ajax_add_to_cart' );
		$tags->set_attribute( 'data-product_id', $product->get_id() );
		$tags->set_attribute( 'data-product_sku', $product->get_sku() );
		$tags->set_attribute( 'data-quantity', '1' );
		$tags->set_attribute( 'rel', 'nofollow' );
		$tags->set_attribute(
			'data-success_message',
			sprintf( '%s has been added to your cart.', $product->get_name() )
		);
	}

	return $tags->get_updated_html();
}

/**
 * Pastikan AJAX Add to Cart tersedia pada kedua halaman.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_cart() ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
		}
	},
	30
);

/**
 * Shop: ganti isi kartu bawaan, tanpa mengubah query,
 * pengurutan, atau pagination WooCommerce.
 */
add_action(
	'wp',
	function () {
		if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
			return;
		}

		remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
		remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

		add_action(
			'woocommerce_before_shop_loop_item',
			function () {
				global $product;

				echo gpm_catalog_pricing_html( $product );
			}
		);
	}
);

/**
 * Markup produk rekomendasi di Empty Cart.
 */
function gpm_empty_cart_pricing_item( $html, $data, $product ) {
	if ( ! $product instanceof WC_Product ) {
		return $html;
	}

	$card = gpm_catalog_pricing_html( $product );

	return $card
		? '<li class="wc-block-grid__product gpm-catalog-product">' . $card . '</li>'
		: '';
}

/**
 * Aktifkan penggantian kartu hanya saat isi Empty Cart dirender.
 * Tidak memeriksa cart kosong agar tetap bekerja ketika cart
 * menjadi kosong melalui interaksi di halaman.
 */
add_filter(
	'pre_render_block',
	function ( $pre_render, $block ) {
		if (
			null === $pre_render &&
			function_exists( 'is_cart' ) &&
			is_cart() &&
			'woocommerce/empty-cart-block' === ( $block['blockName'] ?? '' )
		) {
			add_filter(
				'woocommerce_blocks_product_grid_item_html',
				'gpm_empty_cart_pricing_item',
				10,
				3
			);
		}

		return $pre_render;
	},
	10,
	2
);

add_filter(
	'render_block_woocommerce/empty-cart-block',
	function ( $content ) {
		remove_filter(
			'woocommerce_blocks_product_grid_item_html',
			'gpm_empty_cart_pricing_item',
			10
		);

		return $content;
	}
);

/**
 * Register halaman /my-account/notifications/.
 */
function gpm_register_notifications_endpoint() {
	add_rewrite_endpoint( 'notifications', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'gpm_register_notifications_endpoint' );

/**
 * Kenalkan endpoint kepada WooCommerce.
 */
function gpm_add_notifications_query_var( $query_vars ) {
	$query_vars['notifications'] = 'notifications';

	return $query_vars;
}
add_filter(
	'woocommerce_get_query_vars',
	'gpm_add_notifications_query_var'
);

/**
 * Tambahkan menu setelah Orders.
 */
function gpm_add_notifications_menu_item( $items ) {
	$new_items = array();

	foreach ( $items as $endpoint => $label ) {
		$new_items[ $endpoint ] = $label;

		if ( 'orders' === $endpoint ) {
			$new_items['notifications'] = __(
				'Notifications',
				'guest-post-child'
			);
		}
	}

	return $new_items;
}
add_filter(
	'woocommerce_account_menu_items',
	'gpm_add_notifications_menu_item'
);

/**
 * Ambil Customer Notes dari order milik customer.
 */
function gpm_get_customer_notifications( $customer_id, $limit = 0 ) {
	$customer_id = absint( $customer_id );
	$limit = absint( $limit );

	if ( ! $customer_id ) {
		return array();
	}

	$order_ids = wc_get_orders(
		array(
			'customer_id' => $customer_id,
			'limit' => -1,
			'return' => 'ids',
		)
	);

	if ( ! $order_ids ) {
		return array();
	}

	$note_query = array(
		'order__in' => array_map( 'absint', $order_ids ),
		'type' => 'customer',
		'orderby' => 'date_created_gmt',
		'order' => 'DESC',
	);

	if ( $limit ) {
		$note_query['limit'] = $limit;
	}

	$notes = wc_get_order_notes( $note_query );
	$notifications = array();

	foreach ( $notes as $note ) {
		$order = wc_get_order( $note->order_id );

		if (
			! $order ||
			absint( $order->get_user_id() ) !== $customer_id
		) {
			continue;
		}

		$notifications[] = array(
			'id' => absint( $note->id ),
			'content' => (string) $note->content,
			'date_created' => $note->date_created,
			'order_id' => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'order_url' => $order->get_view_order_url(),
		);
	}

	return $notifications;
}

/**
 * Render daftar notifikasi untuk Dashboard dan halaman Notifications.
 */
function gpm_render_notification_list( $notifications ) {
	if ( ! $notifications ) {
		?>
		<p class="gpm-panel-empty">
			<?php esc_html_e( 'No notifications yet.', 'guest-post-child' ); ?>
		</p>
		<?php
		return;
	}
	?>

	<div class="gpm-notification-list">
		<?php foreach ( $notifications as $notification ) : ?>
			<?php
			$date = $notification['date_created'];
			?>

			<article class="gpm-notification-item" data-notification-id="<?php echo esc_attr( $notification['id'] ); ?>">
				<div class="gpm-notification-item__content">
					<div class="gpm-notification-item__message">
						<?php
						echo wp_kses_post(
							wpautop( $notification['content'] )
						);
						?>
					</div>

					<p class="gpm-notification-item__meta">
						Order #<?php echo esc_html( $notification['order_number'] ); ?>

						<?php if ( $date instanceof WC_DateTime ) : ?>
							&bull;

							<time datetime="<?php echo esc_attr( $date->date( 'c' ) ); ?>">
								<?php
								echo esc_html(
									wc_format_datetime(
										$date,
										get_option( 'date_format' ) .
										' ' .
										get_option( 'time_format' )
									)
								);
								?>
							</time>
						<?php endif; ?>
					</p>
				</div>

				<a data-notification-order href="<?php echo esc_url( $notification['order_url'] ); ?>">
					<div class="gpm-notification-item__action">View Order</div>
				</a>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Render halaman seluruh notifikasi.
 */
function gpm_render_notifications_endpoint( $current_page = 1 ) {
	$current_page = max( 1, absint( $current_page ) );
	$per_page = 10;

	$notifications = gpm_get_customer_notifications(
		get_current_user_id()
	);

	$total = count( $notifications );
	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$current_page = min( $current_page, $total_pages );

	$page_notifications = array_slice(
		$notifications,
		( $current_page - 1 ) * $per_page,
		$per_page
	);
	?>

	<section class="gpm-notifications-page" aria-labelledby="gpm-notifications-page-title">
		<header class="gpm-panel-header">
			<div>
				<span class="gpm-panel-header__eyebrow">
					ACCOUNT UPDATES
				</span>

				<h2 id="gpm-notifications-page-title">
					Notifications
				</h2>
			</div>
		</header>

		<?php gpm_render_notification_list( $page_notifications ); ?>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="gpm-notification-pagination"
				aria-label="<?php esc_attr_e( 'Notification pages', 'guest-post-child' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base' => wc_get_endpoint_url(
								'notifications',
								'%#%',
								wc_get_page_permalink( 'myaccount' )
							),
							'format' => '',
							'current' => $current_page,
							'total' => $total_pages,
							'type' => 'list',
							'prev_text' => '&larr; Previous',
							'next_text' => 'Next &rarr;',
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</section>
	<?php
}
add_action(
	'woocommerce_account_notifications_endpoint',
	'gpm_render_notifications_endpoint',
	10,
	1
);

/**
 * Membuat markup ikon cart beserta badge jumlah produk.
 */
function gpm_nav_cart_link_markup() {
	$cart = function_exists( 'WC' ) ? WC()->cart : null;
	$count = $cart ? $cart->get_cart_contents_count() : 0;

	$label = sprintf(
		_n(
			'%d product in cart',
			'%d products in cart',
			$count,
			'guest-post-child'
		),
		$count
	);

	return sprintf(
		'<a class="elementor-icon elementor-animation-grow gpm-nav-cart-link"
			href="%1$s"
			aria-label="%2$s">
			<i aria-hidden="true" class="jki jki-shopping-cart-solid"></i>
			<span class="gpm-nav-cart-count%3$s" aria-hidden="true">%4$s</span>
		</a>',
		esc_url( wc_get_cart_url() ),
		esc_attr( $label ),
		0 === $count ? ' is-empty' : '',
		$count > 0 ? esc_html( (string) $count ) : ''
	);
}

/**
 * Menambahkan badge pada widget cart Elementor Navbar.
 */
function gpm_render_nav_cart_count( $widget_content, $widget ) {
	if (
		'ecced6d' !== $widget->get_id() ||
		! function_exists( 'WC' )
	) {
		return $widget_content;
	}

	wp_enqueue_script( 'wc-cart-fragments' );

	return '<div class="elementor-icon-wrapper">'
		. gpm_nav_cart_link_markup()
		. '</div>';
}
add_filter(
	'elementor/widget/render_content',
	'gpm_render_nav_cart_count',
	10,
	2
);

/**
 * Memperbarui badge setelah produk dimasukkan ke cart melalui AJAX.
 */
function gpm_refresh_nav_cart_count( $fragments ) {
	if ( function_exists( 'WC' ) ) {
		$fragments['a.gpm-nav-cart-link'] = gpm_nav_cart_link_markup();
	}

	return $fragments;
}
add_filter(
	'woocommerce_add_to_cart_fragments',
	'gpm_refresh_nav_cart_count'
);

add_filter( 'gettext_woocommerce', function ( $translated, $text ) {
	if (
		'Note:' === $text &&
		function_exists( 'is_wc_endpoint_url' ) &&
		( is_wc_endpoint_url( 'view-order' ) || is_wc_endpoint_url( 'order-received' ) )
	) {
		return 'Heading Post:';
	}

	return $translated;
}, 20, 2 );
?>