<?php

/*	This software is the unpublished, confidential, proprietary, intellectual
	property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
	or used in any manner without expressed written consent from Kim David Software, LLC.
	Kim David Software, LLC owns all rights to this work and intends to keep this
	software confidential so as to maintain its value as a trade secret.

	Copyright 2004-Present, Kim David Software, LLC.

	WARNING! This code is part of the Kim David Software's Coreware system.
	Changes made to this source file will be lost when new versions of the
	system are installed.
*/

/*
%module:image_album:album_code=album_code:wrapper_element_id=element_id%
*/

class ImageAlbumPageModule extends PageModule {
	function createContent() {
		$resultSet = executeQuery("select * from images join album_images using (image_id) where album_id = (select album_id from albums where album_code = ? and client_id = ?) order by sequence_number", $this->iParameters['album_code'], $GLOBALS['gClientId']);
		?>
        <div id="<?= $this->iParameters['wrapper_element_id'] ?>">
			<?php
			while ($row = getNextRow($resultSet)) {
				if (empty($row['link_url'])) {
					?>
                    <div><a rel="prettyPhoto[album_<?= $this->iParameters['album_code'] ?>]" href='<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>'><img class='defer-load' data-image_source="<?= getImageFilename($row['image_id'],array("use_cdn"=>true,"image_type"=>"small")) ?>"></a></div>
				<?php } else { ?>
                    <div><a href='<?= $row['link_url'] ?>'><img class='defer-load' data-image_source="<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>"></a></div>
					<?php
				}
			}
			?>
        </div>
		<?php
	}
}
