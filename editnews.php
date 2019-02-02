<?php
/*
=====================================================
 DataLife Engine - by SoftNews Media Group 
-----------------------------------------------------
 http://dle-news.ru/
-----------------------------------------------------
 Copyright (c) 2004-2016 SoftNews Media Group
=====================================================
 Данный код защищен авторскими правами
=====================================================
 Файл: editnews.php
-----------------------------------------------------
 Назначение: AJAX для редакторования
=====================================================
*/

@error_reporting ( E_ALL ^ E_WARNING ^ E_NOTICE );
@ini_set ( 'display_errors', true );
@ini_set ( 'html_errors', false );
@ini_set ( 'error_reporting', E_ALL ^ E_WARNING ^ E_NOTICE );

define( 'DATALIFEENGINE', true );
define( 'ROOT_DIR', substr( dirname(  __FILE__ ), 0, -12 ) );
define( 'ENGINE_DIR', ROOT_DIR . '/engine' );

include ENGINE_DIR . '/data/config.php';

date_default_timezone_set ( $config['date_adjust'] );

if( $config['http_home_url'] == "" ) {
	
	$config['http_home_url'] = explode( "engine/ajax/editnews.php", $_SERVER['PHP_SELF'] );
	$config['http_home_url'] = reset( $config['http_home_url'] );
	$config['http_home_url'] = "http://" . $_SERVER['HTTP_HOST'] . $config['http_home_url'];

}

require_once ENGINE_DIR . '/classes/mysql.php';
require_once ENGINE_DIR . '/data/dbconfig.php';
require_once ENGINE_DIR . '/modules/functions.php';

dle_session();

$_COOKIE['dle_skin'] = trim(totranslit( $_COOKIE['dle_skin'], false, false ));
$_TIME = time ();

if( $_COOKIE['dle_skin'] ) {
	if( @is_dir( ROOT_DIR . '/templates/' . $_COOKIE['dle_skin'] ) ) {
		$config['skin'] = $_COOKIE['dle_skin'];
	}
}

if( $config["lang_" . $config['skin']] ) {

	if ( file_exists( ROOT_DIR . '/language/' . $config["lang_" . $config['skin']] . '/website.lng' ) ) {	
		include_once ROOT_DIR . '/language/' . $config["lang_" . $config['skin']] . '/website.lng';
	} else die("Language file not found");

} else {
	
	include_once ROOT_DIR . '/language/' . $config['langs'] . '/website.lng';

}
$config['charset'] = ($lang['charset'] != '') ? $lang['charset'] : $config['charset'];

@header( "Content-type: text/html; charset=" . $config['charset'] );

require_once ENGINE_DIR . '/classes/parse.class.php';
require_once ENGINE_DIR . '/modules/sitelogin.php';

$parse = new ParseFilter( Array (), Array (), 1, 1 );

if( ! $is_logged ) die( "error" );

$id = intval( $_REQUEST['id'] );

if( ! $id ) die( "error" );

//################# Определение групп пользователей
$user_group = get_vars( "usergroup" );

if( ! $user_group ) {
	$user_group = array ();
	
	$db->query( "SELECT * FROM " . USERPREFIX . "_usergroups ORDER BY id ASC" );
	
	while ( $row = $db->get_row() ) {
		
		$user_group[$row['id']] = array ();
		
		foreach ( $row as $key => $value ) {
			$user_group[$row['id']][$key] = stripslashes($value);
		}
	
	}
	set_vars( "usergroup", $user_group );
	$db->free();
}

