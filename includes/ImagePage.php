<?php
/**
 * @package MediaWiki
 */

/**
 *
 */
if( !defined( 'MEDIAWIKI' ) )
	die();

require_once( 'Image.php' );

/**
 * Special handling for image description pages
 * @package MediaWiki
 */
class ImagePage extends Article {

	/* private */ var $img;  // Image object this page is shown for
	
	function view() {
		global $wgUseExternalEditor, $wgOut ;

		$this->img  = new Image( $this->mTitle );

		if( $this->mTitle->getNamespace() == NS_IMAGE  ) {
			$this->openShowImage();
			if ( $this->img->exists() ) $this->showEXIFdata();
			
			# No need to display noarticletext, we use our own message, output in openShowImage()
			if ( $this->getID() ) {
				Article::view();
			} else {
				# Just need to set the right headers
				$wgOut->setArticleFlag( true );
				$wgOut->setRobotpolicy( 'index,follow' );
				$wgOut->setPageTitle( $this->mTitle->getPrefixedText() );
				$wgOut->addMetaTags();
				$this->viewUpdates();
			}
			
			if ( $this->img->exists() ) {
				$this->uploadNewVersionLink();
				if ( $wgUseExternalEditor && $this->img->exists() ) {
					$this->externalEditorLink();
				}
			}
			$this->closeShowImage();
			$this->imageHistory();
			$this->imageLinks();
		} else {
			Article::view();
		}
	}
	
	function showEXIFdata() {
		global $wgOut, $wgShowEXIF;
		if ( ! $wgShowEXIF ) return;

		# Get the EXIF data
		$exif = $this->img->getExifData();
		if ( count ( $exif ) == 0 ) return; # No EXIF data available
		
		# Create the table
		$r = "{| class=exif\n" ;
		$r .= '|+ ' . wfMsg( 'exifdata' ) . "\n|-\n" ;
		foreach ( $exif as $k => $v ) {
			$r .= "! $k\n";
			$r .= '| ' . htmlspecialchars($v) . "\n|-\n";
		}
		$wgOut->addWikiText( substr($r, 0, -3) . '|}' );
	}

	function openShowImage()
	{
		global $wgOut, $wgUser, $wgImageLimits, $wgRequest, 
		       $wgUseImageResize, $wgRepositoryBaseUrl, 
		       $wgUseExternalEditor, $wgServer;
		$full_url  = $this->img->getViewURL();
		$anchoropen = '';
		$anchorclose = '';

		if( $wgUser->getOption( 'imagesize' ) == '' ) {
			$sizeSel = User::getDefaultOption( 'imagesize' );
		} else {
			$sizeSel = IntVal( $wgUser->getOption( 'imagesize' ) );
		}
		if( !isset( $wgImageLimits[$sizeSel] ) ) {
			$sizeSel = User::getDefaultOption( 'imagesize' );
		}
		$max = $wgImageLimits[$sizeSel];
		$maxWidth = $max[0];
		$maxHeight = $max[1];
		$sk = $wgUser->getSkin();

		if ( $this->img->exists() ) {
			if ( $this->img->getType() != '' ) {
				# image
				$width = $this->img->getWidth();
				$height = $this->img->getHeight();
				$msg = wfMsg('showbigimage', $width, $height, intval( $this->img->getSize()/1024 ) );
				if ( $width > $maxWidth ) {
					$height = floor( $height * $maxWidth / $width );
					$width  = $maxWidth;
				} 
				if ( $height > $maxHeight ) {
					$width = floor( $width * $maxHeight / $height );
					$height = $maxHeight;
				}
				if ( $width != $this->img->getWidth() || $height != $this->img->getHeight() ) {
					if( $wgUseImageResize ) {
						$thumbnail = $this->img->getThumbnail( $width );
						$url = $thumbnail->getUrl();
					} else {
						# No resize ability? Show the full image, but scale
						# it down in the browser so it fits on the page.
						$url = $full_url;
					}
					$anchoropen  = "<a href=\"{$full_url}\">";
					$anchorclose = "</a><br />\n$anchoropen{$msg}</a>";
				} else {
					$url = $full_url;
				}
				$s = '<div class="fullImageLink">' . $anchoropen .
				     "<img border=\"0\" src=\"{$url}\" width=\"{$width}\" height=\"{$height}\" alt=\"" .
				     htmlspecialchars( $wgRequest->getVal( 'image' ) ).'" />' . $anchorclose . '</div>';
			} else {
				$s = "<div class=\"fullMedia\">" . $sk->makeMediaLink( $this->img->getName(),'' ) . '</div>';
			}
			$wgOut->addHTML( $s );
			if($this->img->fromSharedDirectory) {
				$sharedtext="<div class=\"sharedUploadNotice\">" . wfMsg("sharedupload");
				if($wgRepositoryBaseUrl) {
					$sharedtext .= " ". wfMsg("shareduploadwiki",$wgRepositoryBaseUrl . urlencode($this->mTitle->getDBkey()));
				}
				$sharedtext.="</div>";
				$wgOut->addWikiText($sharedtext);
			}
			
		} else {
			# Image does not exist
			$wgOut->addWikiText( wfMsg( 'noimage', $this->getUploadUrl() ) );
		}
	}
	
