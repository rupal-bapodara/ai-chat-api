$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

let currentConversationId = null;

$("#send").click(function () {
    let message = $("#message").val();

    if (message == "") {
        alert("Please enter message");
        return;
    }

    $("#loading").show();
    $("#reply").html("");

    $.post("/chat", {
        message: message,
        conversation_id: currentConversationId
    }, function (response) {
        $("#loading").hide();
        $("#reply").html(response.reply);

        if (response.conversation_id) {
            currentConversationId = response.conversation_id;
        }
    }).fail(function () {
        $("#loading").hide();
        alert("Something went wrong.");
    });
});
