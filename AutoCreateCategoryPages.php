<?php
/**
 * @file
 * @ingroup Extensions
 * @author Merrick Schaefer, Mark Johnston, Evan Wheeler & Adam Mckaig (at UNICEF), Dror S.
 * @licence GPL 3.0
 */

if ( !defined( 'MEDIAWIKI' ) )
	die();

/* ---- CREDITS ---- */
$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'name'           => 'AutoCreateCategoryPages',
	'version'        => '0.2',
	'author'         => array ( 'Merrick Schaefer', 'Mark Johnston', 'Evan Wheeler', 'Adam Mckaig (UNICEF)', 'Dror S. ([http://www.kolzchut.org.il All-Rights])' ),
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Auto_Create_Category_Pages',
	'descriptionmsg' => 'autocreatecategorypages-desc',
);

$wgAutoCreateCategoryStub = null;	// Can be used to override the stub message.

$wgExtensionMessagesFiles['AutoCreateCategoryPages'] = dirname( __FILE__ ) . '/AutoCreateCategoryPages.i18n.php';
$wgAutoloadClasses['UniwikiAutoCreateCategoryPages'] = dirname(__FILE__) . '/AutoCreateCategoryPages.body.php';

$wgAutoCreateCategoryPagesObject = new UniwikiAutoCreateCategoryPages();

/* ---- HOOKS ---- */
$wgHooks['ArticleSaveComplete'][] = array( $wgAutoCreateCategoryPagesObject, "UW_AutoCreateCategoryPages_Save");
$wgHooks['UserGetReservedNames'][] = array( $wgAutoCreateCategoryPagesObject, 'UW_OnUserGetReservedNames');
