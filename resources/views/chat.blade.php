<!DOCTYPE html>
<html>
<head>
    <title>Laravel PDF Chat</title>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/chat.css') }}" />
</head>

<body>
    <div class="container">
        <h2>Laravel PDF Chat</h2>

        @if (session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif

        <form action="{{ route('documents.upload') }}" method="POST" enctype="multipart/form-data" class="upload-box">
            @csrf
            <input type="file" name="documents[]" multiple accept="application/pdf" />
            <button type="submit">Upload PDFs</button>
        </form>

        @if ($documents->isNotEmpty())
            <div class="document-list">
                <h3>Uploaded documents</h3>
                <ul>
                    @foreach ($documents as $document)
                        <li>
                            <strong>{{ $document->original_name }}</strong>
                            <span>{{ $document->status }}</span>
                            <form
                                action="{{ route('documents.delete', $document) }}"
                                method="POST"
                                style="display: inline"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <label for="document_id">Choose document</label>
        <select id="document_id">
            <option value="">Use all available context</option>
            @foreach ($documents as $document)
                <option value="{{ $document->id }}">{{ $document->original_name }}</option>
            @endforeach
        </select>

        <textarea id="message" placeholder="Ask a question about your uploaded PDFs..."></textarea>
        <button id="send">Ask AI</button>
        <div id="loading">Thinking...</div>
        <div id="reply"></div>
        <div id="sources"></div>
    </div>

    <script src="{{ asset('js/chat.js') }}"></script>
</body>
</html>
