<?php
// test functions here
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/core.php';

$directories = directory_mapper(CCTVABSOLUTEPATH);

$json = (json_builder($directories));
logger("Raw object: ");
logger($json);
logger("JSON output: ");
logger(json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), true);

function json_builder($directories)
{
    $output = [];
    $output["cameras"] = camera_mapper($directories);
    $output["stats"] = ["videos" => 123456, "directories" => 10, "timeGenerated" => "1525974315"];
    return $output;
}

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
                // $startTime = $values["startTime"];
                // $endTime = $values["endTime"];
                // $cameras[$camera]["dates"][$date]["playlists"][$playlistName]["dirPath"] = $path;
                // $cameras[$camera]["dates"][$date]["playlists"][$playlistName]["startTime"] = $startTime;
                // $cameras[$camera]["dates"][$date]["playlists"][$playlistName]["endTime"] = $endTime;
                // $cameras[$camera]["dates"][$date]["playlists"][$playlistName]["duration"] = $endTime - $startTime;

            }

        }
    }

    return $cameras;
}

function playlist_path_parser($path)
{
    $pathComponents = array_reverse(explode(DIRECTORY_SEPARATOR, $path));
    $pathComponents = array_slice($pathComponents, 0, 4);
    $playlistName = implode("_", array_reverse($pathComponents));
    return $playlistName;
}

function map_paths_to_camera($path)
{
    $components = explode(DIRECTORY_SEPARATOR, $path);
    $camera = $components[count($components) - 4];
    return $camera;
}

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
function map_dates_to_camera($path)
{

    $components = directory_exploder($path);
    $camera = $components[count($components) - 4];
    $date = implode("/", array_slice($components, -3));
    return $date;

}
