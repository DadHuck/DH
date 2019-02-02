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
 Файл: comments.php
-----------------------------------------------------
 Назначение: WYSIWYG для комментариев
=====================================================
*/

if(!defined('DATALIFEENGINE'))
{
  die("Hacking attempt!");
}

if( $config['allow_comments_wysiwyg'] == 1 ) {

	if ($user_group[$member_id['user_group']]['allow_url']) $link_icon = "'insertLink', 'dleleech',"; else $link_icon = "";
	if ($user_group[$member_id['user_group']]['allow_image']) $link_icon .= "'insertImage',";
	if ($user_group[$member_id['user_group']]['allow_up_image']) $link_icon .= "'dleupload',";

	$js_array[] = "engine/editor/jscripts/froala/editor.js";
	$js_array[] = "engine/editor/jscripts/froala/languages/{$lang['wysiwyg_language']}.js";
		
	$onload_scripts[] = <<<HTML
	
      $('#comments').froalaEditor({
        dle_root: dle_root,
        dle_upload_area : "comments",
        dle_upload_user : "{$p_name}",
        dle_upload_news : "{$p_id}",
        width: '100%',
        height: '220',
        language: '{$lang['wysiwyg_language']}',
		placeholderText: '',
        enter: $.FroalaEditor.ENTER_BR,
        toolbarSticky: false,
        theme: 'gray',
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
		htmlAllowedTags: ['div', 'span', 'p', 'br', 'strong', 'em', 'ul', 'li', 'ol', 'b', 'u', 'i', 's', 'a', 'img'],
		htmlAllowedAttrs: ['class', 'href', 'alt', 'src', 'style', 'target'],
		pastePlain: true,
        imageInsertButtons: ['imageBack', '|', 'imageByURL'],
        imagePaste: false,
        imageStyles: {
          'fr-bordered': 'Borders',
          'fr-rounded': 'Rounded',
          'fr-padded': 'Padded',
          'fr-shadows': 'Shadows',
        },
		
        toolbarButtonsXS: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'formatOL', 'formatUL', '|', {$link_icon} 'emoticons', '|', 'dlehide', 'dlequote', 'dlespoiler'],

        toolbarButtonsSM: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'formatOL', 'formatUL', '|', {$link_icon} 'emoticons', '|', 'dlehide', 'dlequote', 'dlespoiler'],

        toolbarButtonsMD: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'formatOL', 'formatUL', '|', {$link_icon} 'emoticons', '|', 'dlehide', 'dlequote', 'dlespoiler'],

        toolbarButtons: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'formatOL', 'formatUL', '|', {$link_icon} 'emoticons', '|', 'dlehide', 'dlequote', 'dlespoiler']

      });

HTML;

$wysiwyg = <<<HTML
<link rel="stylesheet" href="{$config['http_home_url']}engine/editor/jscripts/froala/fonts/font-awesome.css">
<link rel="stylesheet" href="{$config['http_home_url']}engine/editor/jscripts/froala/css/editor.css">
<script type="text/javascript">
	var text_upload = "$lang[bb_t_up]";
</script>
<div class="wseditor"><textarea id="comments" name="comments" rows="10" cols="50" class="ajaxwysiwygeditor">{$text}</textarea></div>
HTML;

} else {

	if ($user_group[$member_id['user_group']]['allow_url']) $link_icon = "link dleleech | "; else $link_icon = "";
	if ($user_group[$member_id['user_group']]['allow_image']) $link_icon .= "image ";
	if ($user_group[$member_id['user_group']]['allow_up_image']) $link_icon .= "dleupload ";

	
	$onload_scripts[] = <<<HTML
	tinymce.init({
		selector: 'textarea#comments',
		language : "{$lang['wysiwyg_language']}",
		width : "100%",
		height : 220,
		plugins: ["link image paste dlebutton"],
		theme: "modern",
		relative_urls : false,
		convert_urls : false,
		remove_script_host : false,
		extended_valid_elements : "div[align|class|style|id|title]",
		paste_as_text: true,
		toolbar_items_size: 'small',
		statusbar : false,
		
		dle_root : dle_root,
		dle_upload_area : "comments",
		dle_upload_user : "{$p_name}",
		dle_upload_news : "{$p_id}",
		
		menubar: false,
		toolbar1: "bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | {$link_icon}dleemo | bullist numlist | dlequote dlehide",
		dle_root : "{$config['http_home_url']}",
		content_css : "{$config['http_home_url']}engine/editor/css/content.css"

	});
HTML;

$wysiwyg = <<<HTML
<script type="text/javascript">
	var text_upload = "$lang[bb_t_up]";
</script>
    <textarea id="comments" name="comments" rows="10" cols="50">{$text}</textarea>
HTML;


}


if ( $allow_subscribe ) $wysiwyg .= "<br /><input type=\"checkbox\" name=\"allow_subscribe\" id=\"allow_subscribe\" value=\"1\" /><label for=\"allow_subscribe\">&nbsp;&nbsp;" . $lang['c_subscribe'] . "</label><br />";


?>