<?php
/*
Display the UI for Simple Popup Images, to make it easy to insert
new images.
*/

// Load the wp files and the plugin files so that we have access to the
// popup-generating code. We need the wp files for access to the
// get_option() function.
require_once('../../../wp-config.php');
include_once(ABSPATH . '/wp-includes/version.php');
if (((float) $wp_version) < 2.1) {
  require_once (ABSPATH . '/wp-includes/wp-l10n.php');
}
require_once (ABSPATH . '/wp-admin/admin-functions.php');
require_once('popup-images.php');

// Strip slashes and HTMLify all POST variables and store them in
// a new array. Strip slashes from all $_POST variables.
foreach ($_POST as $k => $v) {
		$_POST[$k] = stripslashes($v);
		$_HTML[$k] = htmlspecialchars(stripslashes($v), ENT_QUOTES);
}

// Insertion handling
if (isset($_POST['insert'])) {
  // Validation
  if (empty($_POST['image_title']))
    $popimui_errors[] = 'You need to add an image title';
  if (empty($_POST['image_url']))
    $popimui_errors[] = 'You need to add a URL to the image';
  else if (!@getimagesize(popim_add_server_to_url($_POST['image_url'])) &&
	   !@getimagesize(popim_turn_url_to_dir($_POST['image_url'])))
    $popimui_errors[] = "The image doesn't exist at ".
      popim_add_server_to_url($_POST['image_url']);
  if (empty($_POST['thumbnail_url']))
    $popimui_errors[] = 'You need to add a URL to the thumbnail';
  else if (!@getimagesize(popim_add_server_to_url($_POST['thumbnail_url'])) &&
	   !@getimagesize(popim_turn_url_to_dir($_POST['image_url'])))
    $popimui_errors[] = "The thumbnail doesn't exist at ".
      popim_add_server_to_url($_POST['thumbnail_url']);
  if (!isset($popimui_errors)) {
    $popimui_insert_str =
      popim_generate_str_from_options($_POST['image_url'],
				      $_POST['image_title'],
				      $_POST['thumbnail_url'],
				      0,0,0,0,
				      $_POST['persistent'] == 'yes');
  }
}

// Thumbnail creation handling
if (isset($_POST['make_thumbnail'])) {
  $popimui_errors = popimui_generate_thumbnail($_POST['image_url'],
					       $_POST['thumbnail_width'],
					       $_POST['thumbnail_height']);
  if (empty($popimui_errors))
    unset($popimui_errors);
}

// If we have a string to insert in the post, do so
if (isset($popimui_insert_str)) {
  print popimui_make_html_header();
  print popimui_insert_popim_link_js($popimui_insert_str);
}
else {
  print popimui_make_html_header();
}

// Print the options form
print popimui_make_options_form();

// If the user wants a preview of the popup link, give them one
if (isset($_POST['preview'])) {
  print popimui_make_preview();
}

// Similarly, if they made a thumbnail, let them see it
if (isset($_POST['thumbnail_preview'])) {
  print popimui_show_thumbnail();
}

if (isset($popimui_errors)) {
  print popimui_show_errors($popimui_errors);
}

print "</body>\n</html>\n";


////
// Functions


// Create the HTML header
function popimui_make_html_header($popim_link = FALSE) {
  $html = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n" .
    "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n" .
    "<head>\n" .
    "<title>Simple Popup Image Chooser</title>\n" .
    "<link rel='stylesheet' href='popup-images.css' type='text/css' />\n".
    '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'."\n".
    "<script language='javascript' type='text/javascript' src='popim-functions.js'></script>\n";
  if ($popim_link) {
    $html .= popimui_insert_popim_link_js($popim_link);
  }
  $html .= "</head>\n\n".
    "<body>\n\n";
  
  return $html;
}

