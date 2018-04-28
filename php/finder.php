<?php
require __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Finder\Finder;

$rootdir = "E:\\cctv";
$rootRelative = "/cctv";
$playlistdir = "";
$search = "/\d*?_\d*?_\d*?_\d*?\.mp4$/";
$dirlist = [];
$filesfound = [];
$filestree = [];

$dirfinder = new Finder();
$dirfinder->directories()->depth('== 3')->in($rootdir);

// find directories inside root cctv path (the one set in unifi video)
foreach ($dirfinder as $dirs) {
    array_push($dirlist, $dirs->getRealPath());
    logger("++ Found directory: ${dirs}");
}

// iterator to look for files based on regex pattern in dirs found
foreach ($dirlist as $path) {
    findFiles($path, $search, $rootRelative);
}
// debug printing block
logger("++ Available Cameras and Dates: ");
logger($index);

playlistBuilder($output);

function findFiles($path, $search, $rootRelative)
{
    global $output;
    global $index;

    $doOnce = false;
    $files = array_diff(scandir($path), array('..', '.'));
    foreach ($files as $file) {
        $fullPath = $path . DIRECTORY_SEPARATOR . $file;
        $pathParts = explode(DIRECTORY_SEPARATOR, $path);
        $pathParts = invertArrayKeyOrder($pathParts);
        if (preg_match($search, $file)) {
            if (!$doOnce) {
                logger("++ Found matching files in: ${path}");
                $doOnce = true;
                $index[$pathParts[3]][] = $pathParts[0] . "/" . $pathParts[1] . "/" . $pathParts[2];
            }
            $testpath = $rootRelative . "/" . $pathParts[3] . "/" . $pathParts[2] . "/" . $pathParts[1] . "/" . $pathParts[0] . "/" . $file;

            $output[$path][] = $testpath;
        }
    }
}

function playlistBuilder($array)
{
    logger("++ Building playlists from index array");
    foreach ($array as $key => $dirs) {
        $doOnce = false;
        logger("++ Building playlist in: " . $key . ":");
        foreach ($array[$key] as $key => $files) {
            if (!$doOnce) {
                $playlist = playlistHeader($files);
                $doOnce = true;
            }
            $duration = 2;
            $playlist = playlistIterator($files, $duration, $playlist);
        }
        $playlist .= '#EXT-X-ENDLIST' . PHP_EOL;
        print_r("<pre>$playlist</pre>");
        file_put_contents("../playlists/1.m3u8", $playlist);
        logger("++ Videos found - " . ((substr_count($playlist, ".mp4")) - 1));
        logger("++ Created playlist - " . (substr_count($playlist, PHP_EOL)) . " lines written to file");
    }
}

function playlistHeader($file)
{
    $playlist = <<<EOF
#EXTM3U
#EXT-X-VERSION:3
#EXT-X-TARGETDURATION:8
#EXT-X-MEDIA-SEQUENCE:0
#EXT-X-PLAYLIST-TYPE:VOD
#EXT-X-INDEPENDENT-SEGMENTS
#EXT-X-MAP:URI="$file"

EOF;
    // $playlist .= PHP_EOL . '#EXT-X-MAP:URI="' . $file . '"' . PHP_EOL;
    return $playlist;
}

function playlistIterator($file, $duration, $playlist)
{
    $playlist .= '#EXTINF:2.000000,' . PHP_EOL;
    $playlist .= $file . PHP_EOL;
    return $playlist;
}

function makeUnique($array)
{
    // make unique then reorder the keys after
    $array = array_unique($array);
    $array = array_values($array);
    return $array;
}

function invertArrayKeyOrder($array)
{
    $array = array_reverse($array);
    $array = array_values($array);
    return $array;
}

function logger($logText)
{
    if (is_string($logText)) {
        $stamp = "<pre>";
        $stamp .= date('e H:i:s ');
        $stamp .= $logText;
        $stamp .= PHP_EOL;
        $stamp .= "</pre>";
        echo $stamp;
    }
    if (is_array($logText)) {
        echo "<pre>";
        print_r($logText);
        echo "</pre>";
    }
}
