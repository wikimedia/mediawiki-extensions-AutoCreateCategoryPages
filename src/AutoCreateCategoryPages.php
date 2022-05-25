<?php

class AutoCreateCategoryPages {

	/**
	 * Register hooks depending on version
	 */
	public static function registerExtension() {
		global $wgHooks;
		if ( class_exists( MediaWiki\HookContainer\HookContainer::class ) ) {
			// MW 1.35+
			$wgHooks['PageSaveComplete'][] = 'AutoCreateCategoryPages::onPageContentSaveComplete';
		} else {
			$wgHooks['PageContentSaveComplete'][] = 'AutoCreateCategoryPages::onPageContentSaveComplete';
		}
	}

	/**
	 * Get an array of existing categories on this page, with the unprefixed name
	 *
	 * @param array $page_cats
	 * @return array
	 */
	private static function getExistingCategories( $page_cats ) {
		if ( empty( $page_cats ) ) {
			return [];
		}
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'page', 'page_title',
			[ 'page_namespace' => NS_CATEGORY, 'page_title' => $page_cats ]
		);
		$categories = [];
		foreach ( $res as $row ) {
			$categories[] = $row->page_title;
		}

		return $categories;
	}

	/**
	 * After the page is saved, get all the categories
	 * and see if they exist as "proper" pages; if not,
	 * create a simple page for them automatically
	 *
	 * @param WikiPage $article
	 * @param User $user
	 * @return true
	 */
	public static function onPageContentSaveComplete(
		WikiPage $article, $user
	) {
		global $wgAutoCreateCategoryStub;

		// Extract the categories on this page
		$page_cats = $article->getParserOutput( $article->makeParserOptions( $user ) )->getCategories();
		// array keys will cast numeric category names to ints
		// so we need to cast them back to strings to avoid potentially breaking things!
		$page_cats = array_map( 'strval', array_keys( $page_cats ) );
		$existing_cats = self::getExistingCategories( $page_cats );

		// Determine which categories on page do not exist
		$new_cats = array_diff( $page_cats, $existing_cats );

		if ( count( $new_cats ) > 0 ) {
			/*
			 * @TODO probably need to use User::newSystemUser()
			 * MW 1.27+ is supposed to use SessionManager, which requires some changes.
			 * See https://www.mediawiki.org/wiki/Manual:SessionManager_and_AuthManager/Updating_tips
			 */

			// Create a user object for the editing user and add it to the database
			// if it is not there already
			$editor = User::newFromName(
				wfMessage( 'autocreatecategorypages-editor' )->inContentLanguage()->text()
			);
			if ( !$editor->isRegistered() ) {
				$editor->addToDatabase();
			}

			$summary = wfMessage( 'autocreatecategorypages-createdby' )->inContentLanguage()->text();

			foreach ( $new_cats as $cat ) {
				$catTitle = Title::newFromDBkey( $cat )->getText();
				$stub = ( $wgAutoCreateCategoryStub != null ) ?
					$wgAutoCreateCategoryStub
					: wfMessage( 'autocreatecategorypages-stub', $catTitle )->inContentLanguage()->text();

				$safeTitle = Title::makeTitleSafe( NS_CATEGORY, $cat );
				$catPage = new WikiPage( $safeTitle );
				try {
					$content = ContentHandler::makeContent( $stub, $safeTitle );
					if ( method_exists( $catPage, 'doUserEditContent' ) ) {
						// MW 1.36+
						$catPage->doUserEditContent( $content, $editor, $summary, EDIT_NEW & EDIT_SUPPRESS_RC );
					} else {
						$catPage->doEditContent( $content, $summary, EDIT_NEW & EDIT_SUPPRESS_RC, false, $editor );
					}
				} catch ( MWException $e ) {
					/* fail silently...
					* todo: what can go wrong here? */
				}
			}
		}

		return true;
	}

	/**
	 * @param string[] &$names
	 */
	public static function onUserGetReservedNames( &$names ) {
		$names[] = 'msg:autocreatecategorypages-editor';
	}
}
