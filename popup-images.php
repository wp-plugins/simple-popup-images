<?php
/*
Plugin Name: Simple Popup Images
Plugin URI: http://granades.com/simple-popup-images/
Description: Allows you to insert image thumbnails that, when clicked on, pop up a new window with a larger image. The popup windows can be made to vanish when the user clicks away from them. <b>Configuration</b>: <a href="options-general.php?page=popup-images/popup-images.php">Options &raquo; Simple Popup Images</a>.
Version: 0.7
Author: Stephen Granade
Author URI: http://granades.com/
*/

// Create the default location for the popup template "popup.php"
// By default this is the same directory as the plugin
add_option('popim_popup_template', popim_get_script_dir_url(),
	   'The path to the popup.php script on your website.');

// Create the directory value that corresponds to the root of the
// web server
add_option('popim_home_directory', ABSPATH,
	   'The directory on your server that corresponds to the root '.
	   'of your website.');

////
// The filter itself


// Filter posted text, looking for <popim> tags, and turning their
// contents into the appropriate popup. Originally this just worked
// on "<popim ... />" tags, but the TinyMCE editor auto-converts
// those tags to "<popim ...></popim>", so now the filter works on
// both.
function popim_filter($text) {
  $output = preg_replace("|<popim(.*?)/?>(\s*</\s*popim\s*>)?|ie",
  			 "popim_parse_link_from_attribs('\\1')", $text);
  return $output;
}

// Take a bunch of options from a <popim /> tag and turn them into
// the appropriate <a href> and <img> tags. The <popim /> tag must have
// at least the URL to the image, the image title, and the thumbnail URL
function popim_parse_link_from_attribs($str) {
  // Possible attributes, listed as a regexp join
  $attrib_pattern = '(imageurl|title|thumbnailurl|imagewidth|'.
    'imageheight|thumbwidth|thumbheight|ispersistent)';

  // Pull out all attributes and shove them into arrays. Attribute
  // strings are surrounded by quotemarks. We make sure not to
  // match on a quote preceded by a backslash. Since the slash
  // requirements are different for single versus double quotes
  // (i.e. "he's" is allowed w/o a backslash, but not 'he's')
  // we do two matches. Also note that double-quotes are passed
  // from WP/PHP with a backslash in front of them already
  $c1 = preg_match_all('/'.$attrib_pattern.' *= *\\\\"(.*?)(?<!\\\\)\\\\"/i',
		       $str, $attrib_matches1);
  $c2 = preg_match_all('/'.$attrib_pattern." *= *'(.*?)(?<!\\\\)'/i",
		       $str, $attrib_matches2);
  
  if (!$c1 && !$c2) {
    return "[Simple Popup Images unable to add image: no parameters given.]";
  }
  if ($c1) {
    $c1_combine = array_combine($attrib_matches1[1],
				$attrib_matches1[2]);
  }
  else {
    $c1_combine = array();
  }
  if ($c2) {
    $c2_combine = array_combine($attrib_matches2[1],
				$attrib_matches2[2]);
  }
  else {
    $c2_combine = array();
  }
  // Combine all attributes into a single array.
  $attrib_array = array_merge($c1_combine, $c2_combine);
  
  // Make all of the keys lowercase
  $attrib_array = array_change_key_case($attrib_array, CASE_LOWER);
  
  // We must have at least an image URL, a title, and a
  // thumbnail URL
  if (!($attrib_array['imageurl'] && $attrib_array['title'] &&
	$attrib_array['thumbnailurl'])) {
    return "[Simple Popup Images unable to add image: missing parameters.]";
  }
  
  // Strip slashes out of the title
  $attrib_array['title'] = stripslashes($attrib_array['title']);
  
  // Make the link
  return popim_generate_popup_link($attrib_array['imageurl'],
				   $attrib_array['title'],
				   $attrib_array['thumbnailurl'],
				   $attrib_array['imagewidth'],
				   $attrib_array['imageheight'],
				   $attrib_array['thumbwidth'],
				   $attrib_array['thumbheight'],
				   $attrib_array['ispersistent']);
}

