<?php
/**
 * Block patterns.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

/**
 * Starting points for home and landing pages, made of EstateCraft and core blocks.
 */
final class Patterns {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'register_patterns' ), 20 );
	}

	/**
	 * Register the category and patterns.
	 */
	public static function register_patterns() {
		register_block_pattern_category( 'estatecraft', array( 'label' => __( 'Real estate', 'wp-smart-property' ) ) );
		foreach ( self::all() as $slug => $pattern ) {
			register_block_pattern(
				'wp-smart-property/' . $slug,
				array_merge(
					array(
						'categories' => array( 'estatecraft' ),
					),
					$pattern
				)
			);
		}
	}

	/**
	 * Heading block markup.
	 *
	 * @param string $text  Text.
	 * @param int    $level Level.
	 * @param string $align Text alignment.
	 * @return string
	 */
	private static function heading( $text, $level = 2, $align = '' ) {
		$attrs = 2 === $level ? array() : array( 'level' => $level );
		if ( $align ) {
			$attrs['textAlign'] = $align;
		}
		$class = 'wp-block-heading' . ( $align ? ' has-text-align-' . $align : '' );
		return '<!-- wp:heading ' . ( $attrs ? wp_json_encode( $attrs ) . ' ' : '' ) . '-->' . "\n<h{$level} class=\"{$class}\">" . esc_html( $text ) . "</h{$level}>\n<!-- /wp:heading -->\n";
	}

	/**
	 * Paragraph block markup.
	 *
	 * @param string $text  Text.
	 * @param string $align Alignment.
	 * @return string
	 */
	private static function paragraph( $text, $align = '' ) {
		return '<!-- wp:paragraph ' . ( $align ? '{"align":"' . $align . '"} ' : '' ) . '-->' . "\n<p" . ( $align ? ' class="has-text-align-' . $align . '"' : '' ) . '>' . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->\n";
	}

	/**
	 * Buttons block markup.
	 *
	 * @param string $text Label.
	 * @param string $url  Link.
	 * @return string
	 */
	private static function button( $text, $url ) {
		return '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->' . "\n" . '<div class="wp-block-buttons"><!-- wp:button -->' . "\n"
			. '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></div>' . "\n"
			. '<!-- /wp:button --></div>' . "\n" . '<!-- /wp:buttons -->' . "\n";
	}

	/**
	 * Patterns.
	 *
	 * @return array[]
	 */
	public static function all() {
		$archive = get_post_type_archive_link( 'property' );
		$archive = $archive ? $archive : home_url( '/property/' );
		return array(
			'hero-search'         => array(
				'title'       => __( 'Hero with property search', 'wp-smart-property' ),
				'description' => __( 'A large heading and the property search, for the top of a home page.', 'wp-smart-property' ),
				'content'     => '<!-- wp:group {"align":"full","className":"estatecraft-hero","layout":{"type":"constrained","contentSize":"960px"}} -->' . "\n" . '<div class="wp-block-group alignfull estatecraft-hero">' . "\n"
					. self::heading( __( 'Find your next home', 'wp-smart-property' ), 1, 'center' )
					. self::paragraph( __( 'Search houses and apartments for sale and to rent.', 'wp-smart-property' ), 'center' )
					. '<!-- wp:estatecraft/property-search {"fields":["location","type","status","price","beds"],"more":false} /-->' . "\n"
					. '</div>' . "\n" . '<!-- /wp:group -->',
			),
			'search-page'         => array(
				'title'       => __( 'Property search page', 'wp-smart-property' ),
				'description' => __( 'Search filters with results and a map. Choose the page in EstateCraft settings, Pages.', 'wp-smart-property' ),
				'blockTypes'  => array( 'core/post-content' ),
				'content'     => '<!-- wp:estatecraft/property-search {"align":"wide"} /-->' . "\n" . '<!-- wp:estatecraft/search-results {"align":"wide"} /-->',
			),
			'featured-properties' => array(
				'title'       => __( 'Featured properties', 'wp-smart-property' ),
				'description' => __( 'A heading, three featured properties and a link to all properties.', 'wp-smart-property' ),
				'content'     => '<!-- wp:group {"align":"wide","layout":{"type":"constrained","wideSize":"1200px"}} -->' . "\n" . '<div class="wp-block-group alignwide">' . "\n"
					. self::heading( __( 'Featured properties', 'wp-smart-property' ), 2, 'center' )
					. '<!-- wp:estatecraft/property-listings {"count":3,"featuredOnly":true,"align":"wide"} /-->' . "\n"
					. self::button( __( 'View all properties', 'wp-smart-property' ), $archive )
					. '</div>' . "\n" . '<!-- /wp:group -->',
			),
			'latest-properties'   => array(
				'title'       => __( 'Latest properties', 'wp-smart-property' ),
				'description' => __( 'The six newest properties in a grid.', 'wp-smart-property' ),
				'content'     => '<!-- wp:group {"align":"wide","layout":{"type":"constrained","wideSize":"1200px"}} -->' . "\n" . '<div class="wp-block-group alignwide">' . "\n"
					. self::heading( __( 'New on the market', 'wp-smart-property' ) )
					. '<!-- wp:estatecraft/property-listings {"count":6,"order":"newest","align":"wide"} /-->' . "\n"
					. '</div>' . "\n" . '<!-- /wp:group -->',
			),
			'property-spotlight'  => array(
				'title'       => __( 'Property spotlight', 'wp-smart-property' ),
				'description' => __( 'One featured property, large.', 'wp-smart-property' ),
				'content'     => '<!-- wp:estatecraft/featured-property {"align":"wide"} /-->',
			),
			'agent-team'          => array(
				'title'       => __( 'Our agents', 'wp-smart-property' ),
				'description' => __( 'A heading, a short introduction and the agent team.', 'wp-smart-property' ),
				'content'     => '<!-- wp:group {"align":"wide","layout":{"type":"constrained","wideSize":"1200px"}} -->' . "\n" . '<div class="wp-block-group alignwide">' . "\n"
					. self::heading( __( 'Meet our agents', 'wp-smart-property' ), 2, 'center' )
					. self::paragraph( __( 'Local experts who know every street. Get in touch for a free valuation or a viewing.', 'wp-smart-property' ), 'center' )
					. '<!-- wp:estatecraft/agents {"count":8,"columns":4,"align":"wide"} /-->' . "\n"
					. '</div>' . "\n" . '<!-- /wp:group -->',
			),
			'valuation-cta'       => array(
				'title'       => __( 'Valuation call to action', 'wp-smart-property' ),
				'description' => __( 'Invite visitors to ask for a valuation.', 'wp-smart-property' ),
				'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"accent-5","layout":{"type":"constrained"}} -->' . "\n"
					. '<div class="wp-block-group alignfull has-accent-5-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">' . "\n"
					. self::heading( __( 'Thinking of selling?', 'wp-smart-property' ), 2, 'center' )
					. self::paragraph( __( 'Find out what your home is worth with a free, no-obligation valuation.', 'wp-smart-property' ), 'center' )
					. self::button( __( 'Book a valuation', 'wp-smart-property' ), home_url( '/contact/' ) )
					. '</div>' . "\n" . '<!-- /wp:group -->',
			),
		);
	}
}
