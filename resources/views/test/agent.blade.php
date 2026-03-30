<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Test</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; }
        label { display: block; margin-top: 16px; font-weight: bold; }
        select, textarea, input[type="text"] { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        textarea { height: 120px; resize: vertical; }
        button { margin-top: 16px; padding: 10px 24px; cursor: pointer; }
        pre { background: #f4f4f4; padding: 16px; white-space: pre-wrap; word-break: break-all; margin-top: 24px; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Agent Prompt Tester</h1>

    <form id="agent-form">
        @csrf
        <label for="agent">Agent</label>
        <select id="agent" name="agent" required>
            <option value="arcee">Arcee</option>
            <option value="fluxklein">FluxKlein</option>
            <option value="hunteralpha">HunterAlpha</option>
            <option value="nvidia" selected>Nvidia</option>
            <option value="stepfun">StepFun</option>
        </select>

        <label for="message">Message</label>
        <textarea id="message" name="message" required placeholder="Enter your message..."></textarea>

        <label for="conversation_id">Conversation ID <small>(optional — paste a previous ID to continue a conversation)</small></label>
        <input type="text" id="conversation_id" name="conversation_id" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">

        <button type="submit">Send</button>
    </form>

    <div id="output"></div>

    <script>
        document.getElementById('agent-form').addEventListener('submit', async function (e) {
            e.preventDefault();

            const agent = document.getElementById('agent').value;
            const message = document.getElementById('message').value;
            const conversationId = document.getElementById('conversation_id').value.trim();
            const output = document.getElementById('output');

            output.innerHTML = '<p>Sending...</p>';

            const body = { message };
            if (conversationId) {
                body.conversation_id = conversationId;
            }

            try {
                const response = await fetch(`/api/agents/${agent}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                            ?? '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(body),
                });

                const data = await response.json();

                if (!response.ok) {
                    output.innerHTML = `<p class="error">Error ${response.status}</p><pre>${JSON.stringify(data, null, 2)}</pre>`;
                    return;
                }

                if (data.conversation_id) {
                    document.getElementById('conversation_id').value = data.conversation_id;
                }

                output.innerHTML = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            } catch (err) {
                output.innerHTML = `<p class="error">${err.message}</p>`;
            }
        });
    </script>
</body>
</html>
