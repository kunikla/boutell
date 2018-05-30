<?php
/**
 * Created by PhpStorm.
 * User: chamilton
 * Date: 5/28/18
 * Time: 8:30 PM
 */
#There can be ABSOLUTELY NOTHING before the <?php at the top of this file!
#Otherwise we can't choose to output an MP3 or playlist instead of HTML
#where appropriate.

$mp3Dir = "/home/chamilton/sofiles/";
$tmpDir = "/home/chamilton/sotmp/";

#Change this to suit where YOU installed this page!
$playOnceUrl = "http://localhost/code4btv/boutell/playonce.php";

if (preg_match("/(\d+)\.m3u$/", $_SERVER['PATH_INFO'], $matches)) {
  $tmp = $matches[1];
  playlist($tmp);
} elseif (preg_match("/(\d+)\.mp3$/", $_SERVER['PATH_INFO'], $matches)) {
  $tmp = $matches[1];
  mp3($tmp);
} elseif (preg_match("/(\d+)\.html$/", $_SERVER['PATH_INFO'], $matches)) {
  $tmp = $matches[1];
}

if (!$tmp) {
  die("Bad temporary ID #1");
}

$name = file($tmpDir . $tmp);
if (!$name[0]) {
  die("Bad temporary ID (no name)");
}
$artist = $name[1];
$title = $name[2];
$album = $name[3];

$script = $_SERVER['SCRIPT_NAME'] . "/" . $tmp . ".m3u";
#Now output the player HTML
?>

<html>
<head>
<title><?php echo "$artist"?>: <? echo "$title"?> (<? echo "$album"?>)</title>
</head>
<body>
<div align="center">
<b><?php echo "$artist"?></b><br>
<b><?php echo "$title"?></b><br>
<b><i><?php echo "$album"?></i></b><br>
<!--<embed src="--><?php //echo "$script"?><!--"-->
<!--  autostart="true"-->
<!--  kioskmode="true"-->
<!--  type="audio/mpeg"-->
<!--  width="320"-->
<!--  height="240"-->
<!--  loop="true"/>-->
<audio src="<?php echo ($script);?>" controls autoplay></audio>
</div>
</body>
</html>

<?php
function mp3($tmp)
{
  global $mp3Dir;
  global $tmpDir;
  $name = file($tmpDir . $tmp);
  if (!$name[0]) {
    die("Bad temporary ID (no name in mp3)");
  }
  # Remove the temporary ID files so that they
  # can't be used repeatedly to easily mirror the site
  unlink($tmpDir . $tmp);
  header("Content-type: audio/mpeg");
// TODO add headers that prevent caching???
//  header("Cache-Control: no-cache, no-store, must-revalidate");
//  header("Pragma: no-cache")
//  header("Expires: Thu, 01 Dec 1994 16:00:00 GMT");
  $mp3 = $name[0];
  $mp3 = trim($mp3);
  #Validate this for safety! Check for any
  #characters which are not A-Z, a-z, 0-9, . or _
  if (!preg_match("/^[\w\-\+\.\@\ ]+$/", $mp3)) {
    die("Unsafe mp3 filename parameter.");
  }
  $filename = $mp3Dir . $mp3;
  $handle = fopen($filename, "rb");
  if (!$handle) {
    die("No such mp3 file.");
  }

  // First read everything EXCEPT the id3v1.x tag
  //  at the end.

  $size = filesize($filename);
  $id3TagSize = 128;
  $limit = $size - $id3TagSize;
  $pos = 0;
  while ($pos < $limit) {
   $chunk = 8192;
   if ($pos + $chunk > $limit) {
   $chunk = $limit - $pos;
   }
   $data = fread($handle, $chunk);
   print $data;
   $pos += $chunk;
  }

  // Now grab the last 128 bytes, which should be the ID3 tag,
  // and rewrite the comment field before sending it to the
  // browser. If we don't see an ID3 tag signature, output
  // what we did grab, which will be the tail end of the audio,
  // and then invent our own ID3 tag.

  $id = fread($handle, $id3TagSize);

  if (substr($id, 0, 3) != "TAG") {
    # Not really an ID3 tag, so write
    # out the last of the audio data and
    # invent our own ID3 tag
    print $id;
    # Now make an empty ID3 tag to append
    $id = pack("a128", "TAG");
  }

  // Record the IP address and time of download in the actual MP3 file,
  // as a comment. When you find your files on someone else's site you
  // can then determine when they were stolen and from what IP address.
  // That information can be used to pursue legal remedies, beginning by
  // obtaining the identity of the original downloader from their ISP
  // using this information. Note that the time logged in the file is
  // always GMT.
  $comment = $_SERVER['REMOTE_ADDR'] .
    " " . gmdate("Y-m-d h:i:sa", time());

  $newid = substr($id, 0, 97) . pack("a29", $comment) .
   substr($id, 126, 2);
  print $newid;
  fclose($handle);

  // We exit now to avoid writing newlines and other junk spaces
  // after the end of the PHP code as part of the MP3, which would
  // ruin our ID3 tag.
  exit(0);
}

function playlist($tmp)
{
  global $playOnceUrl;
  header("Content-type: audio/mpeg");
  header("Location: " . $playOnceUrl . "/" . $tmp . ".mp3");

  // Don't let other junk appear at the end.
  exit(0);
}

?>
