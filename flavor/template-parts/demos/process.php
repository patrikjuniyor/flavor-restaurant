<?php
/** Per-brand service/process narrative. @package Flavor */
defined( 'ABSPATH' ) || exit;
if ( ! \Flavor\Bespoke_Demos::enabled( 'process' ) ) { return; }
$demo = \Flavor\Bespoke_Demos::demo();
?>
<section class="fd-section fd-process" id="process" aria-labelledby="fd-process-title"><div class="flavor-container"><div class="fd-process__head"><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'process_eyebrow' ) ); ?></span><h2 id="fd-process-title"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'process_title' ) ); ?></h2></div><ol class="fd-process__steps"><?php foreach ( $demo['landing']['steps'] ?? array() as $index => $step ) : ?><li><span class="fd-process__number" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><h3><?php echo esc_html( $step['title'] ); ?></h3><p><?php echo esc_html( $step['text'] ); ?></p></li><?php endforeach; ?></ol></div></section>