// Convert options for a popup image into a fake HTML tag. All options
// are stored as tag attributes.
function popim_generate_str_from_options($url_to_image, $image_title,
					 $url_to_thumbnail, $image_width,
					 $image_height, $thumbnail_width,
					 $thumbnail_height, $is_persistent) {
  // If the image's size wasn't specified, compute it
  if ($image_width <= 0 || $image_height <= 0) {
    if ($image_size =
	@getimagesize(popim_add_server_to_url($url_to_image)) ||
	$image_size = 
	@getimagesize(popim_turn_url_to_dir($url_to_image))) {
      $image_width = $image_size[0];
      $image_height = $image_size[1];
    }
  }
  // If the thumbnail's size wasn't specified, compute it
  if ($thumbnail_width <= 0 || $thumbnail_height <= 0) {
    if ($thumbnail_size =
	@getimagesize(popim_add_server_to_url($url_to_thumbnail)) ||
	$thumbnail_size =
	@getimagesize(popim_turn_url_to_dir($url_to_thumbnail))) {
      $thumbnail_width = $thumbnail_size[0];
      $thumbnail_height = $thumbnail_size[1];
    }
  }
  // Set the persistence text, if needed
  if ($is_persistent) {
    $persistence_str=' isPersistent="1"';
  }
  // Because of how backslashing is done, we need to escape any
  // single quote marks in the title
  return "<popim imageURL='$url_to_image' title='".
    str_replace("'", "\\'", $image_title)."' ".
    "thumbnailURL='$url_to_thumbnail' ".
    "imageWidth='$image_width' imageHeight='$image_height' ".
    "thumbWidth='$thumbnail_width' ".
    "thumbHeight='$thumbnail_height'".$persistence_str.
    " />";
}

// Generate the actual HTML for the popup, given certain information
// about the image, thumbnail, and more.
// If $image_width or $image_height is 0, the function computes the
// image's width and height.
// Similarly, if $thumbnail_width or $thumbnail_height is 0, the
// function computes the thumbnail's width and height.
function popim_generate_popup_link($url_to_image, $image_title,
				   $url_to_thumbnail, $image_width,
				   $image_height, $thumbnail_width,
				   $thumbnail_height, $is_persistent) {
  $image_title_urlencoded = rawurlencode($image_title);
  $image_title_mouseover = addslashes($image_title);
  $image_title_attrib = htmlspecialchars($image_title);
  $template_path = get_option('popim_popup_template');
	
  // Create directory versions of the passed URLs. This assumes
  // that the file exists on the local server. We'll use this
  // as a fallback method for getting the image size
  $dir_to_image = popim_turn_url_to_dir($url_to_image);
  $dir_to_thumbnail = popim_turn_url_to_dir($url_to_thumbnail);
  
  // If there is no server name in the passed URLs, add the
  // server's name.
  $url_to_image = popim_add_server_to_url($url_to_image);
  $url_to_thumbnail = popim_add_server_to_url($url_to_thumbnail);
  
  // If the image's size wasn't specified, compute it
  if ($image_width <= 0 || $image_height <= 0) {
    if (!($image_size = @getimagesize($url_to_image)) &&
	!($image_size = @getimagesize($dir_to_image))) {
      return "[Simple Popup Images unable to find image size for $image_title at either '$url_to_image' or '$dir_to_image' ]";
    }
    $image_width = $image_size[0];
    $image_height = $image_size[1];
  }
  // If the thumbnail's size wasn't specified, compute it
  if ($thumbnail_width <= 0 || $thumbnail_height <= 0) {
    if (!($thumbnail_size = @getimagesize($url_to_thumbnail)) &&
	!($thumbnail_size = @getimagesize($dir_to_thumbnail))) {
      return "[Simple Popup Images unable to find thumbnail image size for $image_title at '$url_to_thumbnail' or '$dir_to_thumbnail' ]";
    }
    $thumbnail_width = $thumbnail_size[0];
    $thumbnail_height = $thumbnail_size[1];
  }
  // If the image is to be persistent, add that option to the link
  if ($is_persistent) {
    $persistence_str='&persistent=1';
  }
  else {
    $persistence_str='';
  }
  
  return '<a title="' . $image_title_attrib .
    '" href="#" onclick="window.open(\''.$template_path.
    '/popup.php?z=' . $url_to_image .
    '&width=' . $image_width . '&height=' . $image_height . 
    '&title=' . $image_title_urlencoded . 
    $persistence_str . '\',\'imagepopup\',\'width=' .
    $image_width . ',height=' . $image_height .
    ',directories=no,location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no,screenx=150,screeny=150\');return false" onmouseover="window.status=\'image popup: ' . $image_title_mouseover . 
    '\';return true" onmouseout="window.status=\'\';return true"><img src="' . $url_to_thumbnail .
    '" width="' . $thumbnail_width . 
    '" height="' . $thumbnail_height .
    '" alt="' . $image_title_attrib .
    '" title="' . $image_title_attrib .
    '" /></a>';
  
}

// Register our filters
add_filter('the_content', 'popim_filter', 0);
add_filter('the_excerpt', 'popim_filter', 0);


////
// Helper Functions


// We can't guarantee that we're running in PHP5 or later, so we can't
// guarantee the existence of array_combine(). So add it in if necessary.
if (!function_exists('array_combine')) {
  function array_combine($a, $b) {
    $c = array();
    if (is_array($a) && is_array($b))
      while (list(, $va) = each($a))
	if (list(, $vb) = each($b))
	  $c[$va] = $vb;
	else
	  break 1;
    return $c;
  }
}

