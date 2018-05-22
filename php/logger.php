<?php
function logger($logText, $date = null)
{
    if (is_array($logText)) {
        echo "<pre>";
        print_r($logText);
        echo "</pre>";
    } elseif (is_object($logText)) {
        echo "<pre>";
        var_dump($logText);
        echo "</pre>";
    } else {
        $stamp = "<pre>";
        if ($date == false) {
            $stamp .= date('e H:i:s ');
        }
        $stamp .= $logText;
        $stamp .= PHP_EOL;
        $stamp .= "</pre>";
        echo $stamp;
    }
}
