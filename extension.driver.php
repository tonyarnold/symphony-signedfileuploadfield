<?php
	define('KEYFILE', MANIFEST . '/signedfileuploadkey.pem');

	Class extension_signedFileUploadField extends Extension{

		public function about() {
			return array(
				'name'			=> 'Field: Signed File Upload',
				'version'		=> '1.0',
				'release-date'	=> '2009-05-05',
				'author'		=> array(
					'name'			=> 'Tony Arnold',
					'website'		=> 'http://tonyarnold.com/',
					'email'			=> 'tony@tonyarnold.com'
				)
			);
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'savePreferences'
				),
			);
		}

		public function savePreferences($context){
			$this->saveSignature(trim($_POST['signeduploadfield']['signature-key']));
		}

		public function appendPreferences($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Signed File Uploads'));
			$label = Widget::Label('Private Key');
			$label->appendChild(Widget::Textarea('signeduploadfield[signature-key]', 12, 50, $this->signature(), array('class' => 'code')));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'Paste your private SSL key into this field. Trailing or preceding white space will be removed automatically.', array('class' => 'help')));
			$context['wrapper']->appendChild($group);
		}

		public function uninstall() {
			if(file_exists(KEYFILE)) { 
			    unlink(KEYFILE);
			}
			
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_signedfileupload`");
		}

		public function install() {
			return $this->_Parent->Database->query("CREATE TABLE IF NOT EXISTS `tbl_fields_signedfileupload` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `destination` varchar(255) NOT NULL,
				  `validator` varchar(50) default NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) TYPE=MyISAM");
		}
		
		public function saveSignature($string){
			return @file_put_contents(KEYFILE, $string);
		}
		
		public function signature(){
			return @file_get_contents(KEYFILE);
		}

		public function signatureForFilename($filename) {
			return shell_exec('openssl dgst -sha1 -binary < "'.$filename.'" | openssl dgst -dss1 -sign "'.KEYFILE.'" | openssl enc -base64');
		}
	}

?>