	function getUploadUrl() {
		global $wgServer;
		$uploadTitle = Title::makeTitle( NS_SPECIAL, 'Upload' );
		return $wgServer . $uploadTitle->getLocalUrl( 'wpDestFile=' . urlencode( $this->img->getName() ) );
	}


	function uploadNewVersionLink() {
		global $wgOut;
		$wgOut->addHTML("<div class='editExternally'>");
		$wgOut->addWikiText( wfMsg( 'uploadnewversion', $this->getUploadUrl() ) );
		$wgOut->addHTML("</div><br clear='both' />");
	}

	function externalEditorLink()
	{
		global $wgUser,$wgOut;
		$sk = $wgUser->getSkin();
		$wgOut->addHTML( '<div class="editExternally">' );
		$wgOut->addHTML( $sk->makeKnownLinkObj( $this->mTitle,
			wfMsg( 'edit-externally' ),
			"action=edit&externaledit=true&mode=file" ) );
		$wgOut->addWikiText( '<div class="editExternallyHelp">' .
			wfMsg('edit-externally-help') . '</div>' );
		$wgOut->addHTML( '</div><br clear="all" />' );
	}
	
	function closeShowImage()
	{
		# For overloading
			
	}

	/**
	 * If the page we've just displayed is in the "Image" namespace,
	 * we follow it with an upload history of the image and its usage.
	 */
	function imageHistory()
	{
		global $wgUser, $wgOut;

		$sk = $wgUser->getSkin();

		$line = $this->img->nextHistoryLine();

		if ( $line ) {
			$list =& new ImageHistoryList( $sk );
			$s = $list->beginImageHistoryList() .
				$list->imageHistoryLine( true, $line->img_timestamp,
					$this->mTitle->getDBkey(),  $line->img_user,
					$line->img_user_text, $line->img_size, $line->img_description );

			while ( $line = $this->img->nextHistoryLine() ) {
				$s .= $list->imageHistoryLine( false, $line->img_timestamp,
			  	$line->oi_archive_name, $line->img_user,
			  	$line->img_user_text, $line->img_size, $line->img_description );
			}
			$s .= $list->endImageHistoryList();
		} else { $s=''; }
		$wgOut->addHTML( $s );
	}

	function imageLinks()
	{
		global $wgUser, $wgOut;

		$wgOut->addHTML( '<h2>' . wfMsg( 'imagelinks' ) . "</h2>\n" );

		$dbr =& wfGetDB( DB_SLAVE );
		$page = $dbr->tableName( 'page' );
		$imagelinks = $dbr->tableName( 'imagelinks' );
		
		$sql = "SELECT page_namespace,page_title FROM $imagelinks,$page WHERE il_to=" .
		  $dbr->addQuotes( $this->mTitle->getDBkey() ) . " AND il_from=page_id"
		  . " LIMIT 500"; # quickie emergency brake
		$res = $dbr->query( $sql, "ImagePage::imageLinks" );

		if ( 0 == $dbr->numRows( $res ) ) {
			$wgOut->addHtml( '<p>' . wfMsg( "nolinkstoimage" ) . "</p>\n" );
			return;
		}
		$wgOut->addHTML( '<p>' . wfMsg( 'linkstoimage' ) .  "</p>\n<ul>" );

		$sk = $wgUser->getSkin();
		while ( $s = $dbr->fetchObject( $res ) ) {
			$name = Title::MakeTitle( $s->page_namespace, $s->page_title );
			$link = $sk->makeKnownLinkObj( $name, "" );
			$wgOut->addHTML( "<li>{$link}</li>\n" );
		}
		$wgOut->addHTML( "</ul>\n" );
	}

