<?php
/**
 * Smart menu search engine.
 *
 * Ranking signals:
 *  - exact phrase in the product name          (highest)
 *  - prefix match on a name token
 *  - fuzzy (typo-tolerant) match on a name token
 *  - category / tag match
 *  - description match                          (lowest)
 * Modifiers: unavailable or out-of-schedule items sink, popular items rise.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Search;

use FlavorCore\Support\PersianText;

defined( 'ABSPATH' ) || exit;

/**
 * Class SmartSearch
 */
class SmartSearch {

	/**
	 * Option holding the query log used for suggestions.
	 */
	public const LOG_OPTION = 'flavor_core_search_log';

	/**
	 * Minimum similarity for a fuzzy token match.
	 */
	private const FUZZY_THRESHOLD = 0.72;

	/**
	 * Run a query.
	 *
	 * @param string               $query  Raw user query.
	 * @param array<string, mixed> $args   branch_id, limit, category, dietary, max_price, min_price, only_available.
	 * @return array<string, mixed>
	 */
	public static function search( string $query, array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'branch_id'      => 0,
				'limit'          => 10,
				'category'       => 0,
				'dietary'        => array(),
				'min_price'      => 0,
				'max_price'      => 0,
				'only_available' => false,
			)
		);

		$branch_id = SearchIndex::resolve_branch( (int) $args['branch_id'] );
		$limit     = max( 1, min( 30, (int) $args['limit'] ) );
		$docs      = SearchIndex::documents( $branch_id );

		$normalized = PersianText::normalize( $query );
		$tokens     = PersianText::tokens( $query );

		$hits = array();

		foreach ( $docs as $doc ) {
			if ( ! self::passes_filters( $doc, $args ) ) {
				continue;
			}

			$score = '' === $normalized
				? self::baseline_score( $doc )
				: self::score( $doc, $normalized, $tokens );

			if ( $score <= 0 ) {
				continue;
			}

			$hits[] = array(
				'score' => $score,
				'doc'   => $doc,
			);
		}

		usort(
			$hits,
			static function ( array $a, array $b ): int {
				if ( $a['score'] === $b['score'] ) {
					return strcmp( (string) $a['doc']['name'], (string) $b['doc']['name'] );
				}
				return $a['score'] < $b['score'] ? 1 : -1;
			}
		);

		$total   = count( $hits );
		$sliced  = array_slice( $hits, 0, $limit );
		$results = array();

		foreach ( $sliced as $hit ) {
			$results[] = self::present( $hit['doc'], $tokens, (float) $hit['score'] );
		}

		$payload = array(
			'query'      => $query,
			'normalized' => $normalized,
			'tokens'     => $tokens,
			'branch_id'  => $branch_id,
			'total'      => $total,
			'results'    => $results,
			'facets'     => self::facets( $hits ),
			'suggestion' => $results ? '' : self::did_you_mean( $normalized, $docs ),
			'popular'    => $results ? array() : self::popular( 6 ),
		);

		return apply_filters( 'flavor_core_search_response', $payload, $query, $args );
	}

	/**
	 * Filters that run before scoring.
	 *
	 * @param array<string, mixed> $doc  Document.
	 * @param array<string, mixed> $args Args.
	 */
	private static function passes_filters( array $doc, array $args ): bool {
		if ( ! empty( $args['only_available'] ) && empty( $doc['available'] ) ) {
			return false;
		}

		$category = (int) $args['category'];
		if ( $category ) {
			$ids = wp_list_pluck( (array) $doc['categories'], 'id' );
			if ( ! in_array( $category, array_map( 'intval', $ids ), true ) ) {
				return false;
			}
		}

		$dietary = array_filter( (array) $args['dietary'] );
		if ( $dietary ) {
			$have = array_map( 'strval', (array) $doc['dietary'] );
			foreach ( $dietary as $flag ) {
				if ( ! in_array( (string) $flag, $have, true ) ) {
					return false;
				}
			}
		}

		$min = (int) $args['min_price'];
		$max = (int) $args['max_price'];
		if ( $min && (int) $doc['price'] < $min ) {
			return false;
		}
		if ( $max && (int) $doc['price'] > $max ) {
			return false;
		}

		return true;
	}

	/**
	 * Score for an empty query (browse mode): popularity first.
	 *
	 * @param array<string, mixed> $doc Document.
	 */
	private static function baseline_score( array $doc ): float {
		$score = 1.0;
		$score += min( 3.0, (int) $doc['popularity'] / 25 );
		$score += (float) $doc['rating'];

		return self::apply_modifiers( $score, $doc );
	}

	/**
	 * Relevance score for a document.
	 *
	 * @param array<string, mixed> $doc        Document.
	 * @param string               $normalized Normalised query.
	 * @param string[]             $tokens     Query tokens.
	 */
	private static function score( array $doc, string $normalized, array $tokens ): float {
		$score     = 0.0;
		$name_norm = (string) $doc['name_norm'];

		if ( $name_norm === $normalized ) {
			$score += 120;
		} elseif ( '' !== $normalized && str_contains( $name_norm, $normalized ) ) {
			$score += 70;
		}

		$name_tokens = (array) $doc['name_tokens'];
		$text_tokens = (array) $doc['text_tokens'];
		$cat_tokens  = (array) $doc['cat_tokens'];
		$tag_tokens  = (array) $doc['tag_tokens'];

		$matched = 0;

		foreach ( $tokens as $token ) {
			$best = 0.0;

			foreach ( $name_tokens as $candidate ) {
				if ( $candidate === $token ) {
					$best = max( $best, 40.0 );
					continue;
				}
				if ( str_starts_with( $candidate, $token ) ) {
					$best = max( $best, 30.0 );
					continue;
				}
				if ( str_contains( $candidate, $token ) ) {
					$best = max( $best, 20.0 );
					continue;
				}
				$similarity = PersianText::similarity( $candidate, $token );
				if ( $similarity >= self::FUZZY_THRESHOLD ) {
					$best = max( $best, 24.0 * $similarity );
				}
			}

			foreach ( $cat_tokens as $candidate ) {
				if ( $candidate === $token || str_starts_with( $candidate, $token ) ) {
					$best = max( $best, 16.0 );
				}
			}

			foreach ( $tag_tokens as $candidate ) {
				if ( $candidate === $token || str_starts_with( $candidate, $token ) ) {
					$best = max( $best, 12.0 );
				}
			}

			if ( $best < 10.0 ) {
				foreach ( $text_tokens as $candidate ) {
					if ( $candidate === $token ) {
						$best = max( $best, 8.0 );
						break;
					}
					if ( str_starts_with( $candidate, $token ) ) {
						$best = max( $best, 5.0 );
					}
				}
			}

			if ( $best > 0 ) {
				$matched++;
				$score += $best;
			}
		}

		if ( 0 === $matched && $score <= 0 ) {
			return 0.0;
		}

		// Reward covering the whole query.
		if ( $tokens && $matched === count( $tokens ) ) {
			$score *= 1.25;
		}

		return self::apply_modifiers( $score, $doc );
	}

	/**
	 * Availability / popularity modifiers.
	 *
	 * @param float                $score Raw score.
	 * @param array<string, mixed> $doc   Document.
	 */
	private static function apply_modifiers( float $score, array $doc ): float {
		if ( empty( $doc['available'] ) ) {
			$score *= 0.45;
		}
		if ( empty( $doc['in_schedule'] ) ) {
			$score *= 0.7;
		}
		$score += min( 6.0, (int) $doc['popularity'] / 40 );
		$score += (float) $doc['rating'] * 0.8;

		return round( $score, 3 );
	}

	/**
	 * Shape a document for the wire, with highlighted name.
	 *
	 * @param array<string, mixed> $doc    Document.
	 * @param string[]             $tokens Query tokens.
	 * @param float                $score  Score.
	 * @return array<string, mixed>
	 */
	private static function present( array $doc, array $tokens, float $score ): array {
		return array(
			'id'           => (int) $doc['id'],
			'name'         => (string) $doc['name'],
			'highlight'    => self::highlight( (string) $doc['name'], $tokens ),
			'short'        => (string) $doc['short'],
			'image'        => (string) $doc['image'],
			'permalink'    => (string) $doc['permalink'],
			'price'        => (int) $doc['price'],
			'price_html'   => (string) $doc['price_html'],
			'prep_time'    => (int) $doc['prep_time'],
			'calories'     => (int) $doc['calories'],
			'dietary'      => (array) $doc['dietary'],
			'categories'   => (array) $doc['categories'],
			'available'    => (bool) $doc['available'],
			'in_schedule'  => (bool) $doc['in_schedule'],
			'available_at' => (string) $doc['available_at'],
			'score'        => $score,
		);
	}

	/**
	 * Wrap matched fragments in <mark>. Input is escaped first.
	 *
	 * @param string   $name   Product name.
	 * @param string[] $tokens Query tokens.
	 */
	public static function highlight( string $name, array $tokens ): string {
		$safe = esc_html( $name );
		if ( ! $tokens ) {
			return $safe;
		}

		$words = preg_split( '/(\s+)/u', $safe, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: array();
		$out   = '';

		foreach ( $words as $word ) {
			if ( '' === trim( $word ) ) {
				$out .= $word;
				continue;
			}
			$norm = PersianText::stem( PersianText::normalize( $word ) );
			$hit  = false;
			foreach ( $tokens as $token ) {
				if ( '' === $norm ) {
					continue;
				}
				if ( str_starts_with( $norm, $token ) || str_contains( $norm, $token )
					|| PersianText::similarity( $norm, $token ) >= self::FUZZY_THRESHOLD ) {
					$hit = true;
					break;
				}
			}
			$out .= $hit ? '<mark>' . $word . '</mark>' : $word;
		}

		return $out;
	}

	/**
	 * Facet counts for the current hit set.
	 *
	 * @param array<int, array<string, mixed>> $hits Hits.
	 * @return array<string, mixed>
	 */
	private static function facets( array $hits ): array {
		$categories = array();
		$dietary    = array();
		$prices     = array();

		foreach ( $hits as $hit ) {
			$doc      = $hit['doc'];
			$prices[] = (int) $doc['price'];

			foreach ( (array) $doc['categories'] as $cat ) {
				$id = (int) $cat['id'];
				if ( ! isset( $categories[ $id ] ) ) {
					$categories[ $id ] = array(
						'id'    => $id,
						'name'  => (string) $cat['name'],
						'count' => 0,
					);
				}
				$categories[ $id ]['count']++;
			}

			foreach ( (array) $doc['dietary'] as $flag ) {
				$flag             = (string) $flag;
				$dietary[ $flag ] = ( $dietary[ $flag ] ?? 0 ) + 1;
			}
		}

		$diet_out = array();
		foreach ( $dietary as $slug => $count ) {
			$diet_out[] = array(
				'slug'  => $slug,
				'count' => $count,
			);
		}

		return array(
			'categories' => array_values( $categories ),
			'dietary'    => $diet_out,
			'price'      => array(
				'min' => $prices ? min( $prices ) : 0,
				'max' => $prices ? max( $prices ) : 0,
			),
		);
	}

	/**
	 * Closest product name when nothing matched.
	 *
	 * @param string                           $normalized Normalised query.
	 * @param array<int, array<string, mixed>> $docs       Index.
	 */
	private static function did_you_mean( string $normalized, array $docs ): string {
		if ( '' === $normalized ) {
			return '';
		}

		$best  = '';
		$score = 0.0;

		foreach ( $docs as $doc ) {
			foreach ( (array) $doc['name_tokens'] as $candidate ) {
				$similarity = PersianText::similarity( $candidate, $normalized );
				if ( $similarity > $score ) {
					$score = $similarity;
					$best  = (string) $doc['name'];
				}
			}
		}

		return $score >= 0.55 ? $best : '';
	}

	/**
	 * Record a query for the popular-terms list.
	 *
	 * @param string $query    Raw query.
	 * @param int    $result_n Number of results.
	 */
	public static function log( string $query, int $result_n ): void {
		$normalized = PersianText::normalize( $query );
		if ( PersianText::length( $normalized ) < 2 || $result_n < 1 ) {
			return;
		}

		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[ $normalized ] = (int) ( $log[ $normalized ] ?? 0 ) + 1;

		arsort( $log );
		$log = array_slice( $log, 0, 100, true );

		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * Most searched terms.
	 *
	 * @param int $limit How many.
	 * @return array<int, array<string, mixed>>
	 */
	public static function popular( int $limit = 8 ): array {
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) || ! $log ) {
			return (array) apply_filters( 'flavor_core_search_popular_fallback', array() );
		}

		arsort( $log );
		$out = array();

		foreach ( array_slice( $log, 0, max( 1, $limit ), true ) as $term => $count ) {
			$out[] = array(
				'term'  => (string) $term,
				'count' => (int) $count,
			);
		}

		return $out;
	}

	/**
	 * Autocomplete terms (product + category names) for a prefix.
	 *
	 * @param string $query     Raw prefix.
	 * @param int    $branch_id Branch.
	 * @param int    $limit     Max terms.
	 * @return string[]
	 */
	public static function suggest( string $query, int $branch_id, int $limit = 6 ): array {
		$prefix = PersianText::normalize( $query );
		if ( '' === $prefix ) {
			return wp_list_pluck( self::popular( $limit ), 'term' );
		}

		$docs = SearchIndex::documents( SearchIndex::resolve_branch( $branch_id ) );
		$out  = array();

		foreach ( $docs as $doc ) {
			if ( str_contains( (string) $doc['name_norm'], $prefix ) ) {
				$out[ (string) $doc['name'] ] = true;
			}
			foreach ( (array) $doc['categories'] as $cat ) {
				if ( str_contains( PersianText::normalize( (string) $cat['name'] ), $prefix ) ) {
					$out[ (string) $cat['name'] ] = true;
				}
			}
			if ( count( $out ) >= $limit * 2 ) {
				break;
			}
		}

		return array_slice( array_keys( $out ), 0, $limit );
	}
}