// Convert a path to use Unix '/' directory separators if it doesn't
// already.
function popim_unixify_path($path) {
  return ((DIRECTORY_SEPARATOR != '/') ?
	  str_replace(DIRECTORY_SEPARATOR, '/', $path) : $path);
}

// Sometimes we might get URLs that lack the server. If so, prepend the
// current server URL to the passed URL
function popim_add_server_to_url($url) {
  // If there is no server name in the passed URLs, add the
  // server's name. But don't add a slash between the server
  // name and the URL if the URL already starts with a slash.
  if (substr($url, 0, 7) != 'http://' &&
      substr($url, 0, 8) != 'https://') {
    return 'http://'.$_SERVER['SERVER_NAME'].
      (substr($url, 0, 1) == '/' ? '' :
       '/') . $url;
  }
  return $url;
}

// Get the relative URL to the directory of the current (included) script.
// We can't use $_SERVER['PHP_SELF'] or anything clever like that, because
// that will point to whatever script called popup-images.php.
// Instead, we do some hackery. __FILE__ contains the local path to
// this (included) file. ABSPATH contains the local path to the
// Wordpress install. The WP option "siteurl" has the URL to the WP
// install.
function popim_get_script_dir_url() {
  $popim_script_dir = popim_unixify_path(dirname(__FILE__));
  $popim_abspath = popim_unixify_path(ABSPATH);
  $popim_url_dir_array = parse_url(get_option('siteurl'));
  $popim_url_path = $popim_url_dir_array['path'];
  // In theory ABSPATH - siteurl path gives the directory to the site 
  if (empty($popim_url_path)) {
    $dir_to_site = $popim_abspath;
  }
  else if (($i = strpos($popim_abspath, $popim_url_path)) === FALSE) {
    return '';
  }
  else {
    $dir_to_site = substr($popim_abspath, 0, $i);
  }
  if (strpos($popim_script_dir, $dir_to_site) === FALSE) {
    return '';
  }
  return str_replace($dir_to_site, '', $popim_script_dir);
}

// Turn a URL into a directory.
function popim_turn_url_to_dir($url) {
  // If there is a server name at the start of the URL, get rid
  // of it.
  if (substr($url, 0, 7) == 'http://') {
    $url = substr($url, 7);
  }
  else if (substr($url, 0, 8) == 'https://') {
    $url = substr($url, 8);
  }
  
  // If there's a leading slash in the URL, remove it
  $url = ltrim($url, "/");
  
  // Return the URL with the directory given in the
  // popim_home_directory option prepended
  return get_option('popim_home_directory').$url;
}


////
// UI Elements

// Make sure the TinyMCE editor is okay with our new-fangled "popim"
// tag

add_filter('mce_valid_elements', 'popim_mce_valid_elements', 0);

function popim_mce_valid_elements($valid_elements) {
  $valid_elements .= '+popim[imageurl|title|thumbnailurl|imagewidth|'.
    'imageheight|thumbwidth|thumbheight|ispersistent]';
  return $valid_elements;
}

// Add javascript to generate a Simple Popup Images quicktag button. If
// the quicktag bar isn't available, instead put a link below the posting
// field entry.
function popim_quicktag_like_button() {
  // Only add the javascript to post.php, post-new.php, page-new.php, or
  // bookmarklet.php pages
  if (strpos($_SERVER['REQUEST_URI'], 'post.php') ||
      strpos($_SERVER['REQUEST_URI'], 'post-new.php') || 
      strpos($_SERVER['REQUEST_URI'], 'page-new.php') ||
      strpos($_SERVER['REQUEST_URI'], 'bookmarklet.php')) {
    $popim_wp_url = get_option('siteurl');
    $popim_UI_script_filename = 'popup-images-ui.php';
    // Get the directory to the popup images script, but
    // trim off the ABSPATH part of the string
    $popim_script_dir = popim_unixify_path(dirname(__FILE__));
    $popim_abspath = popim_unixify_path(ABSPATH);
    if (($end_of_abspath = strpos($popim_script_dir, $popim_abspath)) === FALSE) {
      // If something goes wrong with our directory, use
      // a default value
      $popim_full_script_url = $popim_wp_url.
	'/wp-content/plugins/popup-images/'.
	$popim_UI_script_filename;
    }
    else {
      $end_of_abspath += strlen($popim_abspath);
      $popim_full_script_url = $popim_wp_url.'/'.
	substr($popim_script_dir, $end_of_abspath).
	'/'.$popim_UI_script_filename;
    }
?>
																			  
<div id="popim_link" style="margin-bottom:10px; display:none;">
<a href="<?php echo $popim_full_script_url; ?>" onclick="return popim_open('tinymce=true')">Add a popup image</a>
</div>

<script type="text/javascript">
//<![CDATA[
var popim_toolbar = document.getElementById("ed_toolbar");

if (popim_toolbar) {
  var theButton = document.createElement('input');
  theButton.type = 'button';
  theButton.value = 'Popup';
  theButton.onclick = popim_open;
  theButton.className = 'ed_button';
  theButton.title = 'Add a Popup Image';
  theButton.id = 'ed_Popup';
  popim_toolbar.appendChild(theButton);
}
else {
  var popimLink = document.getElementById("popim_link");
  var pingBack = document.getElementById("pingback");
  if (pingBack == null)
    var pingBack = document.getElementById("post_pingback");
  if (pingBack == null) {
    var pingBack = document.getElementById("savepage");
    pingBack = pingBack.parentNode;
  }
  pingBack.parentNode.insertBefore(popimLink, pingBack);
  popimLink.style.display = 'block';
} 

function popim_open(querystr) {
  var form = 'post';
  var field = 'content';
  var url = '<?php echo $popim_full_script_url; ?>';
  if (querystr) {
    url = url+'?'+querystr;
  }
  var name = 'popim';
  var w = 600;
  var h = 600;
  var valLeft = (screen.width) ? (screen.width-w)/2 : 0;
  var valTop = (screen.height) ? (screen.height-h)/2 : 0;
  var features = 'width='+w+',height='+h+',left='+valLeft+',top='+valTop+',resizable=1,scrollbars=1';
  var popimImageWindow = window.open(url, name, features);
  
  popimImageWindow.focus();
  return false;
}

//]]>
</script>
<?php
    }
}

