<?php
/**
 * Upgrade to Pro Banner
 *
 * @package jeg-elementor-kit
 */

?>
<div class="notice jkit-upgrade-banner">
	<button class="jkit-btn-close-button">✕</button>
	<div class="jkit-banner-content">
		<h1>Unlock More With <span class="jkit-pro-text-highlight">JEG KIT PRO!</span></h1>
		<p>Empowering you to build a website that truly stands out with advanced features and seamless
			integration</p>
		<div class="jkit-banner-cta-button-wrapper">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jkit&utm_source=jeg-elementor-kit&utm_medium=adminbanner' ) ); ?>" class="jkit-banner-cta-button">
				Upgrade to PRO
				<svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M1.5 9.82373L0 1.57373L4.125 5.32373L6.75 0.82373L9.375 5.32373L13.5 1.57373L12 9.82373H1.5ZM12 12.0737C12 12.5237 11.7 12.8237 11.25 12.8237H2.25C1.8 12.8237 1.5 12.5237 1.5 12.0737V11.3237H12V12.0737Z" fill="white" />
				</svg>
			</a>
		</div>
	</div>
	<img src="<?php echo esc_url( JEG_ELEMENTOR_KIT_URL . '/assets/img/admin/banner/arrow.png' ); ?>" alt="Arrow" class="jkit-banner-arrow" />
	<img src="<?php echo esc_url( JEG_ELEMENTOR_KIT_URL . '/assets/img/admin/banner/pro-banner-images-banner.png' ); ?>" alt="Arrow" class="jkit-banner-hero" />
</div>