if( $_REQUEST['action'] == "edit" ) {
	$row = $db->super_query( "SELECT p.id, p.autor, p.date, p.short_story, p.full_story, p.xfields, p.title, p.category, p.approve, p.allow_br, e.reason FROM " . PREFIX . "_post p LEFT JOIN " . PREFIX . "_post_extras e ON (p.id=e.news_id) WHERE p.id = '$id'" );
	
	if( $id != $row['id'] ) die( "error" );
	
	$cat_list = explode( ',', $row['category'] );
	
	$have_perm = 0;

	if( $user_group[$member_id['user_group']]['allow_edit'] and $row['autor'] == $member_id['name'] ) {
		$have_perm = 1;
	}
	
	if( $user_group[$member_id['user_group']]['allow_all_edit'] ) {
		$have_perm = 1;
		
		$allow_list = explode( ',', $user_group[$member_id['user_group']]['cat_add'] );
		
		foreach ( $cat_list as $selected ) {
			if( $allow_list[0] != "all" and ! in_array( $selected, $allow_list ) ) $have_perm = 0;
		}
	}
	
	if( $user_group[$member_id['user_group']]['max_edit_days'] ) {
		$newstime = strtotime( $row['date'] );
		$maxedittime = $_TIME - ($user_group[$member_id['user_group']]['max_edit_days'] * 3600 * 24);
		if( $maxedittime > $newstime ) $have_perm = 0;
	}
	
	if( ($member_id['user_group'] == 1) ) {
		$have_perm = 1;
	}

	
	if( !$have_perm ) die( $lang['editnews_error'] );

	if( !$user_group[$member_id['user_group']]['allow_html'] ) $config['allow_quick_wysiwyg'] = false;
	
	$news_txt = $row['short_story'];
	$full_txt = $row['full_story'];
	$sess_id = session_id();
	$author = urlencode($row['autor']);

	if( $row['allow_br'] and !$config['allow_quick_wysiwyg'] ) {
		
		$news_txt = $parse->decodeBBCodes( $news_txt, false );
		$full_txt = $parse->decodeBBCodes( $full_txt, false );
		$fix_br = "checked";
	
	} else {
		
		if( $config['allow_quick_wysiwyg'] ) {
			$news_txt = $parse->decodeBBCodes( $news_txt, true, $config['allow_quick_wysiwyg'] );
			$full_txt = $parse->decodeBBCodes( $full_txt, true, $config['allow_quick_wysiwyg'] );
		} else { 
			$news_txt = $parse->decodeBBCodes( $news_txt, true, false );
			$full_txt = $parse->decodeBBCodes( $full_txt, true, false );

		}
		
		$fix_br = "";
	
	}

	if( $row['approve'] ) {
		$fix_approve = "checked";
	} else $fix_approve = "";
	
	$row['title'] = $parse->decodeBBCodes( $row['title'], false );

	$xfields = xfieldsload();
	$xfieldsdata = xfieldsdataload ($row['xfields']);
	$xfbuffer = "";

	foreach ($xfields as $name => $value) {
		$fieldname = $value[0];

		if ( isset($xfieldsdata[$value[0]]) ) $fieldvalue = $xfieldsdata[$value[0]]; else continue;

		$smode = $parse->safe_mode;

		if ( $value[8] ) {
			$parse->safe_mode = true;
		}
		
		$fieldvalue = str_ireplace( "&#123;title", "{title", $fieldvalue );
		$fieldvalue = str_ireplace( "&#123;short-story", "{short-story", $fieldvalue );
		$fieldvalue = str_ireplace( "&#123;full-story", "{full-story", $fieldvalue );
		
		if( $row['allow_br'] AND !$config['allow_quick_wysiwyg'] ) {
			
			$fieldvalue = $parse->decodeBBCodes( $fieldvalue, false );
		
		} else {
			
			if( $config['allow_quick_wysiwyg'] ) $fieldvalue = $parse->decodeBBCodes( $fieldvalue, true, $config['allow_quick_wysiwyg'] );
			else $fieldvalue = $parse->decodeBBCodes( $fieldvalue, true, false );
		
		}

		$parse->safe_mode = $smode;

		if ($value[3] == "textarea") {
			
			if ( $value[7] ) {
				
				if ( !$config['allow_quick_wysiwyg'] ) {
	
					$params = "onfocus=\"setNewField(this.id, document.ajaxnews{$id})\" class=\"quick-edit-textarea\" "; 
					$class_name = "bb-editor";
					$panel="<!--panel-->";
					
				} else {
	
					$params = "class=\"wysiwygeditor\" ";
					$class_name = "";
					$panel="";
				}
				
			} else {
				$params = "class=\"quick-edit-textarea\" ";
				$class_name = "";
				$panel="";
			}
		
			 $xfbuffer .= "<div class=\"xfieldsrow\">{$value[1]}:<br /><div class=\"{$class_name}\">{$panel}<textarea name=\"xfield[{$fieldname}]\" id=\"xf_$fieldname\" {$params}>{$fieldvalue}</textarea></div></div>";

		} elseif ($value[3] == "text") {

			$fieldvalue = str_replace('"', '&quot;', $fieldvalue);
			$fieldvalue = str_replace('&amp;', '&', $fieldvalue);

			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><input type=\"text\" name=\"xfield[{$fieldname}]\" id=\"xfield[{$fieldname}]\" value=\"{$fieldvalue}\" class=\"quick-edit-text\" /></div></div>";

		} elseif ($value[3] == "select") { 

			$fieldvalue = str_replace('&amp;', '&', $fieldvalue);
			$fieldvalue = str_replace('&quot;', '"', $fieldvalue);

			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><select name=\"xfield[{$fieldname}]\" class=\"quick-edit-select\">";

	        foreach (explode("\r\n", $value[4]) as $index => $value) {
			  $value = str_replace("'", "&#039;", $value);
			  
			  $value = explode("|", $value);
			  if( count($value) < 2) $value[1] = $value[0];
			  
	          $xfbuffer .= "<option value=\"$index\"" . ($fieldvalue == $value[0] ? " selected" : "") . ">$value[1]</option>\r\n";
	        }

			$xfbuffer .= "</select></div></div>";

		} elseif ($value[3] == "yesorno") {
			
			$fieldvalue = intval($fieldvalue);
			
			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><select name=\"xfield[{$fieldname}]\" class=\"quick-edit-select\">";
			$xfbuffer .= "<option value=\"1\"" . ($fieldvalue == 1 ? " selected" : "") . ">{$lang['xfield_xyes']}</option>\r\n";
            $xfbuffer .= "<option value=\"0\"" . ($fieldvalue == 0 ? " selected" : "") . ">{$lang['xfield_xno']}</option>\r\n";
			$xfbuffer .= "</select></div></div>";

		} elseif( $value[3] == "image" ) {
			
			$max_file_size = (int)($value[10] * 1024);

			$fieldvalue = str_replace('"', '&quot;', $fieldvalue);
			$fieldvalue = str_replace('&amp;', '&', $fieldvalue);
			
			if( $fieldvalue ) {
				$path_parts = pathinfo($fieldvalue);
	
				if( $value[12] AND file_exists(ROOT_DIR . "/uploads/posts/" .$path_parts['dirname']."/thumbs/".$path_parts['basename']) ) {
					$img_url = 	$config['http_home_url'] . "uploads/posts/" . $path_parts['dirname']."/thumbs/".$path_parts['basename'];
				} else {
					$img_url = 	$config['http_home_url'] . "uploads/posts/" . $path_parts['dirname']."/".$path_parts['basename'];
				}
				
				$filename = explode("_", $path_parts['basename']);
				unset($filename[0]);
				$filename = implode("_", $filename);
					
				$up_image = "<div class=\"uploadedfile\"><div class=\"info\">{$filename}</div><div class=\"uploadimage\"><img style=\"width:auto;height:auto;max-width:100px;max-height:90px;\" src=\"" . $img_url . "\" /></div><div class=\"info\"><a href=\"#\" onclick=\"xfimagedelete(\\'".$fieldname."\\',\\'".$fieldvalue."\\');return false;\">{$lang['xfield_xfid']}</a></div></div>";
				
			} else $up_image = "";

$uploadscript = <<<HTML
	new qq.FileUploader({
		element: document.getElementById('xfupload_{$fieldname}'),
		action:  dle_root + 'engine/ajax/upload.php',
		maxConnections: 1,
		multiple: false,
		encoding: 'multipart',
        sizeLimit: {$max_file_size},
		allowedExtensions: ['gif', 'jpg', 'png'],
	    params: {"PHPSESSID" : "{$sess_id}", "subaction" : "upload", "news_id" : "{$row['id']}", "area" : "xfieldsimage", "author" : "{$author}", "xfname" : "{$fieldname}"},
        template: '<div class="qq-uploader">' + 
                '<div id="uploadedfile_{$fieldname}">{$up_image}</div><div class="qq-upload-drop-area"><span>{$lang['media_upload_st5']}</span></div>' +
                '<div class="qq-upload-button btn btn-green" style="width: auto;">{$lang['xfield_xfim']}</div>' +
                '<ul class="qq-upload-list" style="display:none;"></ul>' + 
             '</div>',
		onSubmit: function(id, fileName) {

					$('<div id="uploadfile-'+id+'" class="file-box"><span class="qq-upload-file-status">{$lang['media_upload_st6']}</span><span class="qq-upload-file">&nbsp;'+fileName+'</span>&nbsp;<span class="qq-status"><span class="qq-upload-spinner"></span><span class="qq-upload-size"></span></span><div class="progress "><div class="progress-bar progress-blue" style="width: 0%"><span>0%</span></div></div></div>').appendTo('#xfupload_{$fieldname}');

        },
		onProgress: function(id, fileName, loaded, total){
					$('#uploadfile-'+id+' .qq-upload-size').text(DLEformatSize(loaded)+' {$lang['media_upload_st8']} '+DLEformatSize(total));
					var proc = Math.round(loaded / total * 100);
					$('#uploadfile-'+id+' .progress-bar').css( "width", proc + '%' );
					$('#uploadfile-'+id+' .qq-upload-spinner').css( "display", "inline-block");

		},
		onComplete: function(id, fileName, response){

						if ( response.success ) {
							var returnbox = response.returnbox;
							var returnval = response.xfvalue;

							returnbox = returnbox.replace(/&lt;/g, "<");
							returnbox = returnbox.replace(/&gt;/g, ">");
							returnbox = returnbox.replace(/&amp;/g, "&");

							$('#uploadfile-'+id+' .qq-status').html('{$lang['media_upload_st9']}');
							$('#uploadedfile_{$fieldname}').html( returnbox );
							$('#xf_{$fieldname}').val(returnval);
							$('#xfupload_{$fieldname} .qq-upload-button, #xfupload_{$fieldname} .qq-upload-button input').attr("disabled","disabled");

							setTimeout(function() {
								$('#uploadfile-'+id).fadeOut('slow', function() { $(this).remove(); });
							}, 1000);

						} else {
							$('#uploadfile-'+id+' .qq-status').html('{$lang['media_upload_st10']}');

							if( response.error ) $('#uploadfile-'+id+' .qq-status').append( '<br /><font color="red">' + response.error + '</font>' );

							setTimeout(function() {
								$('#uploadfile-'+id).fadeOut('slow');
							}, 4000);
						}
		},
        messages: {
            typeError: "{$lang['media_upload_st11']}",
            sizeError: "{$lang['media_upload_st12']}",
            emptyError: "{$lang['media_upload_st13']}"
        },
		debug: false
    });
	
	$('#xfupload_{$fieldname} .qq-upload-button, #xfupload_{$fieldname} .qq-upload-button input').attr("disabled","disabled");
	
HTML;
			
			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><div id=\"xfupload_{$fieldname}\"></div><input type=\"hidden\" name=\"xfield[$fieldname]\" id=\"xf_$fieldname\" value=\"{$fieldvalue}\" /><script type=\"text/javascript\">{$uploadscript}</script></div></div>";

		} elseif( $value[3] == "imagegalery" ) {

	    $max_file_size = (int)($value[10] * 1024);
		
		$fieldvalue = str_replace('"', '&quot;', $fieldvalue);
		$fieldvalue = str_replace('&amp;', '&', $fieldvalue);
		$fieldcount = md5($fieldname);

		if( $fieldvalue ) {
			$fieldvalue_arr = explode(',', $fieldvalue);
			$up_image = array();
			
			foreach ($fieldvalue_arr as $temp_value) {
				$temp_value = trim($temp_value);
				
				if($temp_value == "") continue;
				
				$path_parts = pathinfo($temp_value);
				
				if( $value[12] AND file_exists(ROOT_DIR . "/uploads/posts/" .$path_parts['dirname']."/thumbs/".$path_parts['basename']) ) {
					$img_url = 	$config['http_home_url'] . "uploads/posts/" . $path_parts['dirname']."/thumbs/".$path_parts['basename'];
				} else {
					$img_url = 	$config['http_home_url'] . "uploads/posts/" . $path_parts['dirname']."/".$path_parts['basename'];
				}
				
				$filename = explode("_", $path_parts['basename']);
				unset($filename[0]);
				$filename = implode("_", $filename);
				
				$xf_id = md5($temp_value);
				$up_image[] = "<div id=\"xf_{$xf_id}\" class=\"uploadedfile\"><div class=\"info\">{$filename}</div><div class=\"uploadimage\"><img style=\"width:auto;height:auto;max-width:100px;max-height:90px;\" src=\"" . $img_url . "\" /></div><div class=\"info\"><a href=\"#\" onclick=\"xfimagegalerydelete_".md5($fieldname)."(\\'".$fieldname."\\',\\'".$temp_value."\\', \\'".$xf_id."\\');return false;\">{$lang['xfield_xfid']}</a></div></div>";

			}
			
			$totaluploadedfiles = count($up_image);
			$up_image = implode($up_image);

			
		} else { $up_image = ""; $totaluploadedfiles = 0; }
		
		if (!$value[5]) { 
			$params = "rel=\"essential\" "; 
			$uid = "uid=\"essential\" "; 

		} else { 

			$params = ""; 
			$uid = "";

		}

$uploadscript = <<<HTML
	var maxallowfiles_{$fieldcount} = {$value[16]};
	var totaluploaded_{$fieldcount} = {$totaluploadedfiles};
	var totalqueue_{$fieldcount} = 0;
	
	function xfimagegalerydelete_{$fieldcount} ( xfname, xfvalue, id )
	{
		DLEconfirm( '{$lang['image_delete']}', '{$lang['p_info']}', function () {
		
			ShowLoading('');
	
			$.post('engine/ajax/upload.php', { subaction: 'deluploads', user_hash: '{$dle_login_hash}', news_id: '{$row['id']}', author: '{$author}', 'images[]' : xfvalue }, function(data){
	
				HideLoading('');
				var str = $('#xf_'+xfname).val();
				var arr = str.split(',');
				if( $.inArray(xfvalue, arr) != -1 ){
					arr.splice( $.inArray(xfvalue, arr), 1 );
				}
				
				if ( arr.length ) {
					$('#xf_'+xfname).val(arr.join(','));
				} else {
					$('#xf_'+xfname).val('');
				}

				$('#xf_'+id).remove();
				totaluploaded_{$fieldcount} --;
				
				$('#xfupload_' + xfname + ' .qq-upload-button, #xfupload_' + xfname + ' .qq-upload-button input').removeAttr('disabled');
			});
			
		} );
		
		return false;

	};
	
	var uploader_{$fieldcount} = new qq.FileUploader({
		element: document.getElementById('xfupload_{$fieldname}'),
		action: 'engine/ajax/upload.php',
		maxConnections: 1,
		multiple: true,
		encoding: 'multipart',
        sizeLimit: {$max_file_size},
		allowedExtensions: ['gif', 'jpg', 'jpeg', 'png'],
	    params: {"PHPSESSID" : "{$sess_id}", "subaction" : "upload", "news_id" : "{$row['id']}", "area" : "xfieldsimagegalery", "author" : "{$author}", "xfname" : "{$fieldname}"},
        template: '<div class="qq-uploader">' + 
                '<div id="uploadedfile_{$fieldname}">{$up_image}</div><div class="qq-upload-drop-area"><span>{$lang['media_upload_st5']}</span></div>' +
                '<div class="qq-upload-button btn btn-green" style="width: auto;">{$lang['xfield_xfimg']}</div>' +
                '<ul class="qq-upload-list" style="display:none;"></ul>' + 
             '</div>',
		onSubmit: function(id, fileName) {
		
					totalqueue_{$fieldcount} ++;
					
					if(maxallowfiles_{$fieldcount} && (totaluploaded_{$fieldcount} + totalqueue_{$fieldcount} ) > maxallowfiles_{$fieldcount} ) {
						totalqueue_{$fieldcount} --;
					
					    $('#xfupload_{$fieldname} .qq-upload-button, #xfupload_{$fieldname} .qq-upload-button input').attr("disabled","disabled");
						return false;
					}
							
					$('<div id="uploadfile-'+id+'" class="file-box"><span class="qq-upload-file-status">{$lang['media_upload_st6']}</span><span class="qq-upload-file">&nbsp;'+fileName+'</span>&nbsp;<span class="qq-status"><span class="qq-upload-spinner"></span><span class="qq-upload-size"></span></span><div class="progress "><div class="progress-bar progress-blue" style="width: 0%"><span>0%</span></div></div></div>').appendTo('#xfupload_{$fieldname}');

        },
		onProgress: function(id, fileName, loaded, total){
					$('#uploadfile-'+id+' .qq-upload-size').text(DLEformatSize(loaded)+' {$lang['media_upload_st8']} '+DLEformatSize(total));
					var proc = Math.round(loaded / total * 100);
					$('#uploadfile-'+id+' .progress-bar').css( "width", proc + '%' );
					$('#uploadfile-'+id+' .qq-upload-spinner').css( "display", "inline-block");

		},
		onComplete: function(id, fileName, response){

						totalqueue_{$fieldcount} --;

						if ( response.success ) {
							totaluploaded_{$fieldcount} ++;

							var fieldvalue = $('#xf_{$fieldname}').val();
						
							var returnbox = response.returnbox;
							var returnval = response.xfvalue;

							returnbox = returnbox.replace(/&lt;/g, "<");
							returnbox = returnbox.replace(/&gt;/g, ">");
							returnbox = returnbox.replace(/&amp;/g, "&");

							$('#uploadfile-'+id+' .qq-status').html('{$lang['media_upload_st9']}');
							$('#uploadedfile_{$fieldname}').append( returnbox );
							
							if (fieldvalue == "") {
								$('#xf_{$fieldname}').val(returnval);
							} else {
								fieldvalue += ',' +returnval;
								$('#xf_{$fieldname}').val(fieldvalue);
							}

							if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} == maxallowfiles_{$fieldcount} ) {
									$('#xfupload_{$fieldname} .qq-upload-button, #xfupload_{$fieldname} .qq-upload-button input').attr("disabled","disabled");
							}

							setTimeout(function() {
								$('#uploadfile-'+id).fadeOut('slow', function() { $(this).remove(); });
							}, 1000);

						} else {
							$('#uploadfile-'+id+' .qq-status').html('{$lang['media_upload_st10']}');

							if( response.error ) $('#uploadfile-'+id+' .qq-status').append( '<br /><font color="red">' + response.error + '</font>' );

							setTimeout(function() {
								$('#uploadfile-'+id).fadeOut('slow');
							}, 4000);
						}
		},
        messages: {
            typeError: "{$lang['media_upload_st11']}",
            sizeError: "{$lang['media_upload_st12']}",
            emptyError: "{$lang['media_upload_st13']}"
        },
		debug: false
    });
	
	if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} >=  maxallowfiles_{$fieldcount} ) {
		$('#xfupload_{$fieldname} .qq-upload-button, #xfupload_{$fieldname} .qq-upload-button input').attr("disabled","disabled");
	}
