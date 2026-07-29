$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

let currentConversationId = null;

$('#send').click(function () {
    let message = $('#message').val();
    let documentId = $('#document_id').val();

    if (message === '') {
        alert('Please enter message');
        return;
    }

    $('#loading').show();
    $('#reply').html('');
    $('#sources').html('');

    $.post('/chat', {
        message: message,
        conversation_id: currentConversationId,
        document_id: documentId || null
    }, function (response) {
        $('#loading').hide();
        $('#reply').html(response.reply);

        if (response.sources && response.sources.length) {
            let sourcesHtml = '<strong>Sources</strong><ul>';
            response.sources.forEach(function (source) {
                sourcesHtml += '<li>' + source.document + ' · Page ' + source.page + '</li>';
            });
            sourcesHtml += '</ul>';
            $('#sources').html(sourcesHtml);
        }

        if (response.conversation_id) {
            currentConversationId = response.conversation_id;
        }
    }).fail(function () {
        $('#loading').hide();
        alert('Something went wrong.');
    });
});
