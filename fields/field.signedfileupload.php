<?php
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/fields/field.upload.php');


	Class FieldSignedFileUpload extends FieldUpload {
	
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Signed File Upload';
		}
		
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');
			
			if (preg_match('/^mimetype:/', $data[0])) {
				$data[0] = str_replace('mimetype:', '', $data[0]);
				$column = 'mimetype';
				
			} else if (preg_match('/^size:/', $data[0])) {
				$data[0] = str_replace('size:', '', $data[0]);
				$column = 'size';
				
			} else if (preg_match('/^signature:/', $data[0])) {
				$data[0] = str_replace('signature:', '', $data[0]);
				$column = 'signature';
	
			} else {
				$column = 'file';
			}
			
			if (self::isFilterRegex($data[0])) {
				$this->_key++;
				$pattern = str_replace('regexp:', '', $this->cleanValue($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.{$column} REGEXP '{$pattern}'
				";
				
			} elseif ($andOperation) {
				foreach ($data as $value) {
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND t{$field_id}_{$this->_key}.{$column} = '{$value}'
					";
				}
				
			} else {
				if (!is_array($data)) $data = array($data);
				
				foreach ($data as &$value) {
					$value = $this->cleanValue($value);
				}
				
				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.{$column} IN ('{$data}')
				";
			}
			
			return true;
		}
		
		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			if(!$flagWithError && !is_writable(DOCROOT . $this->get('destination') . '/')) 
				$flagWithError = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
			
            $label = Widget::Label( $this->get('label') );
			$class = 'file';
			$label->setAttribute('class', $class);
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
			
			$span = new XMLElement('span');
			if($data['file']) $span->appendChild(Widget::Anchor('/workspace' . $data['file'], URL . '/workspace' . $data['file']));
			
			$span->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, $data['file'], ($data['file'] ? 'hidden' : 'file')));

			$label->appendChild($span);
			
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
			
		}

		function commit(){
			
			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			$fields['destination'] = $this->get('destination');
			$fields['validator'] = ($fields['validator'] == 'custom' ? NULL : $this->get('validator'));
			$fields['sslkey'] = $this->get('sslkey');
			
			$this->_engine->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");		
			return $this->_engine->Database->insert($fields, 'tbl_fields_' . $this->handle());
					
		}		
		
		function appendFormattedElement(&$wrapper, $data){
			$item = new XMLElement($this->get('element_name'));
			
			$item->setAttributeArray(array(
				'size' => General::formatFilesize(filesize(WORKSPACE . $data['file'])),
			 	'path' => str_replace(WORKSPACE, NULL, dirname(WORKSPACE . $data['file'])),
				'type' => $data['mimetype'],
				'signature' => $data['signature']
			));
			
			$item->appendChild(new XMLElement('filename', General::sanitize(basename($data['file']))));
						
			$m = unserialize($data['meta']);
			
			if(is_array($m) && !empty($m)){
				$item->appendChild(new XMLElement('meta', NULL, $m));
			}
					
			$wrapper->appendChild($item);
		}
		
		function displaySettingsPanel(&$wrapper, $errors = null){
			parent::displaySettingsPanel($wrapper, $errors);
			
            $sslkey_label = Widget::Label(__('Private DSA Key'));
            $sslkey_label->appendChild( Widget::Textarea('fields['.$this->get('sortorder').'][sslkey]', 12, 50, $this->get('sslkey'), array('class' => 'code') ));
            $wrapper->appendChild($sslkey_label);
			
		}
		
		public function checkPostFieldData($data, &$message, $entry_id = NULL) {
			if (is_array($data) and isset($data['name'])) $data['name'] = $this->getUniqueFilename($data['name']);
			return parent::checkPostFieldData($data, $message, $entry_id);
		}
				
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			if (is_array($data) and isset($data['name'])) $data['name'] = $this->getUniqueFilename($data['name']);
			
			$status = self::__OK__;
			
			## Its not an array, so just retain the current data and return
			if(!is_array($data)) {
				
				$status = self::__OK__;

				// Do a simple reconstruction of the file meta information. This is a workaround for
				// bug which causes all meta information to be dropped
				return array(
					'file' => $data,
					'mimetype' => self::getMIMEType($data),
					'size' => filesize(WORKSPACE . $data),
					'meta' => serialize(self::getMetaInfo(WORKSPACE . $data, self::getMIMEType($data))),
					'signature' => self::signatureForFilename(WORKSPACE . $data)
				);
	
			}

			if($simulate) return;
			
			if($data['error'] == UPLOAD_ERR_NO_FILE || $data['error'] != UPLOAD_ERR_OK) return;
			
			## Sanitize the filename
			$data['name'] = Lang::createFilename($data['name']);
			
			## Upload the new file
			$abs_path = DOCROOT . '/' . trim($this->get('destination'), '/');
			$rel_path = str_replace('/workspace', '', $this->get('destination'));
			
			if(!General::uploadFile($abs_path, $data['name'], $data['tmp_name'], $this->_engine->Configuration->get('write_mode', 'file'))) {
				
				$message = __('There was an error while trying to upload the file <code>%1$s</code> to the target directory <code>%2$s</code>.', array($data['name'], 'workspace/'.ltrim($rel_path, '/')));
				$status = self::__ERROR_CUSTOM__;
				return;
			}

			if ($entry_id) {
    			$row = $this->Database->fetchRow(0, "SELECT * FROM `tbl_entries_data_".$this->get('id')."` WHERE `entry_id` = '$entry_id' LIMIT 1");
    			$existing_file = $abs_path . '/' . basename($row['file']);
    
    			General::deleteFile($existing_file);
			}

			$status = self::__OK__;
			
			$file = rtrim($rel_path, '/') . '/' . trim($data['name'], '/');
			
			$data['signature'] = self::signatureForFilename(WORKSPACE . $file);
			
            ## If browser doesn't send MIME type (e.g. .flv in Safari)
            if (strlen(trim($data['type'])) == 0){
                $data['type'] = 'unknown';
            }
			
			return array(
				'file' => $file,
				'size' => $data['size'],
				'mimetype' => $data['type'],
				'meta' => serialize(self::getMetaInfo(WORKSPACE . $file, $data['type'])),
				'signature' => $data['signature']
			);
		}
		
		private static function getMIMEType($file) {
			
			$imageMimeTypes = array(
				'image/gif',
				'image/jpg',
				'image/jpeg',
				'image/png',
			);
			
			if(in_array('image/' . General::getExtension($file), $imageMimeTypes)) return 'image/' . General::getExtension($file);
			
			return 'unknown';
		}		

		function createTable() {
			return $this->_engine->Database->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
                    `id` int(11) unsigned NOT NULL auto_increment,
                    `entry_id` int(11) unsigned NOT NULL,
                    `file` varchar(255) default NULL,
                    `size` int(11) unsigned NOT NULL,
                    `mimetype` varchar(50) default NULL,
                    `meta` varchar(255) default NULL,
                    `signature` varchar(255) default NULL,
                    PRIMARY KEY  (`id`),
                    KEY `entry_id` (`entry_id`),
                    KEY `file` (`file`),
                    KEY `mimetype` (`mimetype`)
				) TYPE=MyISAM;");
		}

		private function signatureForFilename($filename) {   	        
	        $dsa_key_tmpfile_path = tempnam(TMP, 'dsakey_');
	        $dsa_key_tmpfile = fopen($dsa_key_tmpfile_path, 'w');
	        fwrite($dsa_key_tmpfile, $this->get('sslkey'));
	        fseek($dsa_key_tmpfile, 0);
			$file_signature = shell_exec('openssl dgst -sha1 -binary < "'.$filename.'" | openssl dgst -dss1 -sign "'.$dsa_key_tmpfile_path.'" | openssl enc -base64');
			fclose($dsa_key_tmpfile);			
			return trim($file_signature);
		}
		
		private function getUniqueFilename($filename) {
			// since unix timestamp is 10 digits, the unique filename will be limited to ($crop+1+10) characters;
			$crop  = '33';
			return preg_replace("/(.*)(\.[^\.]+)/e", "substr('$1', 0, $crop).'-'.time().'$2'", $filename);
		}

	}

?>