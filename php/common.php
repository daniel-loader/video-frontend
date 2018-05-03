<?php
include_once __DIR__ . '/../config.php';
require __DIR__ . '/logger.php';
require __DIR__ . '/playlist.php';

//debug printing
logger("++ Building index of directories in " . CCTVABSOLUTEPATH . ":");
$directories = rawDirectoryMapper(CCTVABSOLUTEPATH);
logger($directories);
logger("++ Filtering directories with meta folder children: ");
logger(validPaths($directories));
logger("++ Array of valid cameras with nametag: ");
$camaraWithMeta = cameraNameMapper(validPaths($directories));
logger($camaraWithMeta);
logger("++ Array of valid cameras and associated dates available: ");
$foundCameraDates = validCameraDates(validPaths($directories));
logger($foundCameraDates);

foreach ((validPaths($directories)) as $path) {
    playlistBuilder($path, playlistName($path));
}

function playlistName($path)
{
    // "91614fae-dfcb-3be6-9958-5fa07b248114_2018_05_01"
    $pathComponents = array_reverse(explode(DIRECTORY_SEPARATOR, $path));
    $pathComponents = array_slice($pathComponents, 0, 4);
    $playlistName = implode("_", array_reverse($pathComponents));
    return $playlistName;
}

function cameraNameMapper($paths)
{
    $directoryArray = [];
    $pathComponents = directoryExploder($paths);
    foreach ($pathComponents as $items) {
        $items = array_reverse($items);
        $hash = $items[3];
        $items = array_reverse($items);
        array_push($items, "meta");
        $imploded = implode(DIRECTORY_SEPARATOR, $items);
        $files = (scandir($imploded, 1));

        $jsonPath = (array_values(preg_grep("/\.json/", $files))[0]);
        $directoryArray[$hash] = $imploded . DIRECTORY_SEPARATOR . $jsonPath;
        foreach ($directoryArray as $path) {
            $jsonArray = json_decode(file_get_contents($path));
            $cameraUUID = $hash;
            $cameraName = $jsonArray->meta->cameraName;
            $cameraArray[$cameraUUID] = $cameraName;
        }
    }
    return ($cameraArray);
}

function directoryExploder($array)
{
    $directoryArray = [];
    foreach ($array as $path) {
        array_push($directoryArray, explode(DIRECTORY_SEPARATOR, $path));
    }
    return $directoryArray;
}

function validCameraDates($array)
{
    $array = reorderArray(directoryExploder($array));
    foreach ($array as $key => $val) {
        $date = implode("/", array_slice($val, 0, 3));
        $camera = $val[3];
        $keyval = array_search($camera, $array);
        $outputArray[] = array('camera' => $camera, 'date' => $date);
    }

    return $outputArray;
}

function validPaths($array)
{
    $array = (array_filter(directoryExploder($array), "isMeta"));
    $array = reorderArray($array);
    $array = cameraDirectoryMapper($array);
    return $array;
}

function cameraDirectoryMapper($array)
{
    // Take exploded directory array and produce absolute filepaths to the CCTV
    $outputArray = [];
    foreach ($array as $pathComponents) {
        array_push($outputArray, implode(DIRECTORY_SEPARATOR, array_reverse(array_slice($pathComponents, 1))));
    }
    return $outputArray;
}

function rawDirectoryMapper($baseDir)
{
    $directories = [];
    foreach (scandir($baseDir) as $item) {
        if (!preg_match('/^([^.])/', $item)) {
            // ignore existing, parent and hidden items
            continue;
        }

        $dir = $baseDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($dir)) {
            // check if directory, if so add to array
            $directories[] = $dir;
            $directories = array_merge($directories, rawDirectoryMapper($dir));
        }
    }
    return $directories;
}

function isMeta($elem)
{
    // only return arrays with a meta directory value
    // saner than using fixed depth searches from the rootdir
    return (in_array("meta", $elem));
}

function reorderArray($array)
{
    // reorder the array index values
    $array = array_values($array);
    $newArray = [];
    // flip values stored for the path so deepest is first to omit
    // potentially deeper parent paths
    foreach ($array as $key => $val) {
        $newArray[$key] = array_reverse($val);
    }
    return $newArray;
}
