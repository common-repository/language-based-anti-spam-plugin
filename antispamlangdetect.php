<?php
/*
Plugin Name: Language-based Comment Spam Condom
Plugin URI: http://www.seoblackout.com/forum
Description: Deletes comments seen as spam. By <a href="http://www.theblackmelvyn.com">BlackMelvyn</a>, based on <a href="http://www.seoblackout.com" target="_blank">Tiger</a>'s script
Author: BlackMelvyn & Tiger
Version: 1.1
Author URI: http://www.seoblackout.com/forum
*/

	function removeBOM($str = ""){
		if(substr($str, 0, 3) == pack("CCC",0xef,0xbb,0xbf)){
			$str = substr($str, 3);
		}
		return $str;
	}

//	Détection de la langue du commentaire
	function Google_Language_Detection($text, $langue, $langue2){
		$url = 'http://api.microsofttranslator.com/V2/Ajax.svc/Detect?appid=B8FE5B4B69BD3F6E040E4FE67B3E2DD6D40DA9B8&text='.urlencode($text);
		if(function_exists('curl_init')){
			$userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$url);
			curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
			curl_setopt($ch, CURLOPT_FAILONERROR, true);
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			curl_close ($ch);
		} 
		else{
			$result = file_get_contents($url);
		}
		
		$lang = removeBOM(str_replace('"', '', $result));
		if($lang == $langue || $lang==$langue2){
			return true;
		}
		else{
			return false;
		}
	}
	
	//	Compter le nombre de liens
	function checkNumLinks($comment_content){
		$regex = '~(http:\/\/.*)~U';
		if(preg_match_all($regex, $comment_content, $m)){
			//	Si plus de 2 liens, suspiscion de SPAM 
			if(count($m[1]) > 2){
				return false;
			}
			else{
				return true;
			}
		}
		else{
			return true;
		}
	}

class lbcsc{
	
	
	//	Vérification du texte et mise à jour du statut du commentaire
	function comment_post($id){
		global $wpdb;
		$langue = get_option('lbcsc_blog_lang');
		$langue2 = get_option('lbcsc_alt_lang');
		
		$error_message = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="fr-FR">
			<head>
				<title>Spam Attempt Detected</title>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
				<link rel="stylesheet" href="'.get_bloginfo('url').'/wp-admin/css/login.css" type="text/css" media="all" />
			<link rel="stylesheet" href="'.get_bloginfo('url').'/wp-admin/css/colors-fresh.css" type="text/css" media="all" />
			</head>
			<body class="login">
			<p><br /></p>
			<div style="width:500px; margin:auto;">
				<p class="message">Spam attempt detected... Please use the blog\'s writing language or use an online translation tool.<br />Please check that your comment does not have too many links.<br /><a href="'.$_SERVER['HTTP_REFERER'].'">Back</a></p>
				<p id="backtoblog"><a href="'.$_SERVER['HTTP_REFERER'].'">&laquo; Back to previous page</a></p>
			</div>
			</body>
			</html>';
		
		$sql = "SELECT comment_content FROM $wpdb->comments WHERE $wpdb->comments.comment_ID=".$id;
		$results = $wpdb->get_results($sql, OBJECT);
		$comment_content = '';
		$comment_content = $results[0]->comment_content;
		
		$count_content = strlen($comment_content);
		if ($count_content<150){
			$text = $comment_content;
		}
		else{
			$newart = substr($comment_content, 0, 150);
			$pos = strrpos($newart, " ");
			$text = substr($newart, 0, $pos);
		}
		
		if(!Google_Language_Detection($text, $langue, $langue2)){
			wp_set_comment_status($id, 'delete');
			echo $error_message;
			exit();
		}
		elseif(!checkNumLinks($comment_content)){
			echo $error_message;
			exit();
		}
		else{
			wp_set_comment_status($id, 'hold');
		}
	}
	
	//	Insertion dans le menu principal de l'admin
	function langComment_admin_option(){
	if (function_exists('add_submenu_page')){
			add_options_page('Language Anti-Spam', 'Language Anti-Spam', 8, basename(__FILE__), array('lbcsc','langComment_admin_menu'));
		}
	}
	
