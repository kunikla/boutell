<?php
/**
 * Created by PhpStorm.
 * User: chamilton
 * Date: 5/28/18
 * Time: 8:18 PM
 */
  # ABSOLUTELY NO WHITESPACE BEFORE THE ABOVE LINE!

  #CHANGE THIS to suit YOUR site! Do NOT put your mp3s folder
  #inside your website folder. This must be a FILE PATH ON YOUR
  #SERVER, where you have placed your actual MP3s. NOT a URL.
  #The / at the end is REQUIRED.
  $mp3Dir = "/home/chamilton/sofiles/";  // Get option from database
  $tmpDir = "/home/chamilton/sotmp/";   // Not sure this needs to be outside of webroot...

  #CHANGE THIS to suit where YOU installed playonce.php!
  $playOnceUrl = "http://localhost/code4btv/boutell/playonce.php";

  #300 seconds (5 minutes) is a long life for one of our
  #temporary files, because they are only needed long enough
  #for the browser to fetch the player page, the playlist, and
  #the very start of the actual MP3. They don't have to be around
  # for the entire download.
  $maximumTmpAge = 300;

  if ($_POST['play']) {
    play();
  }
  # Otherwise output a song selection page
?>
<html>
<head>
<title>Select A Song</title>
</head>
<body>
<h1>Select A Song</h1>
<p><b>WARNING:</b> downloads are for your personal use only. Your IP address and
the time of your download will be recorded in the song file. We can and
will identify and pursue legal remedies against those who distribute our
files through file sharing services.</p>
<div align="center">
<form method="POST" action="playmenu.php">
<select name="mp3" size="10">
<?php

if (!is_dir($mp3Dir)) {
  die("mp3Dir is not set correctly.");
}

$dir = opendir($mp3Dir);
if ($dir) {
  while (1) {

    $file = readdir($dir);

    if ($file == false) {
      break;
    }

    if (!preg_match("/.mp3$/i", $file)) {
      continue;
    }

    # We found an MP3 file. Yank out the ID3v1.x tag
    # and find the title, artist and album to make
    # things pretty.
    $info = getInfo($file);

    # We show the title, artist and album here,
    # you can change this if you wish.
    $label = $info['artist'] . " " . $info['title'] .
      " ( " . $info['album'] . " )";

    # Don't allow funny business in ID3 tags to creep in and mess
    # up our layout. Replace anything that might alter the HTML.
    $label = preg_replace("/[\<\>\&\"]/", " ", $label);
    echo ("<option value=\"$file\">$label</option>\n");
  }
}

function getInfo($file)
{
  global $mp3Dir;
  $handle = fopen($mp3Dir . $file, "rb");
  if (!$handle) {
    die("Can't open $file");
  }
  fseek($handle, -128, SEEK_END);
  $id = fread($handle, 128);
  $tag = substr($id, 0, 3);
  if ($tag != "TAG") {
    $title = $file;
  } else {
    $title = substr($id, 3, 30);
    $artist = substr($id, 33, 30);
    $album = substr($id, 63, 30);
    $title = trim($title);
    $artist = trim($artist);
    $album = trim($album);
  }
  $info['artist'] = $artist;
  $info['title'] = $title;
  $info['album'] = $album;
  return $info;
}

?>
</select>
<p>
<input type="submit" name="play" value="Play Now">
</form>
</div>
</body>
</html>

<?php
function play()
{
  global $playOnceUrl;
  global $tmpDir;
  $mp3 = $_POST['mp3'];
  if (!preg_match("/^[\w\-\+\.\@\ ]+$/", $mp3)) {
    # Dangerous characters in filename, reject it
    die("Bad filename $mp3");
  }
  $info = getInfo($mp3);
  if (!$info) {
    die("Bad filename, no info: $mp3");
  }
  # Needed for PHP versions OLDER than 4.2.0 only.
  # If your host still has PHP older than 4.2.0, shame on them.
  # Find a better web host.
  srand(makeSeed());

  # Clean up leftover temporary files from users who did not
  # have correctly configured audio players. If we don't do this,
  # the tmp directory will become cluttered with garbage.
  cleanupTemporaryFiles();

  # Generate a unique ID for this song download.
  $tmp = rand(0, 1 << 30);
  $handle = fopen("$tmpDir$tmp", "w");
  fwrite($handle, "$mp3\n");
  fwrite($handle, $info['artist'] . "\n");
  fwrite($handle, $info['title'] . "\n");
  fwrite($handle, $info['album'] . "\n");
  fclose($handle);
  # Now redirect to the playonce.php script which will
  # take advantage of this file (once to display a player,
  # a second time to generate a playlist file, and a final
  # time to deliver the actual IP-and-date-stamped MP3).
  header("Location: " . $playOnceUrl . "/" . $tmp . ".html");
}

function makeSeed()
{
  list($usec, $sec) = explode(' ', microtime());
  return (float) $sec + ((float) $usec * 100000);
}

function cleanupTemporaryFiles()
{
  global $maximumTmpAge;
  global $tmpDir;
  # Scan the temporary directory for old files and
  # clean them up.
  $dir = opendir($tmpDir);
  if (!$dir) {
    return;
  }
  while (1) {
    $file = readdir($dir);
    if ($file == false) {
      break;
    }
    if (!preg_match("/^\d+$/", $file)) {
      continue;
    }
    $fileinfo = stat("$tmpDir$file");
    $now = time();
    $when = $fileinfo['mtime'];
    if ($now - $when > $maximumTmpAge) {
      unlink("$tmpDir$file");
    }
  }
}
?>
