<?php
/**
 * The Signal Newsletter Section Partial
 *
 * Uses ACF repeater field 'news_and_announcements' on page ID 1032.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials/front-page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! have_rows( 'news_and_announcements', 1032 ) ) {
	return;
}
?>

<section id="signal_newsletter" class="bg-[#f3f4f6] dark:bg-[#1f2937] px-6 py-16">
	<div class="max-w-3xl mx-auto text-center">
		<h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-4">The Signal Newsletter</h2>
		<p class="text-gray-600 dark:text-gray-300 mb-8">
			Stay updated with our monthly newsletter. Read the latest issue or browse past editions.
		</p>

		<?php the_row(); $pdf = get_sub_field( 'pdf_upload' ); ?>
		<?php if ( $pdf ) : ?>
			<a href="<?php echo esc_url( $pdf['url'] ); ?>" target="_blank" class="inline-block bg-[#538f85] hover:bg-[#3c6b65] text-white font-semibold px-6 py-3 rounded transition">
				View Latest Issue (PDF)
			</a>
		<?php endif; ?>
	</div>

	<div class="max-w-2xl mx-auto mt-12 border-t border-gray-300 dark:border-gray-600 pt-8">
		<h3 class="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">Previous Issues</h3>
		<ul class="space-y-3 text-left">
			<?php while ( have_rows( 'news_and_announcements', 1032 ) ) : the_row(); $pdf = get_sub_field( 'pdf_upload' ); ?>
				<?php if ( $pdf ) : ?>
					<li>
						<a href="<?php echo esc_url( $pdf['url'] ); ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
							<?php the_sub_field( 'news_title' ); ?>
						</a>
					</li>
				<?php endif; ?>
			<?php endwhile; ?>
		</ul>
	</div>
</section>
