<?php
include_once(__DIR__ . '/../config.php');
require(__DIR__ .'/logger.php');

$file = "1525046400872_1525046402872_2147483647_97404775.mp4";

// $output = `ffmpeg -y -i test.mp4 -progress progress.txt -c copy test.mkv 2> output.txt`;

$framecount = (int)`ffprobe -v error -count_frames -select_streams v:0 -show_entries stream=nb_read_frames -of default=nokey=1:noprint_wrappers=1 $file 2> output.txt`;
$framerate = (int)`ffprobe -v error -count_frames -select_streams v:0  -show_entries stream=r_frame_rate -of default=nokey=1:noprint_wrappers=1 $file 2> output.txt`;
$duration = $framecount / $framerate;

var_dump($framecount);
var_dump($framerate);
var_dump($duration);