var globalData;
var databaseDates;

function getjson(callback) {
    $.getJSON("json/cached.json", function(data) {
        globalData = data;
        callback(data);
    });
}

function camera_select(data) {
    Object.keys(data.cameras).map(function(item) {
        $elem = $("<option>")
            .val(item)
            .text(data.cameras[item].name);
        $("#camera").append($elem);
    });
}

function select_playlist() {
    var cameraIndex = $("#camera").val();
    var cameraPlaylist = $("#playlist").val();
    var playlistDirectory = "playlists";
    if (cameraIndex == -1) {
        console.log("no camera");
    } else {
        cameraUUID = globalData.cameras[cameraIndex].uuid;
        console.group("select_playlist");
        playlistURl = [playlistDirectory, cameraPlaylist]
            .filter(Boolean)
            .join("/");
        console.log(playlistURl);
        console.groupEnd();
        // Assume player instance is already created
        player.configure({
            source: playlistURl,
            loop: false,
            chromeless: false
        });
    }
}

function datepicker_populate() {
    var cameraIndex = $(this).val();
    console.log(cameraIndex);
    if (cameraIndex == -1) {
        $("#date").datepicker("setDate", null);
        $("#playlist").html(
            $("<option>")
                .val(-1)
                .text("- Playlist -")
        );
        $("#date").datepicker("option", "beforeShowDay", dateReset);
        player.configure({
            source: "splash.mp4",
            loop: true,
            chromeless: true
        });
    } else {
        databaseDates = Object.keys(globalData.cameras[cameraIndex].dates);
        $("#date").datepicker("option", "beforeShowDay", dateExcluder);
    }
}

function playlist_populate() {
    var cameraIndex = $("#camera").val();
    var date = $.datepicker.formatDate(
        "yy/mm/dd",
        $(this).datepicker("getDate")
    );

    $("#playlist").empty();
    globalData.cameras[cameraIndex].dates[date].forEach(function(
        playlist,
        index
    ) {
        $elem = $("<option>")
            .val(playlist)
            .text("Playlist " + (index + 1));
        $("#playlist").append($elem);
    });
}

var dateExcluder = function(date) {
    var string = jQuery.datepicker.formatDate("yy/mm/dd", date);
    // console.group("dateExcluder");
    // console.log(databaseDates);
    // console.log(string);
    // console.log(databaseDates.indexOf(string));
    // console.groupEnd();
    return [databaseDates.indexOf(string) >= 0];
};

var dateReset = function(date) {
    return [false, ""];
};

$(document).ready(function() {
    getjson(function(data) {
        camera_select(data);
    });
    $("#camera").change(datepicker_populate);
    $("#date").datepicker({
        dateFormat: "dd/mm/yy",
        beforeShowDay: dateReset,
        defaultDate: 0
    });
    $("#date").change(playlist_populate);
    $("#submit").click(select_playlist);
});
