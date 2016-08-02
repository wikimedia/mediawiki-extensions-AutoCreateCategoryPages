<?php

class AutoCreateCategoryPages {
		/**
	 * Get an array of existing categories, with the name in the key and sort key in the value.
	 *
	 * @return array
	 */
	static function getExistingCategories() {
		// TODO: cache this. Probably have to add to said cache every time a category page is created, by us or manually
		$dbr = wfGetDB ( DB_SLAVE );
		$res = $dbr->select( 'page', 'page_title', array( 'page_namespace' => NS_CATEGORY ) );
		
		$categories = array();
		foreach( $res as $row ) {
			$categories[] = $row->page_title;
		}
		
		return $categories;
	}

	/* after the page is saved, get all the categories
	 * and see if they exist as "proper" pages; if not,
	 * create a simple page for them automatically 
	 */

	public static function onPageContentSaveComplete(
		WikiPage $article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId
	) {

		global $wgAutoCreateCategoryStub;

		// Extract the categories on this page
		//$article->getParserOptions();
		$page_cats = $article->getParserOutput( $article->makeParserOptions( $user ) )->getCategories();
		// array keys will cast numeric category names to ints
		// so we need to cast them back to strings to avoid potentially breaking things!
		$page_cats = array_map( 'strval', array_keys( $page_cats ) );
		$existing_cats = self::getExistingCategories();
		
		// Determine which categories on page do not exist
		$new_cats = array_diff( $page_cats, $existing_cats );
		
		if( count( $new_cats ) > 0 ) {
			// Create a user object for the editing user and add it to the database
			// if it is not there already
			$editor = User::newFromName( wfMessage( 'autocreatecategorypages-editor' )->inContentLanguage()->text() );
			if ( !$editor->isLoggedIn() ) {
				$editor->addToDatabase();
			}
			
			$summary = wfMessage( 'autocreatecategorypages-createdby' )->inContentLanguage()->text();

			foreach( $new_cats as $cat ) {
				$catTitle = Title::newFromDBkey ( $cat )->getText();
				$stub = ( $wgAutoCreateCategoryStub != null ) ? 
						$wgAutoCreateCategoryStub : wfMessage( 'autocreatecategorypages-stub', $catTitle )->inContentLanguage()->text();

				$safeTitle = Title::makeTitleSafe( NS_CATEGORY, $cat );
				$catPage = new WikiPage( $safeTitle  );
				try {
					$content = ContentHandler::makeContent( $stub, $safeTitle );
					$catPage->doEditContent( $content, $summary, EDIT_NEW & EDIT_SUPPRESS_RC, false, $editor );
				} catch ( MWException $e ) {
					/* fail silently...
					* todo: what can go wrong here? */
				}
			}
		}

		return true;
	}

	public static function onUserGetReservedNames( &$names ) {
		$names[] = 'msg:autocreatecategorypages-editor';

		return true;
	}
}
