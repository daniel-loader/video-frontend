<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/php/logger.php';
require_once __DIR__ . '/php/core.php';
//require __DIR__ . '/php/test.php';

// switchcase goes here to map ajax calls to functions below
switch (true) {
    case isset($_POST['testing']):
        testing($_POST);
        break;
    case isset($_POST['init']):
        init($_POST);
        break;
    case isset($_POST['refresh_playlist']):
        refresh_playlist($_POST);
        break;
    case isset($_POST['refresh_all_playlists']):
        //do something
        break;
}
// testing function to confirm ajax works
function testing($ajaxPost)
{
    if (isset($ajaxPost['testing']) && !empty($ajaxPost['testing'])) {
        // logic to execute if all the variables are correctly sent to php
        $getdate = $ajaxPost['testing'];
        echo "date selected: $getdate <br/>";
    } else {
        // warning sent that form is incomplete
        echo "<h2>Please select a date</h2>";
    }
}

// function for returning camera names to populate select box
function init($ajaxPost)
{
    $directories = directory_mapper(CCTVABSOLUTEPATH);
    echo json_encode(map_camera_names($directories));
}

// function for (re)building playlist for current camera and return a trigger to reload the playlist
function refresh_playlist($ajaxPost)
{
    if (isset($_POST['refresh_playlist']) && !empty($_POST['refresh_playlist'])) {
        // logic to execute if all the variables are correctly sent to php
    } else {
        // warning sent that form is incomplete
    }
}

// function for cleaning old playlists and rebuilding index of playlists and trigger select box refresh

// function for exporting video to /exports and returning download link

// function for full reindex of files to create playlists (should be hidden by default)
