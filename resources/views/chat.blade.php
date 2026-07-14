<!DOCTYPE html>
<html>

<head>

    <title>Laravel AI Chat</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <link rel="stylesheet" href="{{ asset('css/chat.css') }}">

</head>

<body>

    <div class="container">

        <h2>Laravel AI Chat</h2>

        <textarea id="message" placeholder="Ask anything..."></textarea>

        <br>

        <button id="send">

            Ask AI

        </button>

        <div id="loading">

            Thinking...

        </div>

        <div id="reply"></div>

    </div>

    <script src="{{ asset('js/chat.js') }}"></script>

</body>

</html>