HTML;

			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><div id=\"xfupload_{$fieldname}\"></div><input type=\"hidden\" name=\"xfield[$fieldname]\" id=\"xf_$fieldname\" value=\"{$fieldvalue}\" /><script type=\"text/javascript\">{$uploadscript}</script></div></div>";

		} elseif( $value[3] == "file" ) {
			$max_file_size = (int)($value[15] * 1024);
			$allowed_files = explode( ',', strtolower( $value[14] ) );
			$allowed_files = implode( "', '", $allowed_files );
	
			$fieldvalue = str_replace('"', '&quot;', $fieldvalue);
			$fieldvalue = str_replace('&amp;', '&', $fieldvalue);
			
			if( $fieldvalue ) {
				
				$fileid = intval(preg_replace( "'\[attachment=(.*?):(.*?)\]'si", "\\1", $fieldvalue ));
				
				$fileid = "&nbsp;<button class=\"qq-upload-button btn btn-sm btn-red\" onclick=\"xffiledelete('".$fieldname."','".$fileid."');return false;\">{$lang['xfield_xfid']}</button>";
	
				$show="display:inline-block;";
				
			} else { $show="display:none;"; $fileid="";}

$uploadscript = <<<HTML
	new qq.FileUploader({
		element: document.getElementById('xfupload_{$fieldname}'),
		action: dle_root + 'engine/ajax/upload.php',
		maxConnections: 1,
		multiple: false,
		encoding: 'multipart',
        sizeLimit: {$max_file_size},
		allowedExtensions: ['{$allowed_files}'],
	    params: {"PHPSESSID" : "{$sess_id}", "subaction" : "upload", "news_id" : "{$row['id']}", "area" : "xfieldsfile", "author" : "{$author}", "xfname" : "{$fieldname}"},
        template: '<div class="qq-uploader">' + 
                '<div class="qq-upload-drop-area"><span>{$lang['media_upload_st5']}</span></div>' +
                '<div class="qq-upload-button btn btn-green" style="width: auto;">{$lang['xfield_xfif']}</div>' +
                '<ul class="qq-upload-list" style="display:none;"></ul>' + 
             '</div>',
		onSubmit: function(id, fileName) {

					$('<div id="uploadfile-'+id+'" class="file-box"><span class="qq-upload-file-status">{$lang['media_upload_st6']}</span><span class="qq-upload-file">&nbsp;'+fileName+'</span>&nbsp;<span class="qq-status"><span class="qq-upload-spinner"></span><span class="qq-upload-size"></span></span><div class="progress"><div class="progress-bar progress-blue" style="width: 0%"><span>0%</span></div></div></div>').appendTo('#xfupload_{$fieldname}');

        },
		onProgress: function(id, fileName, loaded, total){
					$('#uploadfile-'+id+' .qq-upload-size').text(DLEformatSize(loaded)+' {$lang['media_upload_st8']} '+DLEformatSize(total));
					var proc = Math.round(loaded / total * 100);
					$('#uploadfile-'+id+' .progress-bar').css( "width", proc + '%' );
					$('#uploadfile-'+id+' .qq-upload-spinner').css( "display", "inline-block");

		},
		onComplete: function(id, fileName, response){

						if ( response.success ) {
							var returnbox = response.returnbox;
							var returnval = response.xfvalue;

							returnbox = returnbox.replace(/&lt;/g, "<");
							returnbox = returnbox.replace(/&gt;/g, ">");
							returnbox = returnbox.replace(/&amp;/g, "&");

							$('#uploadfile-'+id+' .qq-status').html('{$lang['media_upload_st9']}');
							$('#xf_{$fieldname}').show();
							$('#uploadedfile_{$fieldname}').html( returnbox );
							$('#xf_{$fieldname}').val(returnval);
							$('#xfupload_{$fieldname} .qq-upload-button, #xfupload_{$fieldname} .qq-upload-button input').attr("disabled","disabled");

							setTimeout(function() {
								$('#uploadfile-'+id).fadeOut('slow', function() { $(this).remove(); });
							}, 1000);

						} else {
							$('#uploadfile-'+id+' .qq-status').html('{$lang['media_upload_st10']}');

							if( response.error ) $('#uploadfile-'+id+' .qq-status').append( '<br /><font color="red">' + response.error + '</font>' );

							setTimeout(function() {
								$('#uploadfile-'+id).fadeOut('slow');
							}, 4000);
						}
		},
        messages: {
            typeError: "{$lang['media_upload_st11']}",
            sizeError: "{$lang['media_upload_st12']}",
            emptyError: "{$lang['media_upload_st13']}"
        },
		debug: false
    });
	
	$('#xfupload_{$fieldname} .qq-upload-button, #xfupload_{$fieldname} .qq-upload-button input').attr("disabled","disabled");
HTML;

			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><input style=\"{$show}\" class=\"quick-edit-text\" type=\"text\" name=\"xfield[$fieldname]\" id=\"xf_$fieldname\" value=\"{$fieldvalue}\" /><span id=\"uploadedfile_{$fieldname}\">{$fileid}</span><div id=\"xfupload_{$fieldname}\"></div><script type=\"text/javascript\">{$uploadscript}</script></div></div>";
		
		}
	
	}
	
	$addtype = "addnews";
	
	if( !$config['allow_quick_wysiwyg'] ) {
		
		include_once ENGINE_DIR . '/ajax/bbcode.php';
		$xfbuffer = str_replace ("<!--panel-->", $code, $xfbuffer);
	
	} else {

		$p_name = urlencode($row['autor']);

		if ( $config['allow_quick_wysiwyg'] == "2") {

			if ( $user_group[$member_id['user_group']]['allow_image_upload'] OR $user_group[$member_id['user_group']]['allow_file_upload'] ) $image_upload = "dleupload "; else $image_upload = "";

			$bb_code = <<<HTML

<script type="text/javascript">
var text_upload = "$lang[bb_t_up]";

setTimeout(function() {

	tinymce.remove('textarea.wysiwygeditor');

	tinymce.init({
		selector: 'textarea.wysiwygeditor',
		language : "{$lang['wysiwyg_language']}",
		width : "100%",
		height : "280",
		theme: "modern",
		plugins: ["advlist autolink lists link image charmap anchor searchreplace visualblocks visualchars fullscreen media nonbreaking table contextmenu emoticons paste textcolor codemirror spellchecker dlebutton codesample"],
		relative_urls : false,
		convert_urls : false,
		remove_script_host : false,
		extended_valid_elements : "noindex,div[align|class|style|id|title]",
		custom_elements : 'noindex',
		image_caption: true,
		image_advtab: true,
		toolbar_items_size: 'small',
		menubar: false,
		toolbar1: "fontselect fontsizeselect table link anchor dleleech unlink {$image_upload}image dleemo dlemp dletube dlaudio dlequote dlespoiler codesample dlebreak dlepage code",
		toolbar2: "undo redo copy paste pastetext bold italic underline strikethrough alignleft aligncenter alignright alignjustify subscript superscript bullist numlist forecolor backcolor spellchecker removeformat",

		dle_root : "{$config['http_home_url']}",
		dle_upload_area : "short_story",
		dle_upload_user : "{$p_name}",
		dle_upload_news : "{$row['id']}",
		
		spellchecker_language : "ru",
		spellchecker_rpc_url : "//speller.yandex.net/services/tinyspell",
		content_css : "{$config['http_home_url']}engine/editor/css/content.css"
	});

}, 100);

</script>
HTML;

		
		} else {


			if ( $user_group[$member_id['user_group']]['allow_image_upload'] OR $user_group[$member_id['user_group']]['allow_file_upload'] ) {
				
				$image_upload = "'dleupload',";
				$image_q_upload = ", 'imageUpload'";
				
			} else { $image_upload = ""; $image_q_upload = ""; }
			
			$bb_code = <<<HTML
<link rel="stylesheet" href="{$config['http_home_url']}engine/editor/jscripts/froala/fonts/font-awesome.css">
<link rel="stylesheet" href="{$config['http_home_url']}engine/editor/jscripts/froala/css/editor.css">
<link rel="stylesheet" href="{$config['http_home_url']}engine/skins/codemirror/css/default.css">
<script type="text/javascript" src="{$config['http_home_url']}engine/skins/codemirror/js/code.js"></script>
<script type="text/javascript" src="{$config['http_home_url']}engine/editor/jscripts/froala/editor.js"></script>
<script type="text/javascript" src="{$config['http_home_url']}engine/editor/jscripts/froala/languages/{$lang['wysiwyg_language']}.js"></script>
<script type="text/javascript">
var text_upload = "$lang[bb_t_up]";

      $('.wysiwygeditor').froalaEditor({
        dle_root: dle_root,
        dle_upload_area : "short_story",
        dle_upload_user : "{$p_name}",
        dle_upload_news : "{$row['id']}",
        width: '100%',
        height: '280',
        language: '{$lang['wysiwyg_language']}',
		placeholderText: '',
        enter: $.FroalaEditor.ENTER_BR,
        toolbarSticky: false,
        theme: 'gray',
        htmlRemoveTags: ['script', 'style'],
		lineBreakerTags: ['table', 'hr', 'iframe', 'pre', 'dl'],
        linkAlwaysNoFollow: false,
        linkInsertButtons: ['linkBack'],
        linkList:[],
        linkAutoPrefix: '',
        linkStyles: {
          'fr-strong': 'Bold',
          'fr-text-red': 'Red',
          'fr-text-blue': 'Blue',
          'fr-text-green': 'Green'
        },
        linkText: true,

        paragraphFormat: {
            N: 'Normal',
            H1: 'Heading 1',
            H2: 'Heading 2',
            H3: 'Heading 3',
            H4: 'Heading 4',
            H5: 'Heading 5',
            p: 'Paragraph',
            div: 'Layer',
        },
        paragraphStyles: {
          'fr-text-bordered': 'Bordered',
          'fr-text-spaced': 'Spaced',
          'fr-text-uppercase': 'Uppercase',
          'fr-text-gray': 'Gray',
          'fr-text-red': 'Red',
          'fr-text-blue': 'Blue',
          'fr-text-green': 'Green'
        },
        tableStyles: {
          'fr-solid-borders': 'Solid Borders',
          'fr-dashed-borders': 'Dashed Borders',
          'fr-alternate-rows': 'Alternate Rows'
        },
        tableCellStyles: {
          'fr-red': 'Red',
          'fr-blue': 'Blue',
          'fr-green': 'Green'
        },
        imageAllowedTypes: ['jpeg', 'jpg', 'png', 'gif'],
        imageDefaultWidth: 0,
        imageInsertButtons: ['imageBack', '|', 'imageByURL'{$image_q_upload}],
		imageUploadURL: 'engine/ajax/upload.php',
		imageUploadParam: 'qqfile',
		imageUploadParams: { "subaction" : "upload", "news_id" : "{$row['id']}", "area" : "short_story", "author" : "{$p_name}", "mode" : "quickload"  },
        imageMaxSize: {$config['max_up_size']} * 1024,
        imagePaste: false,
        imageStyles: {
          'fr-bordered': 'Borders',
          'fr-rounded': 'Rounded',
          'fr-padded': 'Padded',
          'fr-shadows': 'Shadows',
        },
		
        toolbarButtonsXS: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'indent', 'outdent', '|', 'subscript', 'superscript', '|', 'insertTable', 'formatOL', 'formatUL', 'insertHR', '|', 'clearFormatting', 'selectAll', '|', 'html', '-', 
                         'fontFamily', 'fontSize', '|', 'color', 'paragraphFormat', 'paragraphStyle', '|', 'insertLink', 'dleleech', '|', 'emoticons', 'insertImage',{$image_upload}'|', 'insertVideo', 'dleaudio','|', 'dlehide', 'dlequote', 'dlespoiler','dlecode'],

						 
        toolbarButtonsSM: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'indent', 'outdent', '|', 'subscript', 'superscript', '|', 'insertTable', 'formatOL', 'formatUL', 'insertHR', '|', 'clearFormatting', 'selectAll', '|', 'html', '-', 
                         'fontFamily', 'fontSize', '|', 'color', 'paragraphFormat', 'paragraphStyle', '|', 'insertLink', 'dleleech', '|', 'emoticons', 'insertImage',{$image_upload}'|', 'insertVideo', 'dleaudio','|', 'dlehide', 'dlequote', 'dlespoiler','dlecode'],

        toolbarButtonsMD: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'indent', 'outdent', '|', 'subscript', 'superscript', '|', 'insertTable', 'formatOL', 'formatUL', 'insertHR', '|', 'clearFormatting', 'selectAll', '|', 'html', '-', 
                         'fontFamily', 'fontSize', '|', 'color', 'paragraphFormat', 'paragraphStyle', '|', 'insertLink', 'dleleech', '|', 'emoticons', 'insertImage',{$image_upload}'|', 'insertVideo', 'dleaudio','|', 'dlehide', 'dlequote', 'dlespoiler','dlecode'],

        toolbarButtons: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'indent', 'outdent', '|', 'subscript', 'superscript', '|', 'insertTable', 'formatOL', 'formatUL', 'insertHR', '|', 'clearFormatting', 'selectAll', '|', 'html', '-', 
                         'fontFamily', 'fontSize', '|', 'color', 'paragraphFormat', 'paragraphStyle', '|', 'insertLink', 'dleleech', '|', 'emoticons', 'insertImage',{$image_upload}'|', 'insertVideo', 'dleaudio','|', 'dlehide', 'dlequote', 'dlespoiler','dlecode']

      }).on('froalaEditor.image.inserted froalaEditor.image.replaced', function (e, editor, \$img, response) {
	  
			if( response ) {
			
			    response = jQuery.parseJSON(response);
			  
			    \$img.removeAttr("data-returnbox").removeAttr("data-success").removeAttr("data-xfvalue").removeAttr("data-flink");

				if(response.flink) {
				  if(\$img.parent().attr('rel') == "highslide") {
		
					\$img.parent().attr('href', response.flink);
		
				  } else {
		
					\$img.wrap( '<a href="'+response.flink+'" class="highslide" rel="highslide"></a>' );
					
				  }
				}
			  
			}
			
		});

