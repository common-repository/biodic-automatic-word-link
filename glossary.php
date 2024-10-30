<?php
/*
  Plugin Name: BioDic Automatic Word Link
  Plugin URI: https://www.biodic.net/
  Description: Crea enlaces automáticos a las palabras que se encuentran definidas en https://www.biodic.net.
  Version: 1.5
  Author: BioScripts - Centro de Investigación y Desarrollo de Recursos Científicos
 */
define('RED_MENU_OPTION', 'biodic_menu_option');
define('RED_ABOUT_OPTION', 'biodic_about');
define('RED_PRO_OPTION', 'biodic_pro');
define('RED_SETTINGS_OPTION', 'biodic_settings');
//Add options needed for plugin
//add_option('biodic_glossaryOnlySingle', 0); //Show on Home and Category Pages or just single post pages?
//add_option('biodic_glossaryOnPages', 1); //Show on Pages or just posts?
//add_option('biodic_glossaryID', 0); //The ID of the main Glossary Page
add_option('biodic_glossaryTooltip', 0); //Use tooltips on glossary items?
//add_option('biodic_glossaryDiffLinkClass', 0); //Use different class to style glossary list
//add_option('biodic_glossaryListTiles', 0); // Display glossary terms list as tiles
//add_option('biodic_glossaryPermalink', 'glossary'); //Set permalink name
add_option('biodic_glossaryFirstOnly', 0); //Search for all occurances in a post or only one?
add_option('biodic_glossaryFilterTooltip', 30); //Clean the tooltip text fromuneeded chars?
add_option('biodic_glossaryLimitTooltip', 0); // Limit the tooltip length  ?
//add_option('biodic_glossaryTermLink', 0); //Remove links to glossary page
add_option('biodic_glossaryExcerptHover', 0); //Search for all occurances in a post or only one?
add_option('biodic_glossaryProtectedTags', 1); //SAviod the use of Glossary in Protected tags?
add_option('biodic_glossaryCaseSensitive', 0); //Case sensitive?
add_option('biodic_glossaryInNewPage', 0); //In New Page?
add_option('biodic_showTitleAttribute', 1); //show HTML title attribute
// Register glossary custom post type
//register_activation_hook(__FILE__, 'biodic_autoInstallIndexPage');




function biodic_admin_menu()
{
    $page = add_menu_page('BioDic AWL', 'BioDic AWL', 'manage_options', RED_SETTINGS_OPTION, 'glossary_options');
    add_filter('views_edit-glossary', 'biodic_filter_admin_nav', 10, 1);
}

add_action('admin_menu', 'biodic_admin_menu');

function biodic_adminMenu()
{

}


function biodic_showNav()
{
    global $submenu, $plugin_page, $pagenow;
    $submenus = array();
    if( isset($submenu[RED_MENU_OPTION]) )
    {
        $thisMenu = $submenu[RED_MENU_OPTION];
        foreach($thisMenu as $item)
        {
            $slug = $item[2];
            $isCurrent = $slug == $plugin_page;
            $url = (strpos($item[2], '.php') !== false || strpos($slug, 'http://') !== false) ? $slug : get_admin_url('', 'admin.php?page=' . $slug);
            $submenus[] = array(
                'link'    => $url,
                'title'   => $item[0],
                'current' => $isCurrent
            );
        }
        require('admin_nav.php');
    }
}

function glossary_flush_rewrite_rules()
{

    // First, we "add" the custom post type via the above written function.
    //biodic_create_post_types();
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'glossary_flush_rewrite_rules');

//Function parses through post entries and replaces any found glossary terms with links to glossary term page.
//Add tooltip stylesheet & javascript to page first
function biodic_glossary_js()
{
    $glossary_path = WP_PLUGIN_URL . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__));
    wp_enqueue_script('tooltip-js', $glossary_path . 'tooltip.js', array('jquery'));
}

add_action('wp_print_scripts', 'biodic_glossary_js');

function biodic_glossary_css()
{
    $glossary_path = WP_PLUGIN_URL . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__));
    wp_enqueue_style('tooltip-css', $glossary_path . 'tooltip.css');
}