	//	Interface admin
	function langComment_admin_menu(){
		echo '<div class="wrap">';
		echo '<h2>Language-based comment spam condom</h2>';
		echo '<p>This plugin determines the language of the comment and compares it to your blog\'s language.<br />
					There is a minimum confidence index that will automatically delete the comment if it is not reached.<br />
					So basically, if your site is in English and is spammed by Russian spammers, you will never see the comments :) </p>';
		if(!get_option('lbcsc_blog_lang')) add_option('lbcsc_blog_lang', 'fr');
		if(!get_option('lbcsc_alt_lang')) add_option('lbcsc_alt_lang', 'ca');
		//	Enregistrement des préférences de langue
		if(isset($_POST['lbcsc_blog_lang']) && isset($_POST['lbcsc_alt_lang']) && trim($_POST['lbcsc_blog_lang']) != '' && trim($_POST['lbcsc_alt_lang']) != ''){
			delete_option('lbcsc_blog_lang');
			delete_option('lbcsc_alt_lang');
			add_option('lbcsc_blog_lang', trim($_POST['lbcsc_blog_lang']));
			add_option('lbcsc_alt_lang', trim($_POST['lbcsc_alt_lang']));
						
			echo '<br /><div class="updated"><br />Preferences have been saved !<br /><br /></div>';
		}
		
		//	Affichage formulaire options
			echo '<h3>Edit your preferences</h3>';
			$lbcsc_blog_opt = get_option('lbcsc_blog_lang');
			$lbcsc_alt_opt = get_option('lbcsc_alt_lang');
			