</script>
HTML;
		}

		$code = "";	
	}

	if ( !$config['allow_quick_wysiwyg'] ) $params = "onfocus=\"setNewField(this.name, document.ajaxnews{$id})\" class=\"quick-edit-textarea\""; else $params = "class=\"wysiwygeditor\"";
	
	$buffer = <<<HTML
<script type="text/javascript" src="{$config['http_home_url']}engine/classes/uploads/html5/fileuploader.js"></script>
<form name="ajaxnews{$id}" id="ajaxnews{$id}" metod="post" action="">
<div style="padding-bottom:5px;"><input type="text" id='edit-title-{$id}' class="quick-edit-text" value="{$row['title']}" /></div>
<div><br /><b>{$lang['s_fshort']}</b></div>
<div class="bb-editor">
{$bb_code}
<textarea name="dleeditnews{$id}" id="dleeditnews{$id}" {$params}>{$news_txt}</textarea>
</div>
<div><br /><b>{$lang['s_ffull']}</b></div>
<div class="bb-editor">
{$code}
<textarea name="dleeditfullnews{$id}" id="dleeditfullnews{$id}" {$params}>{$full_txt}</textarea>
</div>
{$xfbuffer}
<div class="xfieldsrow"><div class="xfieldscolleft">{$lang['reason']}</div><div class="xfieldscolright"><input type="text" id='edit-reason-{$id}' class="quick-edit-text" value="{$row['reason']}"></div></div>
<div class="xfieldsrow"><input type="checkbox" name="approve_{$id}" id="approve_{$id}" value="1" {$fix_approve}>&nbsp;<label for="approve_{$id}">{$lang['add_al_ap']}</label>&nbsp;&nbsp;<input type="checkbox" name="allow_br_{$id}" id="allow_br_{$id}" value="1" {$fix_br}>&nbsp;<label for="allow_br_{$id}">{$lang['aj_allowbr']}</label></div>
</form>
<script type="text/javascript">
	function xfimagedelete( xfname, xfvalue )
	{
		
		DLEconfirm( '{$lang['image_delete']}', '{$lang['p_info']}', function () {
		
			ShowLoading('');
			
			$.post(dle_root + 'engine/ajax/upload.php', { subaction: 'deluploads', user_hash: '{$dle_login_hash}', news_id: '{$id}', author: '{$author}', 'images[]' : xfvalue }, function(data){
	
				HideLoading('');
				
				$('#uploadedfile_'+xfname).html('');
				$('#xf_'+xfname).val('');
				$('#xfupload_' + xfname + ' .qq-upload-button, #xfupload_' + xfname + ' .qq-upload-button input').removeAttr('disabled');
			});
			
		} );

		return false;

	};
	function xffiledelete( xfname, xfvalue )
	{
		
		DLEconfirm( '{$lang['file_delete']}', '{$lang['p_info']}', function () {
		
			ShowLoading('');
			
			$.post(dle_root + 'engine/ajax/upload.php', { subaction: 'deluploads', user_hash: '{$dle_login_hash}', news_id: '{$id}', author: '{$author}', 'files[]' : xfvalue }, function(data){
	
				HideLoading('');
				
				$('#uploadedfile_'+xfname).html('');
				$('#xf_'+xfname).val('');
				$('#xf_'+xfname).hide('');
				$('#xfupload_' + xfname + ' .qq-upload-button, #xfupload_' + xfname + ' .qq-upload-button input').removeAttr('disabled');
			});
			
		} );

		return false;

	};
