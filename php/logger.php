<?php
function logger($logText)
{
    if (is_array($logText)) {
        echo "<pre>";
        print_r($logText);
        echo "</pre>";
    } else {
        $stamp = "<pre>";
        $stamp .= date('e H:i:s ');
        $stamp .= $logText;
        $stamp .= PHP_EOL;
        $stamp .= "</pre>";
        echo $stamp;
    }
}