			echo '<form action="'.htmlentities($_SERVER['REQUEST_URI']).'" method="post">
			<fieldset class="options">
				<p><select name="lbcsc_blog_lang" id="lbcsc_blog_check">
					<option '.(($lbcsc_blog_opt=='sq')?'selected="selected"' : '').' value="sq">Albanian</option>
					<option '.(($lbcsc_blog_opt=='ar')?'selected="selected"' : '').' value="ar">Arabic</option>
					<option '.(($lbcsc_blog_opt=='bg')?'selected="selected"' : '').' value="bg">Bulgarian</option>
					<option '.(($lbcsc_blog_opt=='ca')?'selected="selected"' : '').' value="ca">Catalan</option>
					<option '.(($lbcsc_blog_opt=='zh-CN')?'selected="selected"' : '').' value="zh-CN">Chinese (Simplified)</option>
					<option '.(($lbcsc_blog_opt=='zh-TW')?'selected="selected"' : '').' value="zh-TW">Chinese (Traditional)</option>
					<option '.(($lbcsc_blog_opt=='hr')?'selected="selected"' : '').' value="hr">Croatian</option>
					<option '.(($lbcsc_blog_opt=='cs')?'selected="selected"' : '').' value="cs">Czech</option>
					<option '.(($lbcsc_blog_opt=='da')?'selected="selected"' : '').' value="da">Danish</option>
					<option '.(($lbcsc_blog_opt=='nl')?'selected="selected"' : '').' value="nl">Dutch</option>
					<option '.(($lbcsc_blog_opt=='en')?'selected="selected"' : '').' value="en">English</option>
					<option '.(($lbcsc_blog_opt=='et')?'selected="selected"' : '').' value="et">Estonian</option>
					<option '.(($lbcsc_blog_opt=='tl')?'selected="selected"' : '').' value="tl">Filipino</option>
					<option '.(($lbcsc_blog_opt=='fi')?'selected="selected"' : '').' value="fi">Finnish</option>
					<option '.(($lbcsc_blog_opt=='fr')?'selected="selected"' : '').' value="fr">French</option>
					<option '.(($lbcsc_blog_opt=='gl')?'selected="selected"' : '').' value="gl">Galician</option>
					<option '.(($lbcsc_blog_opt=='de')?'selected="selected"' : '').' value="de">German</option>
					<option '.(($lbcsc_blog_opt=='el')?'selected="selected"' : '').' value="el">Greek</option>
					<option '.(($lbcsc_blog_opt=='iw')?'selected="selected"' : '').' value="iw">Hebrew</option>
					<option '.(($lbcsc_blog_opt=='hi')?'selected="selected"' : '').' value="hi">Hindi</option>
					<option '.(($lbcsc_blog_opt=='hu')?'selected="selected"' : '').' value="hu">Hungarian</option>
					<option '.(($lbcsc_blog_opt=='id')?'selected="selected"' : '').' value="id">Indonesian</option>
					<option '.(($lbcsc_blog_opt=='it')?'selected="selected"' : '').' value="it">Italian</option>
					<option '.(($lbcsc_blog_opt=='ja')?'selected="selected"' : '').' value="ja">Japanese</option>
					<option '.(($lbcsc_blog_opt=='ko')?'selected="selected"' : '').' value="ko">Korean</option>
					<option '.(($lbcsc_blog_opt=='lv')?'selected="selected"' : '').' value="lv">Latvian</option>
					<option '.(($lbcsc_blog_opt=='lt')?'selected="selected"' : '').' value="lt">Lithuanian</option>
					<option '.(($lbcsc_blog_opt=='mt')?'selected="selected"' : '').' value="mt">Maltese</option>
					<option '.(($lbcsc_blog_opt=='no')?'selected="selected"' : '').' value="no">Norwegian</option>
					<option '.(($lbcsc_blog_opt=='pl')?'selected="selected"' : '').' value="pl">Polish</option>
					<option '.(($lbcsc_blog_opt=='pt')?'selected="selected"' : '').' value="pt">Portuguese</option>
					<option '.(($lbcsc_blog_opt=='ro')?'selected="selected"' : '').' value="ro">Romanian</option>
					<option '.(($lbcsc_blog_opt=='ru')?'selected="selected"' : '').' value="ru">Russian</option>
					<option '.(($lbcsc_blog_opt=='sr')?'selected="selected"' : '').' value="sr">Serbian</option>
					<option '.(($lbcsc_blog_opt=='sk')?'selected="selected"' : '').' value="sk">Slovak</option>
					<option '.(($lbcsc_blog_opt=='sl')?'selected="selected"' : '').' value="sl">Slovenian</option>
					<option '.(($lbcsc_blog_opt=='es')?'selected="selected"' : '').' value="es">Spanish</option>
					<option '.(($lbcsc_blog_opt=='sv')?'selected="selected"' : '').' value="sv">Swedish</option>
					<option '.(($lbcsc_blog_opt=='th')?'selected="selected"' : '').' value="th">Thai</option>
					<option '.(($lbcsc_blog_opt=='tr')?'selected="selected"' : '').' value="tr">Turkish</option>
					<option '.(($lbcsc_blog_opt=='uk')?'selected="selected"' : '').' value="uk">Ukrainian</option>
					<option '.(($lbcsc_blog_opt=='vi')?'selected="selected"' : '').' value="vi">Vietnamese</option></select>
				&nbsp;<label for="lbcsc_blog_check">Blog language</label></p>
				
