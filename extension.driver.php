<?php

	Class extension_signedFileUploadField extends Extension{

		public function about() {
			return array(
				'name'			=> 'Field: Signed File Upload',
				'version'		=> '1.03',
				'release-date'	=> '2010-06-15',
				'author'		=> array(
					'name'			=> 'Tony Arnold',
					'website'		=> 'http://tonyarnold.com/',
					'email'			=> 'tony@tonyarnold.com'
				)
			);
		}

		public function uninstall() {			
			Symphony::Database()->query("DROP TABLE `tbl_fields_signedfileupload`");
		}

		public function install() {
			return Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_fields_signedfileupload` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `destination` varchar(255) NOT NULL,
				  `validator` varchar(50) default NULL,
				  `sslkey` text(2048) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `field_id` (`field_id`))");
		}
		
    	public function update($previousVersion){
			if(version_compare($previousVersion, '1.0', '<=')){
        		if(file_exists(MANIFEST . '/signedfileuploadkey.pem')) { 
    			    unlink(MANIFEST . '/signedfileuploadkey.pem');
    			}
    			
				Symphony::Database()->query("ALTER TABLE `tbl_fields_signedfileupload` ADD `sslkey` text(2048) NOT NULL");
			}			

			return true;
		}
	}

?>