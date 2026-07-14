$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$("#send").click(function () {
    let message = $("#message").val();

    if (message == "") {
        alert("Please enter message");
        return;
    }

    $("#loading").show();
    $("#reply").html("");

    $.post("/chat", {
        message: message
    }, function (response) {
        $("#loading").hide();
        $("#reply").html(response.reply);
    }).fail(function () {
        $("#loading").hide();
        alert("Something went wrong.");
    });
});