				<p><select name="lbcsc_alt_lang" id="lbcsc_alt_check">
					<option '.(($lbcsc_alt_opt=='sq')?'selected="selected"' : '').' value="sq">Albanian</option>
					<option '.(($lbcsc_alt_opt=='ar')?'selected="selected"' : '').' value="ar">Arabic</option>
					<option '.(($lbcsc_alt_opt=='bg')?'selected="selected"' : '').' value="bg">Bulgarian</option>
					<option '.(($lbcsc_alt_opt=='ca')?'selected="selected"' : '').' value="ca">Catalan</option>
					<option '.(($lbcsc_alt_opt=='zh-CN')?'selected="selected"' : '').' value="zh-CN">Chinese (Simplified)</option>
					<option '.(($lbcsc_alt_opt=='zh-TW')?'selected="selected"' : '').' value="zh-TW">Chinese (Traditional)</option>
					<option '.(($lbcsc_alt_opt=='hr')?'selected="selected"' : '').' value="hr">Croatian</option>
					<option '.(($lbcsc_alt_opt=='cs')?'selected="selected"' : '').' value="cs">Czech</option>
					<option '.(($lbcsc_alt_opt=='da')?'selected="selected"' : '').' value="da">Danish</option>
					<option '.(($lbcsc_alt_opt=='nl')?'selected="selected"' : '').' value="nl">Dutch</option>
					<option '.(($lbcsc_alt_opt=='en')?'selected="selected"' : '').' value="en">English</option>
					<option '.(($lbcsc_alt_opt=='et')?'selected="selected"' : '').' value="et">Estonian</option>
					<option '.(($lbcsc_alt_opt=='tl')?'selected="selected"' : '').' value="tl">Filipino</option>
					<option '.(($lbcsc_alt_opt=='fi')?'selected="selected"' : '').' value="fi">Finnish</option>
					<option '.(($lbcsc_alt_opt=='fr')?'selected="selected"' : '').' value="fr">French</option>
					<option '.(($lbcsc_alt_opt=='gl')?'selected="selected"' : '').' value="gl">Galician</option>
					<option '.(($lbcsc_alt_opt=='de')?'selected="selected"' : '').' value="de">German</option>
					<option '.(($lbcsc_alt_opt=='el')?'selected="selected"' : '').' value="el">Greek</option>
					<option '.(($lbcsc_alt_opt=='iw')?'selected="selected"' : '').' value="iw">Hebrew</option>
					<option '.(($lbcsc_alt_opt=='hi')?'selected="selected"' : '').' value="hi">Hindi</option>
					<option '.(($lbcsc_alt_opt=='hu')?'selected="selected"' : '').' value="hu">Hungarian</option>
					<option '.(($lbcsc_alt_opt=='id')?'selected="selected"' : '').' value="id">Indonesian</option>
					<option '.(($lbcsc_alt_opt=='it')?'selected="selected"' : '').' value="it">Italian</option>
					<option '.(($lbcsc_alt_opt=='ja')?'selected="selected"' : '').' value="ja">Japanese</option>
					<option '.(($lbcsc_alt_opt=='ko')?'selected="selected"' : '').' value="ko">Korean</option>
					<option '.(($lbcsc_alt_opt=='lv')?'selected="selected"' : '').' value="lv">Latvian</option>
					<option '.(($lbcsc_alt_opt=='lt')?'selected="selected"' : '').' value="lt">Lithuanian</option>
					<option '.(($lbcsc_alt_opt=='mt')?'selected="selected"' : '').' value="mt">Maltese</option>
					<option '.(($lbcsc_alt_opt=='no')?'selected="selected"' : '').' value="no">Norwegian</option>
					<option '.(($lbcsc_alt_opt=='pl')?'selected="selected"' : '').' value="pl">Polish</option>
					<option '.(($lbcsc_alt_opt=='pt')?'selected="selected"' : '').' value="pt">Portuguese</option>
					<option '.(($lbcsc_alt_opt=='ro')?'selected="selected"' : '').' value="ro">Romanian</option>
					<option '.(($lbcsc_alt_opt=='ru')?'selected="selected"' : '').' value="ru">Russian</option>
					<option '.(($lbcsc_alt_opt=='sr')?'selected="selected"' : '').' value="sr">Serbian</option>
					<option '.(($lbcsc_alt_opt=='sk')?'selected="selected"' : '').' value="sk">Slovak</option>
					<option '.(($lbcsc_alt_opt=='sl')?'selected="selected"' : '').' value="sl">Slovenian</option>
					<option '.(($lbcsc_alt_opt=='es')?'selected="selected"' : '').' value="es">Spanish</option>
					<option '.(($lbcsc_alt_opt=='sv')?'selected="selected"' : '').' value="sv">Swedish</option>
					<option '.(($lbcsc_alt_opt=='th')?'selected="selected"' : '').' value="th">Thai</option>
					<option '.(($lbcsc_alt_opt=='tr')?'selected="selected"' : '').' value="tr">Turkish</option>
					<option '.(($lbcsc_alt_opt=='uk')?'selected="selected"' : '').' value="uk">Ukrainian</option>
					<option '.(($lbcsc_alt_opt=='vi')?'selected="selected"' : '').' value="vi">Vietnamese</option></select>
				&nbsp;<label for="lbcsc_alt_check">Alt. blog language</label></p>
				<div class="submit"><input type="submit" value="Save options" /></div>
			</fieldset>
			</form>';
		echo '</div>';
	}
}
$lbcsc = new lbcsc();
add_action('comment_post', array('lbcsc','comment_post'));
add_action('admin_menu', array('lbcsc','langComment_admin_option'));
?>