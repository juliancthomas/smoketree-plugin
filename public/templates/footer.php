<?php
/**
 * Custom Footer Template for Smoketree Plugin
 * 
 * This footer replaces the theme footer site-wide for public and member pages.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 * @since      1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<footer class="stsrc-footer">
	<div id="link-container" class="stsrc-footer-links">
		<div class="stsrc-footer-column">
			<h2 class="stsrc-footer-heading"><?php esc_html_e( 'Quick Links', 'smoketree-plugin' ); ?></h2>
			<ul class="stsrc-footer-list">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'smoketree-plugin' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/register' ) ); ?>"><?php esc_html_e( 'Register', 'smoketree-plugin' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/events' ) ); ?>"><?php esc_html_e( 'Events', 'smoketree-plugin' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/board' ) ); ?>"><?php esc_html_e( 'Board', 'smoketree-plugin' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/become-a-sponsor' ) ); ?>"><?php esc_html_e( 'Become a Sponsor', 'smoketree-plugin' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/pavilion-rental' ) ); ?>"><?php esc_html_e( 'Rent the Pavilion', 'smoketree-plugin' ); ?></a></li>
			</ul>
		</div>
		<div class="stsrc-footer-column">
			<h2 class="stsrc-footer-heading"><?php esc_html_e( 'Contact Us', 'smoketree-plugin' ); ?></h2>
			<ul class="stsrc-footer-list">
				<li>843 Arlington Dr</li>
				<li>Tucker, GA, 30084</li>
			</ul>
			<ul class="stsrc-footer-list">
				<li><a href="mailto:board@smoketree.us">board@smoketree.us</a></li>
			</ul>
		</div>
		<div class="stsrc-footer-column">
			<h2 class="stsrc-footer-heading"><?php esc_html_e( 'Support', 'smoketree-plugin' ); ?></h2>
			<ul class="stsrc-footer-list">
				<li><a href="<?php echo esc_url( home_url( '/wp-content/uploads/2025/03/Privacy-Policy-Smoketree.us_.pdf' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'smoketree-plugin' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/wp-content/uploads/2025/03/Terms-of-Service-Smoketree.us_.pdf' ) ); ?>"><?php esc_html_e( 'Terms of Service', 'smoketree-plugin' ); ?></a></li>
			</ul>
		</div>
	</div>
	<div class="stsrc-footer-credit">
		<?php esc_html_e( 'Website by Julian Thomas', 'smoketree-plugin' ); ?>
	</div>
</footer>

<?php wp_footer(); ?>

<style>
	.stsrc-footer {
		background-color: #f9fafb;
	}
	.stsrc-footer-links {
		max-width: 56rem;
		margin: 0 auto 100px;
		padding: 2rem 1rem;
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}
	
	@media (min-width: 768px) {
		.stsrc-footer-links {
			flex-direction: row;
			gap: 4rem;
		}
	}
	
	.stsrc-footer-column {
		flex: 1;
	}
	
	.stsrc-footer-heading {
		text-align: center;
		font-size: 1.125rem;
		font-weight: 700;
		color: #1f2937;
		margin: 0 0 0.5rem 0;
	}
	
	.stsrc-footer-list {
		list-style: none;
		padding: 0;
		margin: 0 0 1rem 0;
		text-align: center;
		color: #374151;
	}
	
	.stsrc-footer-list li {
		font-size: 0.875rem;
		margin: 0.25rem 0;
	}
	
	.stsrc-footer-list a {
		color: inherit;
		text-decoration: none;
		transition: opacity 0.2s ease;
	}
	
	.stsrc-footer-list a:hover {
		opacity: 0.7;
	}
	
	.stsrc-footer-credit {
		text-align: center;
		font-size: 0.75rem;
		color: #6b7280;
		padding: 1rem;
		margin-top: 1rem;
	}
</style>

</body>
</html>