// Create the options form where the user can define the images etc.
function popimui_make_options_form() {
  global $_HTML;

  $html = "<form action='{$_SERVER['PHP_SELF']}' id='popupform' method='post'>\n".
    "<div>\n".
    "    <fieldset>\n".
    "        <legend>Image title</legend>\n".
    "        <p>Image title: <input type='text' name='image_title' id='image_title' size='50' value='{$_HTML['image_title']}'/></p>\n".
    "    </fieldset>\n".
    "</div>\n\n".
    "<div>\n".
    "    <fieldset>\n".
    "        <legend>Images</legend>\n".
    "        <p>URL to image: <input type='text' name='image_url' id='image_url' size='50' value='{$_HTML['image_url']}'/></p>\n".
    "        <p>URL to thumbnail: <input type='text' name='thumbnail_url' id='thumbnail_url' size='50' value='{$_HTML['thumbnail_url']}'/></p>\n".
    "    </fieldset>\n".
    "</div>\n\n";
  // If the gd extension is available, we can generate a thumbnail
  // for the user
  if (extension_loaded('gd')) {
    $html .=
      "<div>\n".
      "    <fieldset>\n".
      "        <legend>Make a Thumbnail</legend>\n".
      "        <p>Take the image from the URL above and create a thumbnail of it.\n".
      "        The image must live on this server, and the thumbnail will be\n".
      "        saved in the same directory as the image. Enter the thumbnail's\n".
      "        width and/or height in pixels. The image's width-to-height ratio\n".
      "        will be kept, and if the height and width don't match, the\n".
      "        smallest possible one will be used.</p>\n".
      "        <p>Thumbnail width: <input type='text' name='thumbnail_width' size='4' value='{$_HTML['thumbnail_width']}' />  height: <input type='text' name='thumbnail_height' size='4' value='{$_HTML['thumbnail_height']}' /></p>\n".
      "        <p><input type='submit' name='make_thumbnail' value='Generate Thumbnail' /></p>\n".
      "    </fieldset>\n".
      "</div>\n\n";
  }
  $html .=
    "<div>\n".
    "    <fieldset>\n".
    "        <legend>What should happen when the user clicks away from the image?</legend>\n".
    "        <input type='radio' name='persistent' value='yes'".
    (($_HTML['persistent'] == 'no') ? '' : " checked='checked'").
    ">The window stays open</input>\n".
    "        <input type='radio' name='persistent' value='no'".
    (($_HTML['persistent'] == 'yes') ? '' : " checked='checked'").
    ">The window goes away</input>\n".
    "    </fieldset>\n".
    "</div>\n\n".
    "<div>\n".
    "<input type='submit' name='preview' value='Preview' /> <input type='submit' name='insert' value='Insert' />\n".
    "</div>\n\n".
    "</form>\n";

  return $html;				
}

// Show a preview of the popup image
function popimui_make_preview() {
  $html = "<div>\n".
    "    <fieldset>\n".
    "    <legend>Image Preview</legend>\n".
    popim_generate_popup_link($_POST['image_url'],
			      $_POST['image_title'],
			      $_POST['thumbnail_url'],
			      0, 0, 0, 0,
			      $_POST['persistent'] == 'yes').
    "    </fieldset>\n".
    "</div>\n\n".
    "<div>\n".
    "    <fieldset>\n".
    "    <legend>Popup Image Code</legend>\n".
    "    <textarea name=\"popim-code\" id=\"htmlcode\">".
    popim_generate_str_from_options($_POST['image_url'],
				    $_POST['image_title'],
				    $_POST['thumbnail_url'],
				    0, 0, 0, 0,
				    $_POST['persistent'] == 'yes').
    "</textarea>\n".
    "    </fieldset>\n".
    "</div>\n\n";
  
  return $html;
}

// Show a generated thumbnail
function popimui_show_thumbnail() {
  $html = "<div>\n".
    "    <fieldset>\n".
    "    <legend>Generated Thumbnail</legend>\n".
    "    <img src='{$_POST['thumbnail_preview']}' />\n".
    "    </fieldset>\n".
    "</div>\n\n";

  return $html;
}

// Display any errors that might have arisen. Error strings are passed in an
// array, one error per element.
function popimui_show_errors($err) {
  $html = "<div class='errors'>\n".
    "    <fieldset>\n".
    "    <legend>Errors</legend>\n".
    "<ul><li>".implode('</li><li>',$err)."</li></ul>\n".
    "    </fieldset>\n".
    "</div>\n\n";

  return $html;
}

// Print the javascript call that will insert the given popim link
function popimui_insert_popim_link_js($str) {
  $html = "<script type='text/javascript'>\n".
    "//<![CDATA[\n".
    "insertHtml('".addslashes($str)."');\n".
    "//]]>\n".
    "</script>\n";

  return $html;
}

