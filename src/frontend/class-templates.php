<?php
/**
 * Page templates for properties, agents and agencies.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Agents\Agent_Types;
use EstateCraft\Properties\Property_Type;
use EstateCraft\Properties\Taxonomies;

/**
 * One layout per page type, written as block markup and used two ways:
 * - block themes get it as a plugin template (editable in the Site Editor; a theme or a saved
 *   template with the same slug wins);
 * - classic themes get a PHP template that prints it between get_header() and get_footer(). A theme
 *   overrides it with yourtheme/estatecraft/{name}.php, or its own single-property.php etc.
 */
final class Templates {

	/**
	 * Template slugs with title and description.
	 *
	 * @return array[]
	 */
	public static function all() {
		return array(
			'single-property'      => array( __( 'Single property', 'wp-smart-property' ), __( 'Displays a single property.', 'wp-smart-property' ) ),
			'archive-property'     => array( __( 'Property archive', 'wp-smart-property' ), __( 'Lists properties, also for property types, statuses, locations and features.', 'wp-smart-property' ) ),
			'single-estate_agent'  => array( __( 'Single agent', 'wp-smart-property' ), __( 'Displays an agent with their listings.', 'wp-smart-property' ) ),
			'single-estate_agency' => array( __( 'Single agency', 'wp-smart-property' ), __( 'Displays an agency with its agents and listings.', 'wp-smart-property' ) ),
			'archive-estate_agent' => array( __( 'Agent archive', 'wp-smart-property' ), __( 'Lists agents and agencies.', 'wp-smart-property' ) ),
		);
	}

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'register_block_templates' ), 20 );
		add_filter( 'get_block_templates', array( self::class, 'add_block_templates' ), 10, 3 );
		add_filter( 'taxonomy_template_hierarchy', array( self::class, 'taxonomy_hierarchy' ) );
		add_filter( 'archive_template_hierarchy', array( self::class, 'archive_hierarchy' ) );
		add_filter( 'template_include', array( self::class, 'classic' ), 50 );
	}

	/**
	 * Template for the current request, or ''.
	 *
	 * @return string
	 */
	public static function current() {
		if ( is_singular( Property_Type::POST_TYPE ) ) {
			return 'single-property';
		}
		if ( is_post_type_archive( Property_Type::POST_TYPE ) || is_tax( array_keys( Taxonomies::all() ) ) ) {
			return 'archive-property';
		}
		if ( is_singular( Agent_Types::AGENT ) ) {
			return 'single-estate_agent';
		}
		if ( is_singular( Agent_Types::AGENCY ) ) {
			return 'single-estate_agency';
		}
		if ( is_post_type_archive( array( Agent_Types::AGENT, Agent_Types::AGENCY ) ) ) {
			return 'archive-estate_agent';
		}
		return '';
	}

	/**
	 * Block markup of a block.
	 *
	 * @param string $name       Block name without namespace (core blocks use core/...).
	 * @param array  $attributes Attributes.
	 * @return string
	 */
	private static function block( $name, array $attributes = array() ) {
		$name = false === strpos( $name, '/' ) ? 'estatecraft/' . $name : $name;
		$name = 0 === strpos( $name, 'core/' ) ? substr( $name, 5 ) : $name;
		return '<!-- wp:' . $name . ( $attributes ? ' ' . wp_json_encode( $attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '' ) . " /-->\n";
	}

	/**
	 * Columns markup.
	 *
	 * @param array[] $columns Each: array( width, inner markup ).
	 * @return string
	 */
	private static function columns( array $columns ) {
		$html = '<!-- wp:columns {"align":"wide"} -->' . "\n" . '<div class="wp-block-columns alignwide">' . "\n";
		foreach ( $columns as $column ) {
			$html .= '<!-- wp:column {"width":"' . $column[0] . '"} -->' . "\n" . '<div class="wp-block-column" style="flex-basis:' . $column[0] . '">' . "\n" . $column[1] . '</div>' . "\n" . '<!-- /wp:column -->' . "\n";
		}
		return $html . '</div>' . "\n" . '<!-- /wp:columns -->' . "\n";
	}

	/**
	 * Group markup.
	 *
	 * @param string $inner     Inner markup.
	 * @param string $css_class Class.
	 * @param string $tag       Tag.
	 * @param array  $attrs     Extra attributes.
	 * @return string
	 */
	private static function group( $inner, $css_class, $tag = 'div', array $attrs = array() ) {
		$attrs = array_merge(
			array(
				'tagName'   => $tag,
				'className' => $css_class,
			),
			$attrs
		);
		return '<!-- wp:group ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' -->' . "\n" . '<' . $tag . ' class="wp-block-group ' . esc_attr( $css_class ) . '">' . "\n" . $inner . '</' . $tag . '>' . "\n" . '<!-- /wp:group -->' . "\n";
	}

	/**
	 * The page body of a template.
	 *
	 * @param string $name Template slug.
	 * @return string
	 */
	public static function body( $name ) {
		switch ( $name ) {
			case 'single-property':
				$main = self::block( 'core/post-title', array( 'level' => 1 ) )
					. self::block( 'property-address' )
					. self::block( 'property-facts' )
					. self::block( 'core/post-content', array( 'layout' => array( 'type' => 'default' ) ) )
					. self::block( 'property-features' )
					. self::block( 'property-details' )
					. self::block( 'floor-plans' )
					. self::block( 'property-video' )
					. self::block( 'property-map' );
				$side = self::group(
					self::block( 'property-price' )
					. self::block( 'open-houses' )
					. self::block( 'property-agents' )
					. self::block( 'property-actions' ),
					'estatecraft-sidebar'
				);
				return self::block( 'property-gallery', array( 'align' => 'wide' ) )
					. self::columns( array( array( '64%', $main ), array( '36%', $side ) ) )
					. self::block(
						'property-listings',
						array(
							'source'  => 'similar',
							'count'   => 3,
							'heading' => __( 'Similar properties', 'wp-smart-property' ),
							'align'   => 'wide',
						)
					);
			case 'archive-property':
				return self::block(
					'core/query-title',
					array(
						'type'       => 'archive',
						'showPrefix' => false,
						'align'      => 'wide',
					)
				)
					. self::block( 'core/term-description', array( 'align' => 'wide' ) )
					. self::block( 'property-search', array( 'align' => 'wide' ) )
					. self::block( 'search-results', array( 'align' => 'wide' ) );
			case 'single-estate_agent':
			case 'single-estate_agency':
				$agency = 'single-estate_agency' === $name;
				$intro  = self::columns(
					array(
						array( '30%', self::block( 'core/post-featured-image', array( 'aspectRatio' => $agency ? 'auto' : '1' ) ) ),
						array( '70%', self::block( 'core/post-title', array( 'level' => 1 ) ) . self::block( 'agent-profile' ) . self::block( 'core/post-content', array( 'layout' => array( 'type' => 'default' ) ) ) ),
					)
				);
				$agents = $agency ? self::block(
					'agents',
					array(
						'source'  => 'agency',
						'heading' => __( 'Our agents', 'wp-smart-property' ),
						'align'   => 'wide',
					)
				) : '';
				return $intro . $agents . self::block(
					'property-listings',
					array(
						'source'  => $agency ? 'agency' : 'agent',
						'count'   => 12,
						'heading' => __( 'Listings', 'wp-smart-property' ),
						'align'   => 'wide',
					)
				);
			case 'archive-estate_agent':
				return self::block(
					'core/query-title',
					array(
						'type'       => 'archive',
						'showPrefix' => false,
						'align'      => 'wide',
					)
				) . self::block(
					'agents',
					array(
						'source' => 'main',
						'align'  => 'wide',
					)
				);
		}
		return '';
	}

	/**
	 * Full block template: theme header, the body in <main>, theme footer.
	 *
	 * @param string $name Template slug.
	 * @return string
	 */
	public static function block_template( $name ) {
		$main = self::group(
			self::body( $name ),
			'estatecraft-page is-' . $name,
			'main',
			array(
				'layout' => array(
					'type'     => 'constrained',
					'wideSize' => '1200px',
				),
			)
		);
		return '<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->' . "\n" . $main
			. '<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->';
	}

	/**
	 * Register the templates (WordPress 6.7+).
	 */
	public static function register_block_templates() {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}
		foreach ( self::all() as $slug => $info ) {
			register_block_template(
				'wp-smart-property//' . $slug,
				array(
					'title'       => $info[0],
					'description' => $info[1],
					'content'     => self::block_template( $slug ),
				)
			);
		}
	}

	/**
	 * Provide the templates on WordPress 6.5 and 6.6, where plugins cannot register them.
	 *
	 * @param \WP_Block_Template[] $templates     Templates found.
	 * @param array                $query         Query.
	 * @param string               $template_type wp_template or wp_template_part.
	 * @return \WP_Block_Template[]
	 */
	public static function add_block_templates( $templates, $query, $template_type ) {
		if ( function_exists( 'register_block_template' ) || 'wp_template' !== $template_type || ! wp_is_block_theme() ) {
			return $templates;
		}
		$have = wp_list_pluck( $templates, 'slug' );
		foreach ( self::all() as $slug => $info ) {
			if ( in_array( $slug, $have, true ) || ( ! empty( $query['slug__in'] ) && ! in_array( $slug, $query['slug__in'], true ) ) ) {
				continue;
			}
			if ( ! empty( $query['slug__not_in'] ) && in_array( $slug, $query['slug__not_in'], true ) ) {
				continue;
			}
			$template                 = new \WP_Block_Template();
			$template->type           = 'wp_template';
			$template->theme          = get_stylesheet();
			$template->slug           = $slug;
			$template->id             = get_stylesheet() . '//' . $slug;
			$template->title          = $info[0];
			$template->description    = $info[1];
			$template->content        = self::block_template( $slug );
			$template->source         = 'plugin';
			$template->origin         = 'plugin';
			$template->status         = 'publish';
			$template->has_theme_file = false;
			$template->is_custom      = false;
			$template->post_types     = array();
			$templates[]              = $template;
		}
		return $templates;
	}

	/**
	 * Property taxonomy archives use the property archive template unless the theme has a template
	 * for that taxonomy.
	 *
	 * @param string[] $templates Template candidates.
	 * @return string[]
	 */
	public static function taxonomy_hierarchy( $templates ) {
		if ( ! is_tax( array_keys( Taxonomies::all() ) ) ) {
			return $templates;
		}
		$at = array_search( 'taxonomy', $templates, true );
		array_splice( $templates, false === $at ? count( $templates ) : $at, 0, array( 'archive-property' ) );
		return $templates;
	}

	/**
	 * The agency archive uses the agent archive template.
	 *
	 * @param string[] $templates Template candidates.
	 * @return string[]
	 */
	public static function archive_hierarchy( $templates ) {
		if ( ! is_post_type_archive( Agent_Types::AGENCY ) ) {
			return $templates;
		}
		$at = array_search( 'archive', $templates, true );
		array_splice( $templates, false === $at ? count( $templates ) : $at, 0, array( 'archive-estate_agent' ) );
		return $templates;
	}

	/**
	 * Classic themes: use the plugin template unless the theme has a specific one.
	 *
	 * @param string $template Template WordPress chose.
	 * @return string
	 */
	public static function classic( $template ) {
		if ( wp_is_block_theme() ) {
			return $template;
		}
		$name = self::current();
		return '' === $name ? $template : self::resolve( $name, (string) $template );
	}

	/**
	 * Template file to use.
	 *
	 * @param string $name     Our template slug.
	 * @param string $template Template WordPress chose.
	 * @return string
	 */
	public static function resolve( $name, $template ) {
		$base = basename( $template, '.php' );
		// The theme has its own template for this page type (single-property.php, taxonomy-property_type.php ...).
		if ( 0 === strpos( $base, $name ) || ( 'archive-property' === $name && 0 === strpos( $base, 'taxonomy-property_' ) ) ) {
			return $template;
		}
		$override = locate_template( 'estatecraft/' . $name . '.php' );
		return $override ? $override : ESTATECRAFT_DIR . 'templates/' . $name . '.php';
	}

	/**
	 * Print a template body in a classic theme (called from templates/*.php).
	 *
	 * @param string $name Template slug.
	 */
	public static function render( $name ) {
		$context = static function ( $context ) {
			if ( empty( $context['postId'] ) && get_the_ID() ) {
				$context['postId']   = get_the_ID();
				$context['postType'] = get_post_type();
			}
			return $context;
		};
		add_filter( 'render_block_context', $context );
		$body = self::group(
			self::body( $name ),
			'estatecraft-page is-classic is-' . $name,
			'div',
			array(
				'layout' => array(
					'type'     => 'constrained',
					'wideSize' => '1200px',
				),
			)
		);
		if ( is_singular() ) {
			while ( have_posts() ) {
				the_post();
				echo do_blocks( $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks.
			}
		} else {
			echo do_blocks( $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks.
		}
		remove_filter( 'render_block_context', $context );
	}
}
