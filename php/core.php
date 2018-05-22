<?php
// function global_playlist_builder($directories)
// {
//     foreach ($directories as $path) {
//         playlist_builder($path, playlist_name($path));
//     }
//     clean_old_playlists();
// }

function playlist_name($path)
{
    // "91614fae-dfcb-3be6-9958-5fa07b248114_2018_05_01"
    $pathComponents = array_reverse(explode(DIRECTORY_SEPARATOR, $path));
    $pathComponents = array_slice($pathComponents, 0, 4);
    $playlistName = implode("_", array_reverse($pathComponents));
    return $playlistName;
}
function playlist_builder($path, $output)
{
    $fileArray = file_finder($path);
    array_walk_recursive($fileArray, 'prepend_path', $path);
    foreach ($fileArray as $key => $chunks) {
        $playlists[] = playlist_writer($chunks, $key, $output);
    }
    return $playlists;
}

function clean_old_playlists()
{
    logger("++ Checking for old invalid playlists");
    $array = glob("playlists/*");
    $returnArray = [];
    foreach ($array as $path) {
        logger("++ Checking " . $path);
        $initFile = substr(get_line_with_string($path, "EXT-X-MAP:URI"), 16, -3);
        logger("++ Initial MP4 parsed as " . $initFile);
        if (!file_exists($initFile)) {
            logger("-- Playlist refers to missing file, deleting playlist");
            unlink($path);
            continue;
        }
        logger("++ Playlist references to file that exists, skipping");
        $fixedPath = explode("/", $path);
        array_shift($fixedPath);
        $fixedPath = implode("/", $fixedPath);
        $returnArray[] = $fixedPath;
    }
    logger("++ Valid playlists: ");
    logger($returnArray);
    return $returnArray;
}

function playlist_writer($files, $key, $output)
{
    $duration = ffprobe_duration($files[0]);

    foreach ($files as $file) {
        $playlistFiles[] = absolute_to_relative_path($file);
    }
    $playlist = <<<EOF
#EXTM3U
#EXT-X-VERSION:3
#EXT-X-TARGETDURATION:20
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
    file_put_contents(PLAYLISTPATH . $playlistFile, $playlist);
    logger("++ Parsing input array; videos found - " . ((substr_count($playlist, ".mp4")) - 1));
    logger("++ Created playlist $playlistFile - " . (substr_count($playlist, PHP_EOL)) . " lines written to file");
    $startTime = intval(substr(basename(reset($playlistFiles)), 0, 10));
    $endTime = intval(substr(basename(end($playlistFiles)), 0, 10));
    $return = [];
    $return[$playlistFile]["startTime"] = $startTime;
    $return[$playlistFile]["endTime"] = $endTime;
    $return[$playlistFile]["duration"] = $endTime - $startTime;
    $return[$playlistFile]["generated"] = time();
    return $return;
}

function file_finder($path)
{
    $files = scandir($path);
    $files = (array_values(preg_grep("/\d*?_\d*?_\d*?_\d*?\.mp4$/", $files)));
    sort($files);
    $fileArray = playlist_splitter($files);
    return $fileArray;
}

