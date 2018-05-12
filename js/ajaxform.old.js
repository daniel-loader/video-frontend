var dateExcluder = function(date) {
    var string = jQuery.datepicker.formatDate("dd/mm/yy", date);
    return [databaseDates.indexOf(string) >= 0];
};

var ajaxPost = function() {
    var dateSelected = $("#date").val();
    $.ajax({
        url: "ajax.php",
        data: { testing: dateSelected },
        type: "POST",
        success: function(data) {
            $("#result").html(data);
        }
    });
};

$(document).ready(function() {
    $.getJSON("json/cached.json", function(json) {
        Object.keys(json.cameras).map(item => {
            $("#cameras").append(
                `<option value="${item}">${json.cameras[item].name}</option>`
            );
        });
        $("#cameras").change(function() {
            var databaseDates = Object.keys(json.cameras[$(this).val()].dates);
            console.log(databaseDates);
        });
    });
    $("#date").datepicker({
        dateFormat: "dd/mm/yy",
        beforeShowDay: dateExcluder,
        defaultDate: 0
    });
    $("#submit").click(ajaxPost);

    // $("option").options.selectedIndex = 0;
});