add_action('wp_print_styles', 'biodic_glossary_css');

// Sort longer titles first, so if there is collision between terms (e.g.,
// "essential fatty acid" and "fatty acid") the longer one gets created first.
function sortByWPQueryObjectTitleLength($a, $b)
{
    $sortVal = 0;
    if( property_exists($a, 'post_title') && property_exists($b, 'post_title') )
    {
        $sortVal = strlen($b->post_title) - strlen($a->post_title);
    }
    return $sortVal;
}

$foundMatches = array();

function biodic_glossary_replace_matches($match)
{
    global $foundMatches, $glossaryLoopCurrentId;
    $foundMatches[] = array('id' => $glossaryLoopCurrentId, 'text' => $match[0]);
    return '##GLOSSARY' . (count($foundMatches) - 1) . '##';
}

function biodic_glossary_filterTooltipContent($glossaryItemContent)
{
    $glossaryItemContent = str_replace('[biodic_exclude]', '', $glossaryItemContent);
    $glossaryItemContent = str_replace('[/biodic_exclude]', '', $glossaryItemContent);

    if( get_option('biodic_glossaryFilterTooltip') == 1 )
    {
        // remove paragraph, bad chars from tooltip text
        $glossaryItemContent = str_replace(chr(10), "", $glossaryItemContent);
        $glossaryItemContent = str_replace(chr(13), "", $glossaryItemContent);
        $glossaryItemContent = str_replace('</p>', '<br/>', $glossaryItemContent);
        $glossaryItemContent = str_replace('</ul>', '<br/>', $glossaryItemContent);
        $glossaryItemContent = str_replace('<li>', '<br/>', $glossaryItemContent);
        $glossaryItemContent = strip_only($glossaryItemContent, '<li>');
        $glossaryItemContent = strip_only($glossaryItemContent, '<ul>');
        $glossaryItemContent = strip_only($glossaryItemContent, '<p>');
        $glossaryItemContent = strip_only($glossaryItemContent, '<img>');
        $glossaryItemContent = strip_only($glossaryItemContent, '<a>');
        $glossaryItemContent = htmlspecialchars($glossaryItemContent);
        $glossaryItemContent = esc_attr($glossaryItemContent);
        $glossaryItemContent = str_replace("color:#000000", "color:#ffffff", $glossaryItemContent);
        $glossaryItemContent = str_replace('\\[biodic_exclude\\]', '', $glossaryItemContent);
    }
    else
    {
        $glossaryItemContent = strtr($glossaryItemContent, array("\r\n" => '<br />', "\r" => '<br />', "\n" => '<br />'));

//        $glossaryItemContent = htmlentities($glossaryItemContent);
    }

    if( (get_option('biodic_glossaryLimitTooltip') > 30) && (strlen($glossaryItemContent) > get_option('biodic_glossaryLimitTooltip')) )
    {
        $glossaryItemContent = substr($glossaryItemContent, 0, get_option('biodic_glossaryLimitTooltip')) . '    <strong>   &raquo; Leer más en BioDic<strong>';
    }
//    $glossaryItemContent = str_replace('\'', '\\\'', $glossaryItemContent);
    return esc_attr($glossaryItemContent);
}



