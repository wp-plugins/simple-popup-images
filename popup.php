<html>
<head>
<title><?php print htmlspecialchars(stripslashes($_GET['title'])) ?></title>
<style>
BODY	{margin:0px;}
</style>
</head>
<body <?php if (!$_GET['persistent']) { print "onBlur=\"window.close()\""; } ?> ><img src="<?= $_GET['z'] ?>" width="<?= $_GET['width'] ?>" height="<?= $_GET['height'] ?>" border="0" alt="<?= $_GET['title'] ?>" /></body></html>