// Generate a thumbnail from the existing file
function popimui_generate_thumbnail($image_url, $thumb_width, $thumb_height) {
  global $_HTML;
  $errors = array();

  // Make sure at least one of height and width was defined
  if (!$thumb_width && !$thumb_height) {
    $errors[] = "You must enter a width or height for the ".
      "thumbnail";
    return $errors;
  }

  // Figure out where the image lives on the server. We'll figure
  // out the path on the server by comparing the WP url with the
  // WP absolute path. Clever, or extremely hacky? You decide.
  $popim_wp_url = get_option('siteurl');
  $popim_wp_path = popim_unixify_path(ABSPATH);
  // If we're unlucky enough to have a WP location that's not
  // at the root of the server, we have to do a little bit of
  // fiddling
  $wp_url_parts = parse_url($popim_wp_url);
  if (isset($wp_url_parts['path'])) {
    $number_of_dirs = count(preg_split('|//?|', $wp_url_parts['path'],
				       -1, PREG_SPLIT_NO_EMPTY));
    $path_dirs = preg_split('|//?|', $popim_wp_path, -1,
			    PREG_SPLIT_NO_EMPTY);
    $basedir = implode('/',
					   array_slice($path_dirs, 0,
								   count($path_dirs)-$number_of_dirs)).'/';
	// If ABSDIR is on a Windows machine and thus has a "C:"
	// drive-letter-and-colon at the start, don't prepend a "/"
	// to $basedir
	if (!preg_match("|^[a-z]:/|i", $basedir)) {
			$basedir = '/'.$basedir;
	}
  }
  else $basedir = $popim_wp_path;
  
  // Look for the image. Strip off any server portion of the URL
  // if necessary. We can't use parse_url() since the passed URL
  // may be relative in this case.
  if (preg_match('|^http://.*?/(.*)$|', $image_url, $matches)) {
    $image_path = $basedir.$matches[1];
  }
  else {
    $image_path = $basedir.$image_url;
  }
  if (!file_exists($image_path)) {
    $errors[] = "I couldn't find the image file at $image_path ".
      "to make a thumbnail from it";
    return $errors;
  }
  
  // Figure the thumbnail's size, using the smallest scaling possible
  if (!(list($image_width, $image_height) = @getimagesize($image_path))) {
    $errors[] = "The file at $image_path doesn't appear to be ".
      "an image, since I couldn't get its width and ".
      "height";
    return $errors;
  }
  $width_percent = $thumb_width / $image_width;
  $height_percent = $thumb_height / $image_height;
  
  // We're guaranteed that one of these percentages is non-zero
  // thanks to the check at the top of this function
  if (!$width_percent) {
    $thumb_width = round($image_width * $height_percent);
  }
  else if (!$height_percent) {
    $thumb_height = round($image_height * $width_percent);
  }
  else if ($width_percent < $height_percent) {
    $thumb_height = round($image_height * $width_percent);
  }
  else {
    $thumb_width = round($image_width * $height_percent);
  }
  
  // Figure out what kind of image we are by extension (bleah) and
  // make the new filename
  $path_bits = pathinfo($image_path);
  switch ($path_bits['extension']) {
  case 'jpg':
  case 'jpeg':
    $image_load_fn = 'imagecreatefromjpeg';
    $image_save_fn = 'imagejpeg';
    break;
  case 'gif':
    $image_load_fn = 'imagecreatefromgif';
    $image_save_fn = 'imagegif';
    break;
  case 'png':
    $image_load_fn = 'imagecreatefrompng';
    $image_save_fn = 'imagepng';
    break;
  default:
    $errors[] = "The image file at $image_path isn't one of the supported formats (jpeg, gif, png)";
    return $errors;
  }
  // The basename() fiddling is necessary because the 'filename'
  // portion of pathinfo() wasn't added until 5.2.0. Even using
  // basename() to strip off the extension is fiddly, because
  // that didn't show up until 4.1.0
  $thumb_path = $path_bits['dirname'].'/'.
    basename($path_bits['basename'], '.'.$path_bits['extension']).
    '-thumbnail.'.$path_bits['extension'];
  
  // Load the image, resize it, and save it
  $original_image = @$image_load_fn($image_path);
  if (empty($original_image)) {
    $errors[] = "Unable to load the image at $image_path";
    return $errors;
  }
  
  $new_thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);
  if (!imagecopyresampled($new_thumbnail, $original_image, 0, 0,
			  0, 0, $thumb_width, $thumb_height,
			  $image_width, $image_height)) {
    $errors[] = "I couldn't resize the image at $image_path ".
      "for reasons I don't understand -- sorry";
    return $errors;
  }
  
  if (!(@$image_save_fn($new_thumbnail, $thumb_path))) {
    $errors[] = "I couldn't save the resized image at ".
      "$thumb_path for some reason. The most likely ".
      "cause is that you don't have write permission ".
      "set on the directory";
    return $errors;
  }
  
  // As a final step, chmod the thumbnail to be readable and
  // writable by anyone
  chmod($thumb_path, 0666);
  
  // Now that we're successful, show the image by setting
  // $_POST['thumbnail_preview'] to be the URL to the new
  // thumbnail (yeah, yeah, hacky). Also shove the link to the
  // thumbnail into $_HTML['thumbnail_url']
  if (($end_of_basedir = strpos($thumb_path, $basedir)) !== FALSE) {
    $end_of_basedir += strlen($basedir);
    $_POST['thumbnail_preview'] = '/'.
      substr($thumb_path, $end_of_basedir);
    $_HTML['thumbnail_url'] = $_POST['thumbnail_preview'];
  }
  else {
    $errors[] = "Something went wrong internally. You should ".
      "probably contact the author of this plugin";
  }
  
  return $errors;
}

?>
