<?php

class UniwikiAutoCreateCategoryPages {
		/**
	 * Get an array of existing categories, with the name in the key and sort key in the value.
	 *
	 * @return array
	 */
	private function getExistingCategories() {
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
	public function UW_AutoCreateCategoryPages_Save ( &$article, &$user, $text, $summary, $minoredit, $watchthis, $sectionanchor, &$flags, $revision, &$status, $baseRevId ) {
		global $wgDBprefix, $wgAutoCreateCategoryStub;

		// Extract the categories on this page
		//$article->getParserOptions();
		$page_cats = $article->getParserOutput( $article->makeParserOptions( $user ) )->getCategories();
		$page_cats = array_keys( $page_cats );	// Because we get a lame array back
		$existing_cats = $this->getExistingCategories();
		
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

				$catPage = new Article( Title::makeTitleSafe( NS_CATEGORY, $cat ) );
				try {
					$catPage->doEdit ( $stub, $summary, EDIT_NEW & EDIT_SUPPRESS_RC, false, $editor );

				} catch ( MWException $e ) {
					/* fail silently...
					* todo: what can go wrong here? */
				}
			}
		}

		return true;
	}

	public function UW_OnUserGetReservedNames( &$names ) {
		
		$names[] = 'msg:autocreatecategorypages-editor';
		return true;
	}
}