function biodic_glossary_parse($content)
{

    //Run the glossary parser
    if( ((!is_page() && get_option('biodic_glossaryOnlySingle') == 0) OR
            (!is_page() && get_option('biodic_glossaryOnlySingle') == 1 && is_single()) OR
            (is_page() && get_option('biodic_glossaryOnPages') == 1) ) )
    {
     
     global $post;
        
        //Consulta to biodic
        $glossary_index=array();
        /*$glossary_index = get_children(array(
            'post_type'   => 'post',
            'post_status' => 'publish',
            'order'       => 'DESC',
            'orderby'     => 'title'
        ));*/
        
        $d = file_get_contents(plugin_dir_path( __FILE__ ).'biodic_21-08-2018.txt');
        //$d = file_get_contents('https://www.biodic.net/biodic.txt');
        //$d = file_get_contents('https://www.biodic.net/palabrasWP.php');
        //$d = stream_get_contents(fopen('https://www.biodic.net/palabrasWP.php',"r"));
        //echo $d;
        $lineas = explode("\n", $d);
        for($i=0; $i<count($lineas); $i++){
        	$bio = explode("|", $lineas[$i]);
        	if(mb_strlen($bio[1])<4){}else{
	        $data[$i] = new stdClass((int)$bio[0]);
	        $data[$i]->ID = (int)$bio[0];
			$data[$i]->post_title = $bio[1];
			$data[$i]->post_content = $bio[4];
			$data[$i]->post_status = 'publish';
			$data[$i]->post_type = 'post';
			$data[$i]->guid = $bio[3];	
			$data[$i]->post_name = $bio[2];	
			$glossary_index[] = $data[$i];
			}
        }
		

		/*echo '<pre>';
        print_r($glossary_index);
        echo '</pre>';*/
		
          
		  //$glossary_index = array_merge($glossary_index,$my_post);
        // Sort by title length (function above)
        uasort($glossary_index, 'sortByWPQueryObjectTitleLength');
        //the tag:[biodic_exclude]+[/biodic_exclude] can be used to mark text will not be taken into account by the glossary
        if( $glossary_index )
        {
            $timestamp = time();

            $excludeGlossary_regex = '/\\['                              // Opening bracket
                    . '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
                    . "(biodic_exclude)"                     // 2: Shortcode name
                    . '\\b'                              // Word boundary
                    . '('                                // 3: Unroll the loop: Inside the opening shortcode tag
                    . '[^\\]\\/]*'                   // Not a closing bracket or forward slash
                    . '(?:'
                    . '\\/(?!\\])'               // A forward slash not followed by a closing bracket
                    . '[^\\]\\/]*'               // Not a closing bracket or forward slash
                    . ')*?'
                    . ')'
                    . '(?:'
                    . '(\\/)'                        // 4: Self closing tag ...
                    . '\\]'                          // ... and closing bracket
                    . '|'
                    . '\\]'                          // Closing bracket
                    . '(?:'
                    . '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
                    . '[^\\[]*+'             // Not an opening bracket
                    . '(?:'
                    . '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
                    . '[^\\[]*+'         // Not an opening bracket
                    . ')*+'
                    . ')'
                    . '\\[\\/\\2\\]'             // Closing shortcode tag
                    . ')?'
                    . ')'
                    . '(\\]?)/s';

            // define regular expression of tags Glossary should aviod
            if( get_option('biodic_glossaryProtectedTags') == 1 )
            {
                $pre_regex = '/<pre[^>]*>(.*?)<\/pre>/si';
                $object_regex = '/<object[^>]*>(.*?)<\/object>/si';
                $span_regxA = '/<a[^>]*>(.*?)<\/a>/si';
                $span_regxH1 = '/<h1[^>]*>(.*?)<\/h1>/si';
                $span_regxH2 = '/<h2[^>]*>(.*?)<\/h2>/si';
                $span_regxH3 = '/<h3[^>]*>(.*?)<\/h3>/si';
                $script_regex = '/<script[^>]*>(.*?)<\/script>/si';
                $pretags = array();
                $objecttags = array();
                $spantagsA = array();
                $spantagsH1 = array();
                $spantagsH2 = array();
                $spantagsH3 = array();
                $scripttags = array();

                $preTagsCount = preg_match_all($pre_regex, $content, $pretags, PREG_PATTERN_ORDER);
                $i = 0;

                if( $preTagsCount > 0 )
                {
                    foreach($pretags[0] as $pretag)
                    {
                        $content = preg_replace($pre_regex, '#' . $i . 'pre', $content, 1);
                        $i++;
                    }
                }

                $objectTagsCount = preg_match_all($object_regex, $content, $objecttags, PREG_PATTERN_ORDER);
                $i = 0;

                if( $objectTagsCount > 0 )
                {
                    foreach($objecttags[0] as $objecttag)
                    {
                        $content = preg_replace($object_regex, '#' . $i . 'object', $content, 1);
                        $i++;
                    }
                }


                $spanATagsCount = preg_match_all($span_regxA, $content, $spantagsA, PREG_PATTERN_ORDER);
                $i = 0;

                if( $spanATagsCount > 0 )
                {
                    foreach($spantagsA[0] as $spantagA)
                    {
                        $content = preg_replace($span_regxA, '#' . $i . 'a', $content, 1);
                        $i++;
                    }
                }


                $spanH1TagsCount = preg_match_all($span_regxH1, $content, $spantagsH1, PREG_PATTERN_ORDER);
                $i = 0;

                if( $spanH1TagsCount > 0 )
                {
                    foreach($spantagsH1[0] as $spantagH1)
                    {
                        $content = preg_replace($span_regxH1, '#' . $i . 'H1', $content, 1);
                        $i++;
                    }
                }

                $spanH2TagsCount = preg_match_all($span_regxH2, $content, $spantagsH2, PREG_PATTERN_ORDER);
                $i = 0;

                if( $spanH2TagsCount > 0 )
                {
                    foreach($spantagsH2[0] as $spantagH2)
                    {
                        $content = preg_replace($span_regxH2, '#' . $i . 'H2', $content, 1);
                        $i++;
                    }
                }

                $spanH3TagsCount = preg_match_all($span_regxH3, $content, $spantagsH3, PREG_PATTERN_ORDER);
                $i = 0;

                if( $spanH3TagsCount > 0 )
                {
                    foreach($spantagsH3[0] as $spantagH3)
                    {
                        $content = preg_replace($span_regxH3, '#' . $i . 'H3', $content, 1);
                        $i++;
                    }
                }


                $scriptTagsCount = preg_match_all($script_regex, $content, $scripttags, PREG_PATTERN_ORDER);
                $i = 0;

                if( $scriptTagsCount > 0 )
                {
                    foreach($scripttags[0] as $scripttag)
                    {
                        $content = preg_replace($script_regex, '#' . $i . 'script', $content, 1);
                        $i++;
                    }
                }
            }

            $excludeGlossaryStrs = array();

            //replace exclude tags and content between them in purpose to save the original text as is
            //before glossary plug go over the content and add its code
            //(later will be returned to the marked places in content)

            $excludeTagsCount = preg_match_all($excludeGlossary_regex, $content, $excludeGlossaryStrs, PREG_PATTERN_ORDER);
            $i = 0;

            if( $excludeTagsCount > 0 )
            {
                foreach($excludeGlossaryStrs[0] as $excludeStr)
                {
                    $content = preg_replace($excludeGlossary_regex, '#' . $i . 'excludeGlossary', $content, 1);
                    $i++;
                }
            }
            $content = str_replace(array(
                '&#038;',
                '&#8216;',
                '&#8217;',
                '&amp;',
                '&#39;',
                '&lsquo;',
                '&#145;',
                '&rsquo;',
                '&#146;',
                    ), array(
                htmlspecialchars('&', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars('\'', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars('\'', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars('&', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars('\'', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars('‘', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars('‘', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars('`', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars('`', ENT_QUOTES, 'UTF-8')
                    ), $content);
            $replaceRules = array();
            global $foundMatches, $glossaryLoopCurrentId;
            $foundMatches = array();
            foreach($glossary_index as $glossary_item)
            {
                $glossaryLoopCurrentId = $glossary_item->ID;
                $timestamp++;

                $glossary_title = htmlspecialchars(trim($glossary_item->post_title), ENT_QUOTES, 'UTF-8');
//                    $glossary_title = str_replace('\'', '&#39;', $glossary_title);

                $post = $GLOBALS['post'];
                if(is_string($post->post_title) && ($post->post_title == $glossary_item->post_title || strpos($post->post_title, $glossary_item->post_title) !== false) )
                {
                    continue;
                }
				
				$glossarylinkbio = $glossary_item->guid;
				
                //old code bug-doesn't take into account href='' takes into account only href="")
                //$glossary_search = '/\b'.$glossary_title.'s*?\b(?=([^"]*"[^"]*")*[^"]*$)/i';
                $glossary_title = preg_quote($glossary_title, '/');
                $caseSensitive = get_option('biodic_glossaryCaseSensitive', 0);
                $glossary_search = '/(^|(?=\s|\b|\W))' . (!$caseSensitive ? '(?i)' : '') . $glossary_title . '((?=\s|\W)|$)(?=([^"]*"[^"]*")*[^"]*$)(?=([^\']*\'[^\']*\')*[^\']*$)(?!<\/a[0-9]+)/u';
                $glossary_replace = '<a' . $timestamp . '>$0</a' . $timestamp . '>';
                $origContent = $content;

                if( get_option('biodic_glossaryFirstOnly') == 1 )
                {
                    $content_temp = preg_replace_callback($glossary_search, 'biodic_glossary_replace_matches', $content, 1);
                }
                else
                {
                    $content_temp = preg_replace_callback($glossary_search, 'biodic_glossary_replace_matches', $content);
                }
                $content_temp = rtrim($content_temp);

//                $addition = '';
//                $link_search = '/<a' . $timestamp . '>(' . (!$caseSensitive ? '(?i)' : '') . '(' . preg_quote($glossary_item->post_title, '/') . $addition . ')[A-Za-z]*?)<\/a' . $timestamp . '>/u';
                $newWindowsOption = get_option('biodic_glossaryInNewPage') == 1;
                $windowTarget = '';
                if( $newWindowsOption )
                {
                    $windowTarget = ' target="_blank" ';
                }
                if( get_option('biodic_glossaryTooltip') == 1 )
                {
                    if( get_option('biodic_glossaryExcerptHover') && $glossary_item->post_excerpt )
                    {
                        $glossaryItemContent = $glossary_item->post_excerpt;
                    }
                    else
                    {
                        $glossaryItemContent = $glossary_item->post_content;
                    }
                    $glossaryItemContent = biodic_glossary_filterTooltipContent($glossaryItemContent);

                    $titleAttr = (get_option('biodic_showTitleAttribute') == 1) ? ' title="Glossary: ' . esc_attr($glossary_item->post_title) . '" ' : '';

                    if( get_option('biodic_glossaryTermLink') == 1 )
                    {
                        $link_replace = '<span ' . $titleAttr . ' data-tooltip="' . $glossaryItemContent . '" class="glossaryLink">##TITLE##</span>';
                    }
                    else
                    {
                        $link_replace = '<a href="' . $glossarylinkbio . '" ' . $titleAttr . ' data-tooltip="' . $glossaryItemContent . '"  class="glossaryLink"' . $windowTarget . '>##TITLE##</a>';
                    }
                }
                else
                {
                    if( get_option('biodic_glossaryTermLink') == 1 )
                    {
                        $link_replace = '<span  ' . $titleAttr . ' class="glossaryLink">##TITLE##</span>';
                    }
                    else
                    {
                        $link_replace = '<a href="' . $glossarylinkbio . '" ' . $titleAttr . ' class="glossaryLink"' . $windowTarget . '>##TITLE##</a>';
                    }
                }
                $replaceRules[$glossary_item->ID] = $link_replace;
//                $content_temp = preg_replace($link_search, $link_replace, $content_temp);
                $content = $content_temp;
            }

            foreach($foundMatches as $number => $data)
            {
                $template = str_replace('##TITLE##', $data['text'], $replaceRules[$data['id']]);
                $content = str_replace('##GLOSSARY' . $number . '##', $template, $content);
            }


//            foreach ($replaceRules as $timestamp => $rule) {
//                $content = preg_replace($rule['link_search'], $rule['link_replace'], $content);
//            }

            if( $excludeTagsCount > 0 )
            {
                $i = 0;
                foreach($excludeGlossaryStrs[0] as $excludeStr)
                {
                    $content = str_replace('#' . $i . 'excludeGlossary', $excludeStr, $content);
                    $i++;
                }
                //remove all the exclude signs
                $content = str_replace('[/biodic_exclude]', "", str_replace('[biodic_exclude]', "", $content));
            }


            if( get_option('biodic_glossaryProtectedTags') == 1 )
            {

                if( $preTagsCount > 0 )
                {
                    $i = 0;
                    foreach($pretags[0] as $pretag)
                    {
                        $content = str_replace('#' . $i . 'pre', $pretag, $content);
                        $i++;
                    }
                }

                if( $objectTagsCount > 0 )
                {
                    $i = 0;
                    foreach($objecttags[0] as $objecttag)
                    {
                        $content = str_replace('#' . $i . 'object', $objecttag, $content);
                        $i++;
                    }
                }




                if( $spanH1TagsCount > 0 )
                {
                    $i = 0;
                    foreach($spantagsH1[0] as $spantagH1Content)
                    {
                        $content = str_replace('#' . $i . 'H1', $spantagH1Content, $content);
                        $i++;
                    }
                }

                if( $spanH2TagsCount > 0 )
                {
                    $i = 0;
                    foreach($spantagsH2[0] as $spantagH2Content)
                    {
                        $content = str_replace('#' . $i . 'H2', $spantagH2Content, $content);
                        $i++;
                    }
                }

                if( $spanH3TagsCount > 0 )
                {
                    $i = 0;
                    foreach($spantagsH3[0] as $spantagH3Content)
                    {
                        $content = str_replace('#' . $i . 'H3', $spantagH3Content, $content);
                        $i++;
                    }
                }

                if( $spanATagsCount > 0 )
                {
                    $i = 0;
                    foreach($spantagsA[0] as $spantagAContent)
                    {
                        $content = str_replace('#' . $i . 'a', $spantagAContent, $content);
                        $i++;
                    }
                }
                if( $scriptTagsCount > 0 )
                {
                    $i = 0;
                    foreach($scripttags[0] as $scriptContent)
                    {
                        $content = str_replace('#' . $i . 'script', $scriptContent, $content);
                        $i++;
                    }
                }
            }
        }
    }
    //Future caché as custom field
	//add_post_meta($post->ID, 'the_content_biodic', $content, true);
    //add_post_meta($post->ID, 'the_content_biodic_update', date('Y-m-d H:i:s'), true);

    return $content;
    
}

//Make sure parser runs before the post or page content is outputted
add_filter('the_content', 'biodic_glossary_parse');
//add_filter('content_save_pre', 'biodic_glossary_parse');

function glossary_options()
{
    if( isset($_POST["biodic_glossarySave"]) )
    {
        //update the page options
        update_option('biodic_glossaryID', $_POST["biodic_glossaryID"]);
        update_option('biodic_glossaryID', $_POST["biodic_glossaryPermalink"]);
        $options_names = array('biodic_glossaryOnlySingle', 'biodic_glossaryOnPages', 'biodic_glossaryTooltip', 'biodic_glossaryDiffLinkClass', 'biodic_glossaryListTiles', 'biodic_glossaryFirstOnly', 'biodic_glossaryLimitTooltip', 'biodic_glossaryFilterTooltip', 'biodic_glossaryTermLink', 'biodic_glossaryExcerptHover', 'biodic_glossaryProtectedTags', 'biodic_glossaryCaseSensitive', 'biodic_glossaryInNewPage', 'biodic_showTitleAttribute');
        foreach($options_names as $option_name)
        {
            if( $_POST[$option_name] == 1 )
            {
                update_option($option_name, 1);
            }
            else
            {
                update_option($option_name, 0);
            }
        }
    }
    ob_start();
    require('admin_settings.php');
    $content = ob_get_contents();
    ob_end_clean();
    require('admin_template.php');
}

function strip_only($str, $tags, $stripContent = false)
{
    $content = '';
    if( !is_array($tags) )
    {
        $tags = (strpos($str, '>') !== false ? explode('>', str_replace('<', '', $tags)) : array($tags));
        if( end($tags) == '' ) array_pop($tags);
    }
    foreach($tags as $tag)
    {
        if( $stripContent ) $content = '(.+</' . $tag . '[^>]*>|)';
        $str = preg_replace('#</?' . $tag . '[^>]*>' . $content . '#is', '', $str);
    }
    return $str;
}
