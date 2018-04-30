var jsonIngress = 

var databaseDates = ["05/04/2018", "07/04/2018", "22/04/2018"];

var dateExcluder = function(date) {
    var string = jQuery.datepicker.formatDate("dd/mm/yy", date);
    return [databaseDates.indexOf(string) >= 0];
};

var ajaxPost = function() {
    var dateSelected = $("#date").val();
    $.ajax({
        url: "php/ajax.php",
        data: { date: dateSelected },
        type: "POST",
        success: function(data) {
            $("#result").html(data);
        }
    });
};

$(document).ready(function() {
    $("#date").datepicker({
        dateFormat: "dd/mm/yy",
        beforeShowDay: dateExcluder
    });
    $("#submit").click(ajaxPost);
});