</script>	
HTML;

} elseif( $_REQUEST['action'] == "save" ) {
	$row = $db->super_query( "SELECT id, date, title, category, short_story, full_story, autor FROM " . PREFIX . "_post where id = '$id'" );
	
	if( $id != $row['id'] ) die( "News Not Found" );
	
	$cat_list = explode( ',', $row['category'] );
	
	$have_perm = 0;
	
	if( $user_group[$member_id['user_group']]['allow_all_edit'] ) {
		$have_perm = 1;
		
		$allow_list = explode( ',', $user_group[$member_id['user_group']]['cat_add'] );
		
		foreach ( $cat_list as $selected ) {
			if( $allow_list[0] != "all" and ! in_array( $selected, $allow_list ) ) $have_perm = 0;
		}
	}
	
	if( $user_group[$member_id['user_group']]['allow_edit'] and $row['autor'] == $member_id['name'] ) {
		$have_perm = 1;
	}
	
	if( $user_group[$member_id['user_group']]['max_edit_days'] ) {
		$newstime = strtotime( $row['date'] );
		$maxedittime = $_TIME - ($user_group[$member_id['user_group']]['max_edit_days'] * 3600 * 24);
		if( $maxedittime > $newstime ) $have_perm = 0;
	}
	
	if( ($member_id['user_group'] == 1) ) {
		$have_perm = 1;
	}
	
	if( ! $have_perm ) die( "Access it is refused" );
	
	$allow_br = intval( $_REQUEST['allow_br'] );
	$approve = intval( $_REQUEST['approve'] );

	if( !$user_group[$member_id['user_group']]['moderation'] ) $approve = 0;
	
	if( $allow_br ) $use_html = false;
	else $use_html = true;

	$_POST['title'] = $db->safesql( $parse->process( trim( strip_tags (convert_unicode( $_POST['title'], $config['charset']  ) ) ) ) );

	if ( $config['allow_quick_wysiwyg'] ) $parse->allow_code = false;

	$_POST['news_txt'] = convert_unicode( $_POST['news_txt'], $config['charset'] );
	$_POST['full_txt'] = convert_unicode( $_POST['full_txt'], $config['charset'] );

	if ( !$user_group[$member_id['user_group']]['allow_html'] ) {

		$_POST['news_txt'] = strip_tags ($_POST['news_txt']);
		$_POST['full_txt'] = strip_tags ($_POST['full_txt']);

	}

	$news_txt = $db->safesql($parse->BB_Parse( $parse->process( $_POST['news_txt'] ), $use_html ));
	$full_txt = $db->safesql($parse->BB_Parse( $parse->process( $_POST['full_txt'] ), $use_html ));


	$add_module = "yes";
	$ajax_edit = "yes";
	$stop = "";
	$category = $cat_list;
	$xfieldsaction = "init";
	include (ENGINE_DIR . '/inc/xfields.php');

	$editreason = $db->safesql( htmlspecialchars( strip_tags( stripslashes( trim( convert_unicode( $_POST['reason'], $config['charset'] ) ) ) ), ENT_QUOTES, $config['charset'] ) );
	
	if( $editreason != "" ) $view_edit = 1;
	else $view_edit = 0;
	$added_time = time();
	
	if( !trim($_POST['title']) ) die( $lang['add_err_7'] );

	if ($parse->not_allowed_text ) die( $lang['news_err_39'] );

	if( dle_strlen( $_POST['title'], $config['charset'] ) > 255 ) {
		die( $lang['content_error'] );
	}
	if( dle_strlen( $news_txt, $config['charset'] ) > 1677700 ) {
		die( $lang['content_error'] );
	}
		
	if( dle_strlen( $full_txt, $config['charset'] ) > 1677700 ) {
		die( $lang['content_error'] );
	}
	
	if( dle_strlen( $filecontents, $config['charset'] ) > 1677700 ) {
		die( $lang['content_error'] );
	}
	
	if( dle_strlen( $editreason, $config['charset'] ) > 255 ) {
		die( $lang['content_error'] );
	}
	
	$db->query( "UPDATE " . PREFIX . "_post SET title='{$_POST['title']}', short_story='$news_txt', full_story='$full_txt', xfields='$filecontents', approve='$approve', allow_br='$allow_br' WHERE id = '$id'" );
	$db->query( "UPDATE " . PREFIX . "_post_extras SET editdate='$added_time', editor='{$member_id['name']}', reason='$editreason', view_edit='$view_edit' WHERE news_id = '$id'" );

	$db->query( "DELETE FROM " . PREFIX . "_xfsearch WHERE news_id = '{$id}'" );

	if ( count($xf_search_words) AND $approve ) {
					
		$temp_array = array();
					
		foreach ( $xf_search_words as $value ) {
						
			$temp_array[] = "('" . $id . "', '" . $value[0] . "', '" . $value[1] . "')";
		}
					
		$xf_search_words = implode( ", ", $temp_array );
		$db->query( "INSERT INTO " . PREFIX . "_xfsearch (news_id, tagname, tagvalue) VALUES " . $xf_search_words );
	}

	if ($user_group[$member_id['user_group']]['allow_admin']) $db->query( "INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('".$db->safesql($member_id['name'])."', '{$_TIME}', '{$_IP}', '25', '{$_POST['title']}')" );

	if ( $config['allow_alt_url'] AND !$config['seo_type'] ) $cprefix = "full_"; else $cprefix = "full_".$id;	

	clear_cache( array( 'news_', 'rss', $cprefix ) );
	
	$buffer = "ok";

} else die( "error" );

$db->close();

echo $buffer;
?>