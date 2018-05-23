<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/core.php';

$directories = directory_mapper(CCTVABSOLUTEPATH);
$totalcount = 0;

$json = (json_builder($directories));
logger("JSON output: " . $json);

/**
 * Playlist Builder
 *
 * Takes a root path of cctv files to scan and a location to place the playlists.
 *
 * @param string $path
 * @param string $output
 * @return array
 */
function playlist_builder($path, $output)
{
    $fileArray = file_finder($path);
    array_walk_recursive($fileArray, 'prepend_path', $path);
    foreach ($fileArray as $key => $chunks) {
        $playlists[] = playlist_writer($chunks, $key, $output);
    }
    return $playlists;
}

/**
 * File Finder
 *
 * Take a path and scan that directory path for mp4 files matching unifi video
 * naming standards.
 * E.g. 1524960000337_1524960002337_2147483647_10998775.mp4
 * Returns an array that is split into arrays of files depending on if the duration
 * between the files excedes a threshold.
 *
 * @param string $path
 * @return array
 */
function file_finder($path)
{
    global $totalcount;
    $files = scandir($path);
    $files = (array_values(preg_grep("/\d*?_\d*?_\d*?_\d*?\.mp4$/", $files)));
    $totalcount += count($files);
    sort($files);
    $fileArray = playlist_splitter($files);
    return $fileArray;
}

/**
 * Playlist Splitter
 *
 * Takes an array of files and analyses their filename to detect noncontigious
 * breaks, as the first 10 numbers of the file name is a unix timestamp.
 * Default split threshold is 30 seconds and is defined by PLAYLISTCUTOFF.
 * It returns an array of all the sub arrays of contigious videos.
 *
 * @param array $files
 * @return array
 */
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

/**
 * JSON Builder
 *
 * Arranges the JSON for export to storage.
 * Calls various functions to build the array to json_encode.
 *
 * @param [type] $directories
 * @return void
 */
function json_builder($directories)
{
    global $totalcount;
    $output = [];
    $output["cameras"] = camera_mapper($directories);
    $output["stats"] = ["videos" => $totalcount, "directories" => count($directories), "generated" => time()];
    $output = json_encode($output, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . "cached.json", $output);
    return $output;
}

/**
 * CCTV Directory Mapper
 *
 * This function makes a lot of assumptions to speed it up, based on observed
 * unifi video directory structures, where in you get a UUID for a camera,
 * then a year, then month, then day directory where in you get all the MP4
 * files and a meta folder with JSON/thumbnails in.
 * As such it takes the starting root directory and asses directories three
 * deep and then checks if that directory has a meta directory inside it.
 * If so, adds it to the array to return of valid directories.
 *
 * @param string $path
 * @return array
 */
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

/**
 * FFprobe Duration Scanner
 *
 * More assumptions here for efficiency, scan the first file for its duration.
 * Since the DASH fragments report incorrect container duration it's counted
 * manually with framerate and framecount.
 * It's returned as a 6 decimal point int.
 *
 * @param string $file
 * @return int
 */
function ffprobe_duration($file)
{
    $framecount = (int) `ffprobe -v error -count_frames -select_streams v:0 -show_entries stream=nb_read_frames -of default=nokey=1:noprint_wrappers=1 $file`;
    $framerate = (int) `ffprobe -v error -count_frames -select_streams v:0 -show_entries stream=r_frame_rate -of default=nokey=1:noprint_wrappers=1 $file`;
    return number_format(($framecount / $framerate), 6, '.', '');
}

/**
 * Absolute to Relative path remapper
 *
 * Takes a disk path and remaps it to be relative to CCTVALIASPATH.
 *
 * @param string $path
 * @return string
 */
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

/**
 * Playlist Writer
 *
 * Function takes an array of files, which chunk of that day it is ($key),
 * and an output filename to write to.
 * Returns stats about the playlist in an array.
 *
 * @param array $files
 * @param int $key
 * @param string $output
 * @return array
 */
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

    if (!is_dir($_SERVER['DOCUMENT_ROOT'] . PLAYLISTPATH)) {
        mkdir($_SERVER['DOCUMENT_ROOT'] . PLAYLISTPATH);
    }
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . PLAYLISTPATH . $playlistFile, $playlist);
    logger("++ Parsing input array; videos found - " . ((substr_count($playlist, ".mp4")) - 1));
    logger("++ Created playlist $playlistFile - " . (substr_count($playlist, PHP_EOL)) . " lines written to file");

    $startTime = intval(substr(basename(reset($playlistFiles)), 0, 10));
    $endTime = intval(substr(basename(end($playlistFiles)), 0, 10));

    $return[$playlistFile]["startTime"] = $startTime;
    $return[$playlistFile]["endTime"] = $endTime;
    $return[$playlistFile]["duration"] = $endTime - $startTime;
    $return[$playlistFile]["generated"] = time();
    return $return;
}