// Add this footer to all admin pages
add_filter('admin_footer', 'popim_quicktag_like_button');


////
// Options


// Hook our submenu up with the admin menu manager
add_action('admin_menu', 'popim_submenu');

// The options submenu, which appears under the Options admin menu
function popim_submenu() {
  if (function_exists('add_options_page'))
    add_options_page('Simple Popup Images', 'Simple Popup Images', 6, __FILE__, 'popim_options_subpanel');
}

function popim_options_subpanel() {
  if (isset($_POST['update_popim'])) {
    // Fiddle the slashes
    // Get rid of whitespace
    $template_path = trim($_POST['template_loc']);
    $home_path = trim($_POST['website_dir']);
    // Kill off double slashes
    $template_path = str_replace('\\\\', '\\', $template_path);
    $home_path = str_replace('\\\\', '\\', $home_path);
    // Make any back slashes be forward ones for the URL
    $template_path = str_replace('\\', '/', $template_path);
    // Get rid of any trailing slashes in the URL, but make
    // sure there's a trailing slash for the home directory
    $template_path = rtrim($template_path, "/");
    if (substr($home_path, -1, 1) != "/") {
      $home_path .= "/";
    }
    update_option('popim_popup_template', $template_path);
    update_option('popim_home_directory', $home_path);
      ?><div class="updated"><p><strong><?php
	   _e('Simple Popup Images options updated.');
	?></strong></p></div><?php
	      }
		
?><div class="wrap">
    <h2>Simple Popup Images Options</h2>
    <form method="post" id="popim_options">
    <fieldset class="options">
	<legend>Popup Directories</legend>
				<table class="editform" cellspacing="2" cellpadding="5" width="100%">
					<tr>
						<th valign="top" style="padding-top: 10px;">
							<label>Path to popup.php:</label>
						</th>
						<td>
							<?php
							echo "<input type='text' size='50' ";
							echo "name='template_loc' ";
							echo "id='template_loc' ";
							echo "value='".get_option(popim_popup_template)."' />\n";
							?>
							<p style="margin: 5px 10px;">For example, if your site is installed at <strong>http://www.example.com</strong>
							and you placed popup.php so that you can get to it via <strong>http://www.example.com/popup/popup.php</strong>,
							then your path would be &ldquo;<strong>/popup</strong>&rdquo;. If popup.php is in your website's root
                            directory, then leave blank.</p>
						</td>
					</tr>
					<tr>
						<th valign="top" style="padding-top: 10px;">
							<label>Local directory of your website:</label>
						</th>
						<td>
							<?php
							echo "<input type='text' size='50' ";
							echo "name='website_dir' ";
							echo "id='website_dir' ";
							echo "value='".get_option(popim_home_directory)."' />\n";
							?>
							<p style="margin: 5px 10px;">What local directory on your server corresponds to your
							website. For example, if your site is installed at <strong>http://www.example.com</strong>
							and this matches a server directory of <strong>/home/users/example/public_html/</strong>,
							then your local directory would be &ldquo;<strong>/home/users/example/public_html/</strong>&rdquo;.</p>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="submit">
							<input type='submit' name='update_popim' value='Update Options' />
						</td>
					</tr>
				</table>
			</fieldset>
    </form>
</div>
<?php
}

?>