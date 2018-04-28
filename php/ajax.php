<?php
// debugging block (print errors in browser)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['date']) && !empty($_POST['date'])) {
    // logic to execute if all the variables are correctly sent to php
    $getdate = $_POST['date'];
    echo "date selected: $getdate <br/>";
} else {
    // warning sent that form is incomplete
    echo "<h2>Please select a date</h2>";
}