function playlist_splitter($files)
{
    $prevFileTime = null;
    $sliceArray = [];
    $array = [];
    logger("++ Total count of matching files in directory: " . count($files));
    foreach ($files as $file) {
        $fileTime = (int) substr($file, 0, 10);
        if ($prevFileTime) {
            if ($fileTime - $prevFileTime > PLAYLISTCUTOFF) {
                logger("++ Found duration gap between files greater than " . PLAYLISTCUTOFF . "s");
                logger(
                    "++ Found duration gap between files greater than " . PLAYLISTCUTOFF . "s - Duration between " .
                    $prevFileTime . " and " . $fileTime . " is: " . ($fileTime - $prevFileTime) . "s"
                );
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
    $counter = count($result);
    if ($counter > 1) {
        logger("++ Playlist split into " . $counter . " chunks");
    }

    // logger($result);
    return $result;
}

function absolute_to_relative_path($path)
{
    $pathParts = explode(DIRECTORY_SEPARATOR, $path);
    $pathParts = array_reverse($pathParts);
    array_splice($pathParts, 5);
    $pathParts = array_reverse($pathParts);
    $returnPath = CCTVALIASPATH . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $pathParts);
    $returnPath = str_replace('\\', '/', $returnPath);
    return $returnPath;
}
function prepend_path(&$item, $key, $prefix)
{
    $item = $prefix . DIRECTORY_SEPARATOR . $item;
}
function ffprobe_duration($file)
{
    $framecount = (int) `ffprobe -v error -count_frames -select_streams v:0 -show_entries stream=nb_read_frames -of default=nokey=1:noprint_wrappers=1 $file`;
    $framerate = (int) `ffprobe -v error -count_frames -select_streams v:0  -show_entries stream=r_frame_rate -of default=nokey=1:noprint_wrappers=1 $file`;
    return number_format(($framecount / $framerate), 6, '.', '');
}
function map_camera_dates($array)
{
    $array = directory_exploder($array);
    $outputArray = [];
    foreach ($array as $key => $val) {
        $camera = $val[count($val) - 4];
        $date = implode("/", array_slice($val, -3));
        $keyval = array_search($camera, $array);
        $outputArray[] = array($camera, $date);
    }
    foreach ($outputArray as list($uuid, $date)) {
        if (!isset($dateArray[$uuid])) {
            $dateArray[$uuid] = [];
        }

        $dateArray[$uuid][] = $date;
    }
    return $dateArray;
}
function map_camera_names($array)
{
    $array = directory_exploder($array);
    $outputArray = [];
    foreach ($array as $key => $items) {
        $uuid = $items[count($items) - 4];
        array_push($items, "meta");
        $scanPath = implode(DIRECTORY_SEPARATOR, $items);
        $dir = new DirectoryIterator($scanPath);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->getExtension() == "json") {
                $files[$fileinfo->getMTime()] = $fileinfo->getFilename();

            }
        }
        krsort($files);
        $fileTest = ($scanPath . DIRECTORY_SEPARATOR . reset($files));
        if (file_exists($fileTest)) {
            $jsonArray = json_decode(file_get_contents($fileTest));
            $cameraUUID = $uuid;
            $cameraName = $jsonArray->meta->cameraName;
            $cameraArray[$key]["uuid"] = $cameraUUID;
            $cameraArray[$key]["name"] = $cameraName;
        }
    }
    $cameraArray = array_values($cameraArray);
    ksort($cameraArray);
    return ($cameraArray);
}
function directory_mapper($path)
{
    $maxDepth = 3;
    $minDepth = 3;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
        RecursiveIteratorIterator::CATCH_GET_CHILD// Ignore "Permission denied"
    );
    $iterator->setMaxDepth($maxDepth);

    $paths = array($path);
    foreach ($iterator as $path => $dir) {
        if ($iterator->getDepth() >= $minDepth) {
            if ($dir->isDir()) {
                if (file_exists($dir . DIRECTORY_SEPARATOR . "meta")) {
                    $paths[] = $path;
                }

            }
        }

    }
    array_shift($paths);
    return $paths;
}
function get_line_with_string($fileName, $str)
{
    $lines = file($fileName);
    foreach ($lines as $lineNumber => $line) {
        if (strpos($line, $str) !== false) {
            return $line;
        }
    }
    return -1;
}

function directory_exploder($input)
{
    if (is_array($input)) {
        $return = [];
        foreach ($input as $path) {
            array_push($return, explode(DIRECTORY_SEPARATOR, $path));
        }
    } else {
        $return = explode(DIRECTORY_SEPARATOR, $input);
    }
    return $return;
}
function rate_limiter($file)
{
    if (file_exists($file)) {
        $diff = time() - filemtime($file);
        if ($diff > RATELIMIT) {
            logger("++ $file is older than " . RATELIMIT . " seconds, creating");
            return true;
        } else {
            logger("++ $file exists and isn't older than " . RATELIMIT . " seconds, skipping");
        }
    } else {
        logger("++ $file doesn't exist, creating");
        return true;
    }
}
