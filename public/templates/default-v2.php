<?php
/**
 * Template Name: Smoketree Default V2
 *
 * General-purpose page template that renders standard WordPress (Classic Editor)
 * content inside the Smoketree plugin chrome (header / footer).
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/templates
 * @since      1.5.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin header.
require_once plugin_dir_path( __FILE__ ) . 'header.php';
?>

<main class="stsrc-default-v2">
	<article class="stsrc-default-v2__content">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<h1 class="stsrc-default-v2__title"><?php the_title(); ?></h1>

			<div class="stsrc-default-v2__body entry-content">
				<?php the_content(); ?>
			</div>
			<?php
		endwhile;
		?>
	</article>
</main>

<style>
	.stsrc-default-v2 {
		max-width: 56rem;
		margin: 0 auto;
		padding: 2rem 1rem 4rem;
	}

	.stsrc-default-v2__title {
		font-size: 2rem;
		font-weight: 700;
		color: #1f2937;
		margin: 0 0 1.5rem;
		line-height: 1.25;
	}

	@media (min-width: 768px) {
		.stsrc-default-v2__title {
			font-size: 2.5rem;
		}
	}

	/* Prose-like defaults for Classic Editor output */
	.stsrc-default-v2__body {
		color: #374151;
		font-size: 1rem;
		line-height: 1.75;
	}

	.stsrc-default-v2__body h2 {
		font-size: 1.5rem;
		font-weight: 700;
		color: #1f2937;
		margin: 2rem 0 0.75rem;
	}

	.stsrc-default-v2__body h3 {
		font-size: 1.25rem;
		font-weight: 600;
		color: #1f2937;
		margin: 1.75rem 0 0.5rem;
	}

	.stsrc-default-v2__body h4,
	.stsrc-default-v2__body h5,
	.stsrc-default-v2__body h6 {
		font-size: 1.1rem;
		font-weight: 600;
		color: #1f2937;
		margin: 1.5rem 0 0.5rem;
	}

	.stsrc-default-v2__body p {
		margin: 0 0 1.25rem;
	}

	.stsrc-default-v2__body a {
		color: #059669;
		text-decoration: underline;
	}

	.stsrc-default-v2__body a:hover {
		color: #047857;
	}

	.stsrc-default-v2__body ul,
	.stsrc-default-v2__body ol {
		margin: 0 0 1.25rem 1.5rem;
		padding: 0;
	}

	.stsrc-default-v2__body li {
		margin-bottom: 0.25rem;
	}

	.stsrc-default-v2__body blockquote {
		border-left: 4px solid #d1d5db;
		margin: 1.5rem 0;
		padding: 0.75rem 1.25rem;
		color: #4b5563;
		font-style: italic;
	}

	.stsrc-default-v2__body img {
		max-width: 100%;
		height: auto;
		border-radius: 0.5rem;
		margin: 1rem 0;
	}

	.stsrc-default-v2__body table {
		width: 100%;
		border-collapse: collapse;
		margin: 1.5rem 0;
	}

	.stsrc-default-v2__body th,
	.stsrc-default-v2__body td {
		border: 1px solid #e5e7eb;
		padding: 0.5rem 0.75rem;
		text-align: left;
	}

	.stsrc-default-v2__body th {
		background-color: #f3f4f6;
		font-weight: 600;
	}

	.stsrc-default-v2__body hr {
		border: none;
		border-top: 1px solid #e5e7eb;
		margin: 2rem 0;
	}

	.stsrc-default-v2__body pre {
		background-color: #f3f4f6;
		border-radius: 0.375rem;
		padding: 1rem;
		overflow-x: auto;
		margin: 1.25rem 0;
		font-size: 0.875rem;
	}

	.stsrc-default-v2__body code {
		background-color: #f3f4f6;
		padding: 0.125rem 0.25rem;
		border-radius: 0.25rem;
		font-size: 0.875em;
	}

	.stsrc-default-v2__body pre code {
		background: none;
		padding: 0;
	}

	/* WordPress alignment classes */
	.stsrc-default-v2__body .aligncenter {
		display: block;
		margin-left: auto;
		margin-right: auto;
	}

	.stsrc-default-v2__body .alignleft {
		float: left;
		margin: 0.5rem 1.5rem 1rem 0;
	}

	.stsrc-default-v2__body .alignright {
		float: right;
		margin: 0.5rem 0 1rem 1.5rem;
	}

	.stsrc-default-v2__body .wp-caption {
		max-width: 100%;
	}

	.stsrc-default-v2__body .wp-caption-text {
		font-size: 0.875rem;
		color: #6b7280;
		text-align: center;
		margin-top: 0.5rem;
	}
</style>

<?php
// Load plugin footer.
require_once plugin_dir_path( __FILE__ ) . 'footer.php';
