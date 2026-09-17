<?php
/** Lost in Links: inherits the theme header, footer and WordPress 404 response. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
?>
<main id="content" class="site-main gpm-404" aria-labelledby="gpm-404-title" data-motion="paused">
	<div class="gpm-404__art" aria-hidden="true">
		<div class="gpm-404__scene">
			<div class="gpm-404__number">
				<span class="gpm-404__digit">4</span><span class="gpm-404__digit">0</span><span class="gpm-404__digit">4</span>
			</div>
			<svg class="gpm-404__shape gpm-404__asterisk" viewBox="0 0 100 100" focusable="false"><path d="M40 5h20v28l24-14 11 19-25 13 25 14-11 18-24-14v27H40V69L16 83 5 65l25-14L5 38l11-19 24 14Z"/></svg>
			<svg class="gpm-404__shape gpm-404__link" viewBox="0 0 100 100" fill="none" focusable="false"><path d="M43 39 61 21a18 18 0 0 1 26 26L68 66a18 18 0 0 1-26-1l10-10a5 5 0 0 0 7 1l19-19a5 5 0 0 0-7-7L53 48Z" fill="white"/><path d="M57 61 39 79a18 18 0 0 1-26-26l19-19a18 18 0 0 1 26 1L48 45a5 5 0 0 0-7-1L22 63a5 5 0 0 0 7 7l18-18Z" fill="currentColor"/><path d="m16 12-3-9M5 29l-9-3"/></svg>
			<svg class="gpm-404__shape gpm-404__triangle" viewBox="0 0 70 70" focusable="false"><path d="m6 28 55-19-9 54Z"/></svg>
			<span class="gpm-404__shape gpm-404__circle"></span>
			<span class="gpm-404__shape gpm-404__square"></span>
		</div>
	</div>
	<div class="gpm-404__copy">
		<h1 id="gpm-404-title"><span class="gpm-404__sr-only">404 — </span><?php esc_html_e( 'Oops! This link went off the grid.', 'guest-post-child' ); ?></h1>
		<p><?php esc_html_e( 'The page you are looking for may have moved or no longer exists. Let’s get you back on track.', 'guest-post-child' ); ?></p>
	</div>
	<div class="gpm-404__actions">
		<a class="gpm-404__button gpm-404__button--home" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 12H4m7-7-7 7 7 7"/></svg>
			<?php esc_html_e( 'Back to Home', 'guest-post-child' ); ?>
		</a>
		<a class="gpm-404__button" href="<?php echo esc_url( $account_url ); ?>">
			<?php esc_html_e( 'My Account', 'guest-post-child' ); ?>
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 12h16m-7-7 7 7-7 7"/></svg>
		</a>
	</div>
	<form class="gpm-404__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label for="gpm-404-search"><?php esc_html_e( 'Search the site', 'guest-post-child' ); ?></label>
		<div class="gpm-404__search-row">
			<input id="gpm-404-search" type="search" name="s" required placeholder="<?php esc_attr_e( 'What are you looking for?', 'guest-post-child' ); ?>">
			<button type="submit" aria-label="<?php esc_attr_e( 'Search', 'guest-post-child' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="7"/><path d="m15 15 6 6"/></svg></button>
		</div>
	</form>
	<button class="gpm-404__motion" type="button" aria-pressed="false" hidden
		data-pause="<?php esc_attr_e( 'Pause animation', 'guest-post-child' ); ?>"
		data-resume="<?php esc_attr_e( 'Resume animation', 'guest-post-child' ); ?>"
		data-reduced="<?php esc_attr_e( 'Motion reduced', 'guest-post-child' ); ?>">
		<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="gpm-404__pause-icon" d="M8 4v16M16 4v16"/><path class="gpm-404__play-icon" d="m8 4 12 8-12 8Z"/></svg>
		<span><?php esc_html_e( 'Pause animation', 'guest-post-child' ); ?></span>
	</button>
</main>
