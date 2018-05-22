var globalData;
var databaseDates;

function getjson(callback) {
    $.getJSON("json/cached.json", function(data) {
        globalData = data;
        callback(data);
        console.log(globalData);
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
        playlistURL = [playlistDirectory, cameraPlaylist].filter(Boolean).join("/");
        console.log(playlistURL);
        console.groupEnd();
        // Assume player instance is already created
        player.configure({
            source: playlistURL,
            loop: false,
            chromeless: false
        });
    }
}

function unix_to_localtime(unixtime) {
    var time = new Date(unixtime * 1000);
    var hours = ("0" + time.getHours()).slice(-2);
    var minutes = ("0" + time.getMinutes()).slice(-2);
    var seconds = ("0" + time.getSeconds()).slice(-2);
    localtime = hours + ":" + minutes + ":" + seconds;
    return localtime;
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
    var date = $.datepicker.formatDate("yy/mm/dd", $(this).datepicker("getDate"));

    $("#playlist").empty();
    Object.keys(globalData.cameras[cameraIndex].dates[date].playlists).forEach(function(playlist) {
        startTime = unix_to_localtime(globalData.cameras[cameraIndex].dates[date].playlists[playlist].startTime);
        endTime = unix_to_localtime(globalData.cameras[cameraIndex].dates[date].playlists[playlist].endTime);
        $elem = $("<option>")
            .val(playlist)
            .text(startTime + " to " + endTime);
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
