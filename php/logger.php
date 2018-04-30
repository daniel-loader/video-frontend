<?php
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