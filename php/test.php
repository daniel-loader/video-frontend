<pre>
<?php
include_once __DIR__ . '/../config.php';
require __DIR__ . '/logger.php';

playlistBuilder(
    "E:\\cctv\\91614fae-dfcb-3be6-9958-5fa07b248114\\2018\\05\\01",
    "91614fae-dfcb-3be6-9958-5fa07b248114_2018_05_01"
);

function playlistBuilder($path, $output)
{
    logger("++ Building playlist in: " . $path . ":");
    $fileArray = fileFinder($path);
    array_walk_recursive($fileArray, 'prependPath', $path);
    foreach ($fileArray as $key => $chunks) {
        playlistWriter($chunks, $key, $output);
    }
}

function playlistWriter($files, $key, $output)
{
    $duration = ffprobeDuration($files[0]);

    foreach ($files as $file) {
        $playlistFiles[] = absoluteToRelativePath($file);
    }
    $playlist = <<<EOF
#EXTM3U
#EXT-X-VERSION:3
#EXT-X-TARGETDURATION:8
#EXT-X-MEDIA-SEQUENCE:0
#EXT-X-PLAYLIST-TYPE:VOD
#EXT-X-INDEPENDENT-SEGMENTS
#EXT-X-MAP:URI="$playlistFiles[0]"

EOF;
    foreach ($playlistFiles as $filepaths) {
        $playlist .= "#EXTINF:$duration," . PHP_EOL;
        $playlist .= $filepaths . PHP_EOL;
    }
    $playlist .= '#EXT-X-ENDLIST' . PHP_EOL;

    $playlistFile = $output . "_" . $key . ".m3u8";
    file_put_contents("../playlists/" . $playlistFile, $playlist);
    logger("++ Parsing input array; videos found - " . ((substr_count($playlist, ".mp4")) - 1));
    logger("++ Created playlist $playlistFile - " . (substr_count($playlist, PHP_EOL)) . " lines written to file");
}

function fileFinder($path)
{
    $files = scandir($path);
    $files = (array_values(preg_grep("/\d*?_\d*?_\d*?_\d*?\.mp4$/", $files)));
    sort($files);
    $fileArray = playlistSplitter($files);
    return $fileArray;
}

function playlistSplitter($files)
{
    $prevFileTime = null;
    $sliceArray = [];
    $array = [];
    logger("++ Total filecount: " . count($files));
    foreach ($files as $file) {
        $fileTime = (int) substr($file, 0, 10);
        if ($prevFileTime) {
            if ($fileTime - $prevFileTime > PLAYLISTCUTOFF) {
                logger("++ Found duration gap between files greater than " . PLAYLISTCUTOFF . "s");
                logger("++ Duration between " . $prevFileTime . " and " . $fileTime . " is: " . ($fileTime - $prevFileTime) . "s");
                $sliceArray[] = $file;
                logger("++ Splitting into another playlist at file number " . array_search($file, $files));
            }
        }
        $prevFileTime = $fileTime;
    }

    $result = [];
    foreach ($sliceArray as $delimiter) {
        foreach ($files as $key => $val) {
            if ($delimiter === $val) {
                $result[] = array_splice($files, 0, $key);
                continue 2;
            }
        }
    }
    if (!empty($files)) {
        $result[] = $files;
    }
    logger("++ Playlist split into " . count($result) . " chunks");

    // logger($result);
    return $result;
}

function absoluteToRelativePath($path)
{
    $pathParts = explode(DIRECTORY_SEPARATOR, $path);
    $pathParts = array_reverse($pathParts);
    array_splice($pathParts, 5);
    $pathParts = array_reverse($pathParts);
    $returnPath = CCTVALIASPATH . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $pathParts);
    $returnPath = str_replace('\\', '/', $returnPath);
    return $returnPath;
}
function prependPath(&$item, $key, $prefix)
{
    $item = $prefix . DIRECTORY_SEPARATOR . $item;
}
function ffprobeDuration($file)
{
    $framecount = (int) `ffprobe -v error -count_frames -select_streams v:0 -show_entries stream=nb_read_frames -of default=nokey=1:noprint_wrappers=1 $file`;
    $framerate = (int) `ffprobe -v error -count_frames -select_streams v:0  -show_entries stream=r_frame_rate -of default=nokey=1:noprint_wrappers=1 $file`;
    return number_format(($framecount / $framerate), 6, '.', '');
}