	function delete()
	{
		global $wgUser, $wgOut, $wgRequest;

		$confirm = $wgRequest->getBool( 'wpConfirmB' );
		$image = $wgRequest->getVal( 'image' );
		$oldimage = $wgRequest->getVal( 'oldimage' );
		
		# Only sysops can delete images. Previously ordinary users could delete 
		# old revisions, but this is no longer the case.
		if ( !$wgUser->isAllowed('delete') ) {
			$wgOut->sysopRequired();
			return;
		}
		if ( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}

		# Better double-check that it hasn't been deleted yet!
		$wgOut->setPagetitle( wfMsg( 'confirmdelete' ) );
		if ( ( !is_null( $image ) )
		  && ( '' == trim( $image ) ) ) {
			$wgOut->fatalError( wfMsg( 'cannotdelete' ) );
			return;
		}
		
		$this->img  = new Image( $this->mTitle );
		
		# Deleting old images doesn't require confirmation
		if ( !is_null( $oldimage ) || $confirm ) {
			if( $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ), $oldimage ) ) {
				$this->doDelete();
			} else {
				$wgOut->fatalError( wfMsg( 'sessionfailure' ) );
			}
			return;
		}
		
		if ( !is_null( $image ) ) {
			$q = '&image=' . urlencode( $image );
		} else if ( !is_null( $oldimage ) ) {
			$q = '&oldimage=' . urlencode( $oldimage );
		} else {
			$q = '';
		}
		return $this->confirmDelete( $q, $wgRequest->getText( 'wpReason' ) );
	}

	function doDelete()
	{
		global $wgOut, $wgUser, $wgContLang, $wgRequest;
		global $wgUseSquid, $wgInternalServer, $wgPostCommitUpdateList;
		$fname = 'ImagePage::doDelete';

		$reason = $wgRequest->getVal( 'wpReason' );
		$oldimage = $wgRequest->getVal( 'oldimage' );
		
		$dbw =& wfGetDB( DB_MASTER );

		if ( !is_null( $oldimage ) ) {
			if ( strlen( $oldimage ) < 16 ) {
				$wgOut->unexpectedValueError( 'oldimage', htmlspecialchars($oldimage) );
				return;
			}
			if ( strstr( $oldimage, "/" ) || strstr( $oldimage, "\\" ) ) {
				$wgOut->unexpectedValueError( 'oldimage', htmlspecialchars($oldimage) );
				return;
			}
			
			# Invalidate description page cache
			$this->mTitle->invalidateCache();

			# Squid purging
			if ( $wgUseSquid ) {
				$urlArr = Array(
					$wgInternalServer.wfImageArchiveUrl( $oldimage ),
					$wgInternalServer.$this->mTitle->getFullURL()
				);
				wfPurgeSquidServers($urlArr);
			}
			$this->doDeleteOldImage( $oldimage );
			$dbw->delete( 'oldimage', array( 'oi_archive_name' => $oldimage ) );
			$deleted = $oldimage;
		} else {
			$image = $this->mTitle->getDBkey();
			$dest = wfImageDir( $image );
			$archive = wfImageDir( $image );
			
			# Delete the image file if it exists; due to sync problems
			# or manual trimming sometimes the file will be missing.
			$targetFile = "{$dest}/{$image}";
			if( file_exists( $targetFile ) && ! @unlink( $targetFile ) ) {
				# If the deletion operation actually failed, bug out:
				$wgOut->fileDeleteError( $targetFile );
				return;
			}
			$dbw->delete( 'image', array( 'img_name' => $image ) );
			$res = $dbw->select( 'oldimage', array( 'oi_archive_name' ), array( 'oi_name' => $image ) );			

			# Purge archive URLs from the squid
			$urlArr = Array();
			while ( $s = $dbw->fetchObject( $res ) ) {
				$this->doDeleteOldImage( $s->oi_archive_name );
				$urlArr[] = $wgInternalServer.wfImageArchiveUrl( $s->oi_archive_name );
			}	

			# And also the HTML of all pages using this image
			$linksTo = $this->img->getLinksTo();
			if ( $wgUseSquid ) {
				$u = SquidUpdate::newFromTitles( $linksTo, $urlArr );
				array_push( $wgPostCommitUpdateList, $u );
			}
			
			$dbw->delete( 'oldimage', array( 'oi_name' => $image ) );

			# Image itself is now gone, and database is cleaned.
			# Now we remove the image description page.

			$article = new Article( $this->mTitle );
			$article->doDeleteArticle( $reason ); # ignore errors

			# Invalidate parser cache and client cache for pages using this image
			# This is left until relatively late to reduce lock time
			Title::touchArray( $linksTo );

			/* Delete thumbnails and refresh image metadata cache */
			$this->img->purgeCache();


			$deleted = $image;
		}

		$wgOut->setPagetitle( wfMsg( 'actioncomplete' ) );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );

		$sk = $wgUser->getSkin();
		$loglink = $sk->makeKnownLinkObj(
			Title::makeTitle( NS_SPECIAL, 'Log/delete' ),
			wfMsg( 'deletionlog' ) );

		$text = wfMsg( 'deletedtext', $deleted, $loglink );

		$wgOut->addHTML( '<p>' . $text . "</p>\n" );
		$wgOut->returnToMain( false, $this->mTitle->getPrefixedText() );
	}

	function doDeleteOldImage( $oldimage )
	{
		global $wgOut;

		$name = substr( $oldimage, 15 );
		$archive = wfImageArchiveDir( $name );
		
		# Delete the image if it exists. Sometimes the file will be missing
		# due to manual intervention or weird sync problems; treat that
		# condition gracefully and continue to delete the database entry.
		# Also some records may end up with an empty oi_archive_name field
		# if the original file was missing when a new upload was made;
		# don't try to delete the directory then!
		#
		$targetFile = "{$archive}/{$oldimage}";
		if( $oldimage != '' && file_exists( $targetFile ) && !@unlink( $targetFile ) ) {
			# If we actually have a file and can't delete it, throw an error.
			$wgOut->fileDeleteError( "{$archive}/{$oldimage}" );
		} else {
			# Log the deletion
			$log = new LogPage( 'delete' );
			$log->addEntry( 'delete', $this->mTitle, wfMsg('deletedrevision',$oldimage) );
		}
	}

	function revert()
	{
		global $wgOut, $wgRequest, $wgUser;
		global $wgUseSquid, $wgInternalServer, $wgDeferredUpdateList;

		$oldimage = $wgRequest->getText( 'oldimage' );
		if ( strlen( $oldimage ) < 16 ) {
			$wgOut->unexpectedValueError( 'oldimage', htmlspecialchars($oldimage) );
			return;
		}
		if ( strstr( $oldimage, "/" ) || strstr( $oldimage, "\\" ) ) {
			$wgOut->unexpectedValueError( 'oldimage', htmlspecialchars($oldimage) );
			return;
		}

		if ( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}
		if( $wgUser->isAnon() ) {
			$wgOut->errorpage( 'uploadnologin', 'uploadnologintext' );
			return;
		}
		if ( ! $this->mTitle->userCanEdit() ) {
			$wgOut->sysopRequired();
			return;
		}
		if( !$wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ), $oldimage ) ) {
			$wgOut->errorpage( 'internalerror', 'sessionfailure' );
			return;
		}		
		$name = substr( $oldimage, 15 );

		$dest = wfImageDir( $name );
		$archive = wfImageArchiveDir( $name );
		$curfile = "{$dest}/{$name}";

		if ( ! is_file( $curfile ) ) {
			$wgOut->fileNotFoundError( htmlspecialchars( $curfile ) );
			return;
		}
		$oldver = wfTimestampNow() . "!{$name}";
		
		$dbr =& wfGetDB( DB_SLAVE );
		$size = $dbr->selectField( 'oldimage', 'oi_size', array( 'oi_archive_name' => $oldimage )  );

		if ( ! rename( $curfile, "${archive}/{$oldver}" ) ) {
			$wgOut->fileRenameError( $curfile, "${archive}/{$oldver}" );
			return;
		}
		if ( ! copy( "{$archive}/{$oldimage}", $curfile ) ) {
			$wgOut->fileCopyError( "${archive}/{$oldimage}", $curfile );
		}

		# Record upload and update metadata cache
		$img = Image::newFromName( $name );
		$img->recordUpload( $oldver, wfMsg( "reverted" ) );

		$wgOut->setPagetitle( wfMsg( 'actioncomplete' ) );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->addHTML( wfMsg( 'imagereverted' ) );

		$descTitle = $img->getTitle();
		$wgOut->returnToMain( false, $descTitle->getPrefixedText() );
	}
}

