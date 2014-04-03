<?php
ttf_one_load_section_header();
global $ttf_one_section_data, $ttf_one_is_js_template;
$section_name = ttf_one_get_section_name( $ttf_one_section_data, $ttf_one_is_js_template );
$section_order = ( ! empty( $ttf_one_section_data['data']['slide-order'] ) ) ? $ttf_one_section_data['data']['slide-order'] : array();
?>

<div class="ttf-one-add-slide-wrapper">
	<a href="#" class="button button-small ttf-one-add-slide"><?php _e( 'Add Slide', 'ttf-one' ); ?></a>
</div>

<div class="ttf-one-banner-options">
	<p>
		<input type="checkbox" name="<?php echo $section_name; ?>[display-arrows]" value="1" />
		<label>
			<?php _e( 'Display navigation arrows', 'ttf-one' ); ?>
		</label>
		<input type="checkbox" name="<?php echo $section_name; ?>[display-dots]" value="1" />
		<label>
			<?php _e( 'Display navigation dots', 'ttf-one' ); ?>
		</label>
	</p>
</div>

<div class="ttf-one-banner-slides">
	<div class="ttf-one-banner-slides-stage">
		<?php foreach ( $section_order as $key => $section_id  ) : ?>
			<?php if ( isset( $ttf_one_section_data['data']['banner-slides'][ $section_id ] ) ) : ?>
				<?php global $ttf_one_slide_id; $ttf_one_slide_id = $section_id; ?>
				<?php get_template_part( '/inc/builder/sections/builder-templates/banner', 'slide' ); ?>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<input type="hidden" value="<?php echo esc_attr( implode( ',', $section_order ) ); ?>" name="<?php echo $section_name; ?>[banner-slide-order]" class="ttf-one-banner-slide-order" />
</div>

<input type="hidden" class="ttf-one-section-state" name="<?php echo $section_name; ?>[state]" value="<?php if ( isset( $ttf_one_section_data['data']['state'] ) ) echo esc_attr( $ttf_one_section_data['data']['state'] ); else echo 'open'; ?>" />
<?php ttf_one_load_section_footer(); ?>