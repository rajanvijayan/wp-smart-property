<?php
/**
 * Listing blocks: property lists, a featured property, agent lists and agent profiles.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Agents\Agent_Types;
use EstateCraft\Properties\Property_Type;
use WP_Block;
use WP_Query;

/**
 * Render callbacks.
 */
final class Listing_Blocks {

	/**
	 * Agent or agency the block is about (context, global post or a preview).
	 *
	 * @param WP_Block|null $block Block.
	 * @param string[]      $types Allowed post types.
	 * @return int
	 */
	private static function profile_id( $block, array $types ) {
		$id = $block && ! empty( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		if ( $id && in_array( get_post_type( $id ), $types, true ) ) {
			return $id;
		}
		return View::is_preview() ? View::preview_id( $types[0] ) : 0;
	}

	/**
	 * Property list.
	 *
	 * Sources: query (the block's own filters), main (the archive being viewed), similar (like the
	 * property being viewed), agent (listings of the agent being viewed), agency (of its agents).
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function listings( $attributes, $content, $block ) {
		$a      = wp_parse_args(
			$attributes,
			array(
				'source'       => 'query',
				'count'        => 6,
				'order'        => 'featured',
				'featuredOnly' => false,
				'types'        => array(),
				'statuses'     => array(),
				'locations'    => array(),
				'layout'       => 'grid',
				'columns'      => 3,
				'heading'      => '',
				'pagination'   => true,
			)
		);
		$layout = 'list' === $a['layout'] ? 'list' : 'grid';
		$source = $a['source'];
		$main   = false;

		if ( 'main' === $source ) {
			global $wp_query;
			$query = $wp_query;
			$main  = true;
			// Outside a property archive (e.g. a preview in the template editor) show recent ones.
			if ( ! $query instanceof WP_Query || ! Query::is_property_archive( $query ) ) {
				$query = Query::listings( array( 'count' => $a['count'] ) );
				$main  = false;
			}
		} else {
			$args = array(
				'count'    => $a['count'],
				'order'    => $a['order'],
				'featured' => $a['featuredOnly'],
				'terms'    => array(
					'property_type'     => $a['types'],
					'property_status'   => $a['statuses'],
					'property_location' => $a['locations'],
				),
			);
			if ( 'similar' === $source ) {
				$id = View::property_id( $block );
				if ( ! $id ) {
					return '';
				}
				$args = array(
					'count'   => $a['count'],
					'similar' => $id,
				);
			} elseif ( 'agent' === $source || 'agency' === $source ) {
				$profile = self::profile_id( $block, 'agent' === $source ? array( Agent_Types::AGENT ) : array( Agent_Types::AGENCY ) );
				if ( ! $profile ) {
					return '';
				}
				$args['agents'] = 'agent' === $source ? array( $profile ) : Query::agency_agents( $profile );
			}
			$query = Query::listings( $args );
		}

		$heading = $main ? 'h2' : 'h3';
		$cards   = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			$cards .= '<li class="estatecraft-listings__item">' . View::card( get_the_ID(), $heading, $layout ) . '</li>';
		}
		wp_reset_postdata();

		if ( '' === $cards ) {
			if ( $main ) {
				return Blocks::wrap( '<p class="estatecraft-empty">' . esc_html__( 'No properties found.', 'wp-smart-property' ) . '</p>', 'estatecraft-listings' );
			}
			// Similar, agent and filtered lists simply disappear when empty.
			return Blocks::empty_preview( __( 'No properties to show here yet.', 'wp-smart-property' ) );
		}

		$html  = '' !== trim( $a['heading'] ) ? '<h2 class="estatecraft-heading">' . esc_html( $a['heading'] ) . '</h2>' : '';
		$html .= '<ul class="estatecraft-listings__list is-' . esc_attr( $layout ) . '" style="--estatecraft-columns:' . esc_attr( max( 1, min( 4, (int) $a['columns'] ) ) ) . '">' . $cards . '</ul>';
		if ( $main && $a['pagination'] ) {
			$links = paginate_links(
				array(
					'total'     => (int) $query->max_num_pages,
					'current'   => max( 1, (int) get_query_var( 'paged' ) ),
					'prev_text' => __( 'Previous', 'wp-smart-property' ),
					'next_text' => __( 'Next', 'wp-smart-property' ),
					'type'      => 'plain',
				)
			);
			if ( $links ) {
				$html .= '<nav class="estatecraft-pagination" aria-label="' . esc_attr__( 'Properties pages', 'wp-smart-property' ) . '">' . $links . '</nav>';
			}
		}
		return Blocks::wrap( $html, 'estatecraft-listings' );
	}

	/**
	 * One property presented large.
	 *
	 * @param array    $attributes Attributes (propertyId; 0 = the latest featured property).
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function featured( $attributes, $content, $block ) {
		$id = absint( $attributes['propertyId'] ?? 0 );
		if ( ! $id || Property_Type::POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			$query = Query::listings(
				array(
					'count'    => 1,
					'featured' => true,
				)
			);
			if ( ! $query->posts ) {
				$query = Query::listings( array( 'count' => 1 ) );
			}
			$id = $query->posts ? (int) $query->posts[0]->ID : 0;
		}
		if ( ! $id ) {
			return Blocks::empty_preview( __( 'Publish a property to show it here.', 'wp-smart-property' ) );
		}
		$link    = get_permalink( $id );
		$image   = View::main_image( $id );
		$html    = '<a class="estatecraft-featured__media" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">';
		$html   .= $image ? wp_get_attachment_image(
			$image,
			'large',
			false,
			array(
				'alt'   => '',
				'sizes' => '(max-width: 800px) 100vw, 60vw',
			)
		) : '<span class="estatecraft-card__placeholder">' . Icons::get( 'home' ) . '</span>';
		$html   .= '</a><div class="estatecraft-featured__body">' . View::badges( $id );
		$html   .= '<p class="estatecraft-featured__price">' . esc_html( View::price( $id ) ) . '</p>';
		$html   .= '<h2 class="estatecraft-featured__title"><a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h2>';
		$address = View::address( $id );
		if ( '' !== $address ) {
			$html .= '<p class="estatecraft-address">' . Icons::get( 'pin' ) . '<span>' . esc_html( $address ) . '</span></p>';
		}
		$html .= View::facts_html( $id, true );
		if ( has_excerpt( $id ) ) {
			$html .= '<p class="estatecraft-featured__excerpt">' . esc_html( get_the_excerpt( $id ) ) . '</p>';
		}
		$html .= '<p><a class="estatecraft-button" href="' . esc_url( $link ) . '">' . esc_html__( 'View property', 'wp-smart-property' ) . '<span class="screen-reader-text">: ' . esc_html( get_the_title( $id ) ) . '</span></a></p>';
		unset( $content, $block );
		return Blocks::wrap( $html . '</div>', 'estatecraft-featured' );
	}

	/**
	 * Agent team: all agents, an agency's agents, or the agents archive being viewed.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function agent_list( $attributes, $content, $block ) {
		$source = $attributes['source'] ?? 'all';
		$count  = max( 1, min( 48, (int) ( $attributes['count'] ?? 12 ) ) );
		$ids    = array();
		$main   = false;
		if ( 'main' === $source && ( is_post_type_archive( array( Agent_Types::AGENT, Agent_Types::AGENCY ) ) ) ) {
			global $wp_query;
			$ids  = wp_list_pluck( $wp_query->posts, 'ID' );
			$main = true;
		} elseif ( 'agency' === $source ) {
			$agency = absint( $attributes['agencyId'] ?? 0 );
			$agency = $agency ? $agency : self::profile_id( $block, array( Agent_Types::AGENCY ) );
			$ids    = $agency ? array_slice( Query::agency_agents( $agency ), 0, $count ) : array();
		} else {
			$ids = get_posts(
				array(
					'post_type'   => Agent_Types::AGENT,
					'fields'      => 'ids',
					'numberposts' => $count,
					'orderby'     => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
				)
			);
		}
		$html = '';
		foreach ( $ids as $id ) {
			$card  = Blocks::agent_card( $id, $main ? 'h2' : 'h3' );
			$html .= '' !== $card ? '<li>' . $card . '</li>' : '';
		}
		if ( '' === $html ) {
			return $main ? Blocks::wrap( '<p class="estatecraft-empty">' . esc_html__( 'No agents yet.', 'wp-smart-property' ) . '</p>', 'estatecraft-agents' ) : Blocks::empty_preview( __( 'No agents yet.', 'wp-smart-property' ) );
		}
		$title = trim( (string) ( $attributes['heading'] ?? '' ) );
		$html  = ( '' !== $title ? '<h2 class="estatecraft-heading">' . esc_html( $title ) . '</h2>' : '' ) . '<ul class="estatecraft-agents__list" style="--estatecraft-columns:' . esc_attr( max( 1, min( 4, (int) ( $attributes['columns'] ?? 3 ) ) ) ) . '">' . $html . '</ul>';
		if ( $main ) {
			$links = paginate_links(
				array(
					'total'   => (int) $GLOBALS['wp_query']->max_num_pages,
					'current' => max( 1, (int) get_query_var( 'paged' ) ),
				)
			);
			$html .= $links ? '<nav class="estatecraft-pagination" aria-label="' . esc_attr__( 'Agents pages', 'wp-smart-property' ) . '">' . $links . '</nav>' : '';
		}
		return Blocks::wrap( $html, 'estatecraft-agents' );
	}

	/**
	 * Contact details on an agent or agency page.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function profile( $attributes, $content, $block ) {
		$id = self::profile_id( $block, array( Agent_Types::AGENT, Agent_Types::AGENCY ) );
		if ( ! $id ) {
			return '';
		}
		$html = '';
		if ( Agent_Types::AGENT === get_post_type( $id ) ) {
			$agency = (int) get_post_meta( $id, '_estatecraft_agency', true );
			if ( $agency && 'publish' === get_post_status( $agency ) ) {
				$html .= '<p class="estatecraft-profile__agency"><a href="' . esc_url( get_permalink( $agency ) ) . '">' . esc_html( get_the_title( $agency ) ) . '</a></p>';
			}
			$html .= Blocks::contact_list( $id, array( 'phone', 'mobile', 'email', 'website', 'license', 'languages' ) );
			$count = Query::listings(
				array(
					'count'  => 1,
					'agents' => array( $id ),
				)
			)->found_posts;
		} else {
			$html .= Blocks::contact_list( $id, array( 'address', 'phone', 'email', 'website' ) );
			$count = Query::listings(
				array(
					'count'  => 1,
					'agents' => Query::agency_agents( $id ),
				)
			)->found_posts;
		}
		if ( $count ) {
			$html .= '<p class="estatecraft-profile__count">' . esc_html(
				sprintf(
					/* translators: %d: number of properties */
					_n( '%d property listed', '%d properties listed', $count, 'wp-smart-property' ),
					$count
				)
			) . '</p>';
		}
		unset( $attributes, $content );
		return Blocks::wrap( $html, 'estatecraft-profile' );
	}
}