/**
 * @todo document
 * @package MediaWiki
 */
class ImageHistoryList {
	function ImageHistoryList( &$skin ) {
		$this->skin =& $skin;
	}
	
	function beginImageHistoryList() {
		$s = "\n<h2>" . wfMsg( 'imghistory' ) . "</h2>\n" .
		  "<p>" . wfMsg( 'imghistlegend' ) . "</p>\n".'<ul class="special">';
		return $s;
	}

	function endImageHistoryList() {
		$s = "</ul>\n";
		return $s;
	}

	function imageHistoryLine( $iscur, $timestamp, $img, $user, $usertext, $size, $description ) {
		global $wgUser, $wgLang, $wgContLang, $wgTitle;

		$datetime = $wgLang->timeanddate( $timestamp, true );
		$del = wfMsg( 'deleteimg' );
		$delall = wfMsg( 'deleteimgcompletely' );
		$cur = wfMsg( 'cur' );

		if ( $iscur ) {
			$url = Image::imageUrl( $img );
			$rlink = $cur;
			if ( $wgUser->isAllowed('delete') ) {
				$link = $wgTitle->escapeLocalURL( 'image=' . $wgTitle->getPartialURL() .
				  '&action=delete' );
				$style = $this->skin->getInternalLinkAttributes( $link, $delall );

				$dlink = '<a href="'.$link.'"'.$style.'>'.$delall.'</a>';
			} else {
				$dlink = $del;
			}
		} else {
			$url = htmlspecialchars( wfImageArchiveUrl( $img ) );
			if( $wgUser->getID() != 0 && $wgTitle->userCanEdit() ) {
				$token = urlencode( $wgUser->editToken( $img ) );
				$rlink = $this->skin->makeKnownLinkObj( $wgTitle,
				           wfMsg( 'revertimg' ), 'action=revert&oldimage=' .
				           urlencode( $img ) . "&wpEditToken=$token" );
				$dlink = $this->skin->makeKnownLinkObj( $wgTitle,
				           $del, 'action=delete&oldimage=' . urlencode( $img ) .
				           "&wpEditToken=$token" );
			} else {
				# Having live active links for non-logged in users
				# means that bots and spiders crawling our site can
				# inadvertently change content. Baaaad idea.
				$rlink = wfMsg( 'revertimg' );
				$dlink = $del;
			}
		}
		if ( 0 == $user ) {
			$userlink = $usertext;
		} else {
			$userlink = $this->skin->makeLinkObj(
				Title::makeTitle( NS_USER, $usertext ),
				$usertext );
		}
		$nbytes = wfMsg( 'nbytes', $size );
		$style = $this->skin->getInternalLinkAttributes( $url, $datetime );

		$s = "<li> ({$dlink}) ({$rlink}) <a href=\"{$url}\"{$style}>{$datetime}</a>"
		  . " . . {$userlink} ({$nbytes})";

		$s .= $this->skin->commentBlock( $description, $wgTitle );
		$s .= "</li>\n";
		return $s;
	}

}


?>
