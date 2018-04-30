<?php
function playlistBuilder($path)
{
    logger("++ Building playlist in: " . $path . ":");
    $files = scandir($path);
    $files = (array_values(preg_grep("/\d*?_\d*?_\d*?_\d*?\.mp4$/", $files)));
    sort($files);
    array_walk($files, 'prependPath', $path);
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

    file_put_contents("../playlists/1.m3u8", $playlist);
    logger("++ Videos found - " . ((substr_count($playlist, ".mp4")) - 1));
    logger("++ Created playlist - " . (substr_count($playlist, PHP_EOL)) . " lines written to file");
    //logger("++ Playlist output:" . PHP_EOL . $playlist);
}

function absoluteToRelativePath($path)
{
    $cctvAliasPath = DIRECTORY_SEPARATOR . "cctv";
    $pathParts = explode(DIRECTORY_SEPARATOR, $path);
    $pathParts = array_reverse($pathParts);
    array_splice($pathParts, 5);
    $pathParts = array_reverse($pathParts);
    $returnPath = $cctvAliasPath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $pathParts);
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