/**
 * Prepend Path
 *
 * Simple function to take a file and prepend a path to it.
 *
 * @param string $item
 * @param int $key
 * @param string $prefix
 * @return void
 */
function prepend_path(&$item, $key, $prefix)
{
    $item = $prefix . DIRECTORY_SEPARATOR . $item;
}

/**
 * Directory Exploder
 *
 * Take a path and return an array of the path components.
 *
 * @param string $input
 * @return array
 */
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

/**
 * Playlist Name
 *
 * Take a path string and spit out playlist filename encoded from that path.
 * Gets suffixed later with chunk number.
 *
 * @param string $path
 * @return string
 */
function playlist_name($path)
{
    // "91614fae-dfcb-3be6-9958-5fa07b248114_2018_05_01"
    $pathComponents = array_reverse(explode(DIRECTORY_SEPARATOR, $path));
    $pathComponents = array_slice($pathComponents, 0, 4);
    $playlistName = implode("_", array_reverse($pathComponents));
    return $playlistName;
}

/**
 * Camera Mapper
 *
 * This function does the heavy lifting work, looks at the paths and maps this
 * information to an array.
 * Initially it takes each path in the input array and makes an array of paths
 * mapped to a camera UUID.
 * After splitting the paths into their host cameras it iterates over this to
 * map a name (set in the unifi video frontend) to the camera UUID for the
 * frontend to populate a select box.
 * Then populates the array with the dates available by reading the paths,
 * after which it takes those and builds information on the playlists.
 *
 * @param array $array
 * @return array
 */
function camera_mapper($array)
{
    $cameras = [];
    $newestPath = [];

    // build array of paths to camera
    foreach ($array as $path) {
        $pathArray[map_paths_to_camera($path)][] = $path;
    }
    // iterate cameras and pass the array of paths to date builder
    foreach ($pathArray as $camera => $paths) {
        $cameras[$camera]["name"] = map_name_to_camera(end($paths));
        foreach ($paths as $path) {
            $date = map_dates_to_camera($path);
            $playlists = playlist_builder($path, playlist_name($path));
            $cameras[$camera]["dates"][$date] = [];
            foreach ($playlists as $values) {
                $playlistName = key($values);
                $cameras[$camera]["dates"][$date]["playlists"][$playlistName] = $values[$playlistName];
            }
        }
    }
    return $cameras;
}

/**
 * Mapping the paths to each camera
 *
 * Takes a path and pulls the camera UUID from it to return.
 *
 * @param string $path
 * @return string
 */
function map_paths_to_camera($path)
{
    $components = explode(DIRECTORY_SEPARATOR, $path);
    $camera = $components[count($components) - 4];
    return $camera;
}

/**
 * Mapping the name to the camera
 *
 * Takes the most recent unifi created JSON file for a camera and parses it for
 * the nametag.
 *
 * @param string $path
 * @return string
 */
function map_name_to_camera($path)
{

    $path = $path . DIRECTORY_SEPARATOR . "meta";
    $fileList = new DirectoryIterator($path);
    foreach ($fileList as $fileinfo) {
        if ($fileinfo->getExtension() == "json") {
            $files[$fileinfo->getMTime()] = $fileinfo->getFilename();

        }
    }
    krsort($files);
    $latest = ($path . DIRECTORY_SEPARATOR . reset($files));
    if (file_exists($latest)) {
        $json = json_decode(file_get_contents($latest));
        $name = $json->meta->cameraName;
        return $name;
    } else {
        return "JSON MISSING";
    }
}

/**
 * Mapping dates to a camera
 *
 * Takes a path and takes the parts that make up the date and returns them.
 *
 * @param string $path
 * @return string
 */
function map_dates_to_camera($path)
{

    $components = directory_exploder($path);
    $camera = $components[count($components) - 4];
    $date = implode("/", array_slice($components, -3));
    return $date;

}
