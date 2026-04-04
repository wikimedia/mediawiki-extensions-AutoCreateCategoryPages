<?php

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\IConnectionProvider;

class AutoCreateCategoryPages implements
	\MediaWiki\Storage\Hook\PageSaveCompleteHook,
	\MediaWiki\User\Hook\UserGetReservedNamesHook
{

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly UserFactory $userFactory,
		private readonly WikiPageFactory $wikiPageFactory,
	) {
	}

	/**
	 * Get an array of existing categories on this page, with the unprefixed name
	 *
	 * @param array $page_cats
	 *
	 * @return array
	 */
	private function getExistingCategories( $page_cats ) {
		if ( empty( $page_cats ) ) {
			return [];
		}
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$res = $dbr->select(
			'page',
			'page_title',
			[
				'page_namespace' => NS_CATEGORY,
				'page_title' => $page_cats
			],
			__METHOD__
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
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		global $wgAutoCreateCategoryStub;

		// Get a ParserOutput
		$parser_out = $wikiPage->getParserOutput();

		$page_cats = $parser_out->getCategoryNames();
		$existing_cats = $this->getExistingCategories( $page_cats );

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
			$editor = $this->userFactory->newFromName(
				wfMessage( 'autocreatecategorypages-editor' )->inContentLanguage()->text()
			);
			if ( !$editor->isRegistered() ) {
				$editor->addToDatabase();
			}

			$summary = wfMessage( 'autocreatecategorypages-createdby' )->inContentLanguage()->text();

			foreach ( $new_cats as $cat ) {
				$catTitle = Title::newFromDBkey( $cat )->getText();
				$stub = ( $wgAutoCreateCategoryStub != null )
					? $wgAutoCreateCategoryStub
					: wfMessage(
						'autocreatecategorypages-stub',
						$catTitle
					)->inContentLanguage()->text();

				$safeTitle = Title::makeTitleSafe( NS_CATEGORY, $cat );
				$catPage = $this->wikiPageFactory->newFromTitle( $safeTitle );
				try {
					$content = ContentHandler::makeContent( $stub, $safeTitle );
					$catPage->doUserEditContent( $content, $editor, $summary, EDIT_NEW & EDIT_SUPPRESS_RC );
				} catch ( MWException $e ) {
					/* fail silently...
					* todo: what can go wrong here? */
				}
			}
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onUserGetReservedNames( &$reservedUsernames ) {
		$reservedUsernames[] = 'msg:autocreatecategorypages-editor';
	}

